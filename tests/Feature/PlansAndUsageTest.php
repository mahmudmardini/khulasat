<?php

declare(strict_types=1);

use App\Actions\Publish\PublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Summary\TransitionJob;
use App\Contracts\PublishStore;
use App\Domain\Summary\JobState;
use App\Enums\AuditAction;
use App\Enums\UsageEvent;
use App\Models\AuditEvent;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Render\PageRenderer;
use App\Support\Billing\Plan;
use App\Support\Publish\Paths;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * الشرائح والتفعيل اليدوي — T-23، والمواصفة §11، وSCREENS.md الشاشة 9.
 *
 * **ولا بوّابة دفع** — قرار مالك المنتج 7 أيلول 2026. فالمقيس هنا ثلاثة:
 * أنّ الشريحة **قالبُ ملءٍ لا قفل**، وأنّ الاستهلاك يُعرض **بالملخّصات لا
 * بالتوكنز**، وأنّ التعليق **يمنع الإنتاج ولا يمسّ المنشور**.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'slug' => 'tenant-a',
        'plan' => 'free',
        'monthly_quota' => 3,
        'daily_cap' => 3,
        'max_lecture_minutes' => 90,
        'transcription_minutes_quota' => 90,
        'regenerations_per_summary' => 2,
    ]);

    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

/*
 * ─── الشرائح: ثوابتُ إعدادٍ لا جدول ──────────────────────────────────
 */

/**
 * ★ **دقائق التفريغ مشتقّةٌ، والاشتقاق يُفحص لا يُوصف** — قرار 8 أيلول 2026.
 *
 * فالحدود الأربعة الأخرى منقولةٌ عن جدول التسعير في الدراسة، **وهذا وحده
 * ليس فيها**: الدراسة تذكر البند ولا تعطي رقماً. ورقمٌ مشتقٌّ بقاعدةٍ
 * مكتوبةٍ في تعليق **يفترق عن قاعدته بعد أشهر ولا يُلاحَظ** — فتُفحص هنا.
 *
 * والقاعدة: الحصّة × أطول درس × نصيبِ ما يأتي صوتاً. **والنصفُ في
 * المدفوعة** — لأنّ الترجمات الجاهزة والنصّ الملصوق لا يُحتسبان.
 * **والكلُّ في التجربة** — فتجربةٌ تعجز عن تفريغ ما تبيعه ليست تجربة.
 */
it('يشتقّ دقائق التفريغ من الحصّة وأطول درس، لا من رقمٍ مكتوب باليد', function (): void {
    foreach (Plan::all() as $key => $plan) {
        $theoretical = $plan->limits['monthly_quota'] * $plan->limits['max_lecture_minutes'];

        // التجربة تغطية كاملة، وما عداها النصف.
        $expected = $key === 'free' ? $theoretical : intdiv($theoretical, 2);

        expect($plan->limits['transcription_minutes_quota'])
            ->toBe($expected, "دقائق التفريغ في شريحة «{$key}» تفارق اشتقاقها");
    }
});

/**
 * **ولا شريحةٍ تبيع ملخّصاتٍ لا تستطيع تفريغ أوّلها.**
 *
 * فالحدّان مستقلّان في قاعدة البيانات، ويُضبطان يدوياً من لوحة المشرف.
 * وجهةٌ دقائقُها دون أطول درسٍ عندها **تُوقَف عند أوّل رفع صوت** — والرسالة
 * تقول «نفدت دقائقكم» وهي لم تُستعمل بعد.
 */
it('يعطي كل شريحة دقائق تكفي درساً واحداً بأطول مدّة على الأقلّ', function (): void {
    foreach (Plan::all() as $key => $plan) {
        expect($plan->limits['transcription_minutes_quota'])
            ->toBeGreaterThanOrEqual(
                $plan->limits['max_lecture_minutes'],
                "شريحة «{$key}» لا تكفي دقائقُها درساً واحداً",
            );
    }
});

it('يعرّف كل شريحة حدودها الخمسة كاملةً', function (): void {
    $plans = Plan::all();

    expect($plans)->not->toBeEmpty();

    foreach ($plans as $plan) {
        expect($plan->nameAr)->not->toBe('')
            ->and(array_keys($plan->limits))->toBe(Plan::LIMITS);

        foreach (Plan::LIMITS as $column) {
            expect($plan->limits[$column])->toBeInt();
        }
    }
});

/** «الشرائح ثوابتُ في `config`، لا جدولاً في قاعدة البيانات» — معيار القبول. */
it('لا يبني جدول شرائح في قاعدة البيانات', function (): void {
    expect(Schema::hasTable('plans'))->toBeFalse()
        ->and(Schema::hasTable('subscriptions'))->toBeFalse();
});

/** وكلّ شريحةٍ في `rich_plans` شريحةٌ معرَّفة — وإلّا فقفلٌ على اسمٍ لا وجود له. */
it('يشير كل مفتاح في المخرجات الإضافية إلى شريحة قائمة', function (): void {
    $keys = array_keys(Plan::all());

    foreach ((array) config('khulasah.outputs.rich_plans') as $rich) {
        expect($keys)->toContain($rich);
    }
});

/*
 * ─── التطبيق: يملأ الأعمدة ولا يقفلها ────────────────────────────────
 */

function asAdmin(): User
{
    // المشرف العامّ مستخدمٌ بلا جهة على حارسٍ آخر — T-21.
    $admin = User::factory()->superAdmin()->create();

    test()->actingAs($admin, 'admin');

    return $admin;
}

it('يملأ الأعمدة الخمسة ويكتب اسم الشريحة', function (): void {
    asAdmin();

    $expected = Plan::all()['business'];

    test()->put(route('admin.tenants.plan', $this->tenant), [
        'plan' => 'business',
        'note' => 'حوالة 12 رجب — 149، مرجع TR-9001',
    ])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->plan)->toBe('business');

    foreach ($expected->limits as $column => $value) {
        expect((int) $this->tenant->{$column})->toBe($value);
    }
});

/**
 * ★ **الشريحة نقطةُ بداية لا قفل** — معيار القبول الثاني.
 *
 * «جهةٌ على الشريحة الأساسية بحصّةٍ مرفوعة استثناءً حالةٌ واقعية، ومن أقفل
 * الأرقام على الشريحة اضطرّ إلى اختراع شريحةٍ لكلّ استثناء».
 */
it('يبقي كل رقم قابلاً للتعديل وحده بعد تطبيق الشريحة', function (): void {
    asAdmin();

    test()->put(route('admin.tenants.plan', $this->tenant), [
        'plan' => 'starter',
        'note' => 'حوالة — مرجع TR-1',
    ]);

    $limits = $this->tenant->refresh()->only(Plan::LIMITS);

    test()->put(route('admin.tenants.limits', $this->tenant), [
        ...$limits,
        'monthly_quota' => 40,
        'note' => 'استثناء متّفق عليه — مرجع TR-2',
    ])->assertRedirect();

    $this->tenant->refresh();

    expect((int) $this->tenant->monthly_quota)->toBe(40)
        // والشريحة تبقى اسمَها ولا تُمحى بالاستثناء.
        ->and($this->tenant->plan)->toBe('starter')
        // وسائر الأرقام على ما وضعته الشريحة.
        ->and((int) $this->tenant->max_lecture_minutes)->toBe(Plan::all()['starter']->limits['max_lecture_minutes']);
});

/** ويُعرف الاستثناء: أعمدةُ الجهة لا تطابق قالب شريحتها. */
it('يعرف أن حدود الجهة عُدّلت عن شريحتها', function (): void {
    $plan = Plan::all()['starter'];

    $plan->applyTo($this->tenant);
    $this->tenant->save();

    expect($plan->matches($this->tenant))->toBeTrue();

    $this->tenant->forceFill(['monthly_quota' => 40])->save();

    expect($plan->matches($this->tenant->refresh()))->toBeFalse();
});

/** وتطبيقُ الشريحة حدثٌ ماليّ: لا يمرّ بلا سببٍ مكتوب — كتعديل الحدود. */
it('يرفض تطبيق شريحة بلا سبب مكتوب', function (): void {
    asAdmin();

    test()->put(route('admin.tenants.plan', $this->tenant), ['plan' => 'enterprise'])
        ->assertSessionHasErrors('note');

    expect($this->tenant->refresh()->plan)->toBe('free')
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('يقيّد تطبيق الشريحة بمن فعله وبسببه', function (): void {
    $admin = asAdmin();

    test()->put(route('admin.tenants.plan', $this->tenant), [
        'plan' => 'business',
        'note' => 'حوالة بنكية — مرجع TR-9001',
    ]);

    $event = AuditEvent::query()->latest('id')->firstOrFail();

    expect($event->action)->toBe(AuditAction::TenantPlan)
        ->and($event->admin_email)->toBe($admin->email)
        ->and($event->note)->toContain('TR-9001')
        ->and($event->changes['plan'])->toEqual(['from' => 'free', 'to' => 'business'])
        ->and($event->changes['monthly_quota'])->toEqual(['from' => 3, 'to' => 20]);
});

/** وتطبيقُ الشريحة القائمة بحدودها كما هي ليس تعديلاً: لا سبب ولا صفّ. */
it('لا يقيّد ولا يطلب سبباً لتطبيق شريحةٍ لا يغيّر شيئاً', function (): void {
    asAdmin();

    Plan::all()['free']->applyTo($this->tenant);
    $this->tenant->save();

    test()->put(route('admin.tenants.plan', $this->tenant), ['plan' => 'free'])
        ->assertSessionHasNoErrors();

    expect(AuditEvent::query()->count())->toBe(0);
});

/*
 * ─── المخرجات الإضافية: معطَّلة لا مخفيّة ────────────────────────────
 */

it('يفتح المخرجات الإضافية لشريحة مؤسسة فما فوق', function (): void {
    foreach (['free' => false, 'starter' => false, 'business' => true, 'enterprise' => true] as $plan => $allowed) {
        $this->tenant->forceFill(['plan' => $plan])->save();

        expect($this->tenant->refresh()->allowsRichOutputs())->toBe($allowed, $plan);
    }
});

it('يمنع بناء الكاروسيل على شريحةٍ لا تملكه، ويشرح السبب', function (): void {
    $job = SummaryJob::factory()->inState(JobState::Published)->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('jobs.carousel.store', $job))
        ->assertSessionHasErrors('carousel');

    // **والشاشة تبقى مفتوحة تشرح ما ينقص** — SCREENS.md §3-ب.
    $this->actingAs($this->user)
        ->get(route('jobs.carousel', $job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('job.locked', true));
});

/**
 * ★ **الخانة تعني ما تقول.**
 *
 * وكانت `want_carousel` تُرسل من الشاشة ولا يقرؤها أحد: لا قاعدة تحفظها ولا
 * خطٌّ يبنيها. وما كان يُرى ذلك لأنّ `rich_plans` كانت فارغة فالخانة معطَّلةٌ
 * أبداً — **وفتحُها للشرائح الأعلى (T-23) يجعل الوعدَ حيّاً**، فوجب أن يُوفى.
 */
it('يحفظ طلب الكاروسيل لمن تملكه شريحته', function (): void {
    Queue::fake();

    $this->tenant->forceFill(['plan' => 'business'])->save();

    $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
        'want_carousel' => true,
    ])->assertSessionHasNoErrors();

    expect(Lecture::query()->firstOrFail()->want_carousel)->toBeTrue();
});

/** **والشريحة تحرسه في الخادم**، فلا يمرّ من طلبٍ مصنوعٍ باليد. */
it('يتجاهل طلب الكاروسيل من شريحةٍ لا تملكه', function (): void {
    Queue::fake();

    $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
        'want_carousel' => true,
    ])->assertSessionHasNoErrors();

    expect(Lecture::query()->firstOrFail()->want_carousel)->toBeFalse();
});

/*
 * ─── التعليق: يمنع الإنتاج ولا يمسّ المنشور ──────────────────────────
 */

/** ★ معيار القبول الرابع، الشقّ الأوّل: **إنشاء ملخّص يُرفض برسالة عربية**. */
it('يرفض إنشاء ملخّص لجهةٍ معلَّقة برسالة عربية', function (): void {
    Queue::fake();

    $this->tenant->forceFill(['status' => Tenant::SUSPENDED])->save();

    $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'عنوان مُدخَل',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
    ])->assertSessionHasErrors('quota');

    expect(SummaryJob::query()->count())->toBe(0)
        ->and(session('errors')->first('quota'))->toBe(trans('errors.quota.suspended'))
        // عربيةٌ لا رمز، ولا كلمة «توكن» ولا اسم نموذج.
        ->and(trans('errors.quota.suspended'))->toMatch('/[\x{0600}-\x{06FF}]/u');
});

/**
 * ★ **الشقّ الثاني، وهو الأهمّ: صفحتها المنشورة تبقى تُخدَم.**
 *
 * «الجهة نشرت هذه الصفحات وشاركها الناس، وإسقاطها لخلافٍ ماليّ يضرّ بمن
 * لا ذنب له». وسببُ القاعدة السمعةُ لا الفوترة.
 */
it('يبقي صفحة الجهة المعلَّقة منشورةً كما هي', function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title_ar' => 'عنوان الدرس',
        ])->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);

    app(RenderOutput::class)->handle($job, app(PageRenderer::class));

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->refresh()), BrandKit::forTenant($this->tenant))
        ->contents;

    app(PublishSummary::class)->handle($job->refresh(), ['page' => $html]);

    $output = $job->refresh()->outputs()->firstOrFail();
    $path = (string) $output->storage_path;
    $url = $output->public_url;

    // ثمّ يُعلَّق الاشتراك.
    $this->tenant->forceFill(['status' => Tenant::SUSPENDED])->save();

    $store = app(PublishStore::class);

    /*
     * **والشاهدة تكتب فوق الصفحة نفسها** ({@see Paths::tombstone()})، فوجودُ
     * المسار لا يقول شيئاً. والمقيس محتواه: أهو الملخّص أم «أُزيل»؟
     */
    expect($store->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->toContain('عنوان الدرس')
        ->and(Storage::disk('public')->get($path))->not->toContain('أُزيل هذا الملخّص');

    $job->refresh();

    expect($job->state)->toBe(JobState::Published)
        ->and($job->published_at)->not->toBeNull()
        ->and($job->unpublished_at)->toBeNull()
        ->and($job->outputs()->firstOrFail()->public_url)->toBe($url);
});

/*
 * ─── شاشة الاستهلاك: بالملخّصات لا بالتوكنز ──────────────────────────
 */

it('يعرض الشريحة والحدود والاستهلاك للجهة', function (): void {
    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Generate->value,
        'units' => 1,
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Billing')
            ->where('plan.name', Plan::all()['free']->nameAr)
            ->where('plan.rich_outputs', false)
            ->where('limits.monthly_quota', 3)
            ->where('usage.summaries.used', 1)
            ->where('suspended', false)
            ->has('ledger', 1));
});

/**
 * ★ **الحصّة تُستهلك فعلاً عند إنشاء الملخّص** — المواصفة §4 و§11.
 *
 * وكان `usage_ledger` لا يُكتب فيه إلّا صفوفُ كلفةِ استدعاء النموذج
 * بـ`units: 0`، **فكان مجموع الوحدات صفراً أبداً**: الحصّة الشهرية لا تنفد
 * مهما أُنشئ، وشاشةُ الاستهلاك تقول «استعملت ٠» بعد عشرة ملخّصات.
 */
it('يخصم وحدةً من الحصّة عند إنشاء كل ملخّص', function (): void {
    Queue::fake();

    foreach (['الأوّل', 'الثاني'] as $title) {
        $this->actingAs($this->user)->post('/panel/lectures', [
            'source_kind' => 'text',
            'transcript_text' => str_repeat('كلمة ', 600),
            'title_ar' => $title,
            'speaker_name' => 'اسم الملقي',
            'venue_mode' => 'institution',
        ])->assertSessionHasNoErrors();
    }

    expect(UsageRecord::query()->where('units', '>', 0)->count())->toBe(2)
        ->and((int) UsageRecord::query()->sum('units'))->toBe(2);

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertInertia(fn ($page) => $page->where('usage.summaries.used', 2));
});

/**
 * وصفوفُ كلفةِ استدعاء النموذج لا تُعرض ولا تُخصم.
 *
 * فهي ستّةٌ لكلّ ملخّص، وعرضُها يملأ السجلّ بأسطر «٠ ملخّص» — **وهو بابُ
 * التوكنز نفسه من حيث لا يُسمّى**.
 */
it('لا يعرض صفوف كلفة النموذج في سجلّ الشهر ولا يخصمها', function (): void {
    $job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'summary_job_id' => $job->id,
        'event' => UsageEvent::Generate->value,
        'units' => 0,
        'cost_usd' => 0.42,
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertInertia(fn ($page) => $page
            ->has('ledger', 0)
            ->where('usage.summaries.used', 0));
});

/** والسجلّ من `usage_ledger` وحده — لا من عدّ `summary_jobs` (§4). */
it('يقرأ سجلّ الشهر من دفتر الاستهلاك لا من عدّ المهامّ', function (): void {
    // مهمّتان بلا صفوف استهلاك: لا تظهران في السجلّ.
    SummaryJob::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    // وصفٌّ لإعادة توليدٍ لا مهمّة له: يظهر.
    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Regenerate->value,
        'units' => 1,
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertInertia(fn ($page) => $page
            ->has('ledger', 1)
            ->where('ledger.0.event', 'regenerate'));
});

/** ولا يرى مستخدمٌ سجلَّ جهةٍ أخرى — §10. */
it('لا يُظهر استهلاك جهةٍ أخرى', function (): void {
    $other = Tenant::factory()->create();

    UsageRecord::query()->create([
        'tenant_id' => $other->id,
        'event' => UsageEvent::Generate->value,
        'units' => 1,
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertInertia(fn ($page) => $page->has('ledger', 0));
});

it('يُظهر التعليق في شاشة الاشتراك', function (): void {
    $this->tenant->forceFill(['status' => Tenant::SUSPENDED])->save();

    $this->actingAs($this->user)
        ->get(route('settings.billing'))
        ->assertInertia(fn ($page) => $page->where('suspended', true));
});

/**
 * ★ «**لا تظهر كلمة توكن في أيّ شاشة عميل**» — معيار القبول الثالث.
 *
 * ويُفحص على نصوص الشاشة كلّها لا على ما ظهر في اختبارٍ بعينه: كلمةٌ
 * تُضاف بعد شهر لا يمرّ عليها أحد.
 */
it('لا يذكر توكناً ولا نموذجاً في نصوص شاشة الاشتراك', function (): void {
    $flat = json_encode(Lang::get('billing'), JSON_UNESCAPED_UNICODE);

    expect($flat)->not->toContain('توكن')
        ->and($flat)->not->toContain('token')
        ->and($flat)->not->toContain('نموذج');
});
