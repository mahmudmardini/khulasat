<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Enums\AuditAction;
use App\Enums\UnverifiedPolicy;
use App\Models\AuditEvent;
use App\Models\Lecture;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Lang;

/*
 * لوحة المشرف — المواصفة §10، وSCREENS.md القسم الثالث، والمهمّة T-21.
 *
 * **والمقيس هنا ثلاثة، وكلّها تُخرَق صامتةً:**
 *
 *   ١. **البابُ نفسه.** حارسٌ ومسارٌ بلا بابٍ يُدخَل منه لوحةٌ لا يبلغها
 *      أحد — وهي حال اللوحة قبل هذه المهمّة.
 *   ٢. **الأثر.** ورفعُ حصّةِ جهةٍ بعد تحويلٍ بنكيّ حدثٌ ماليّ، وتغييرُ
 *      نموذجٍ يغيّر الجودة والكلفة. فعلٌ بلا أثرٍ لا يُراجَع بعد أشهر.
 *   ٣. **الانتحال.** أخطرُ صلاحيةٍ في النظام: المشرف يرى بيانات جهةٍ
 *      بعينها. «تُراقَب لا تُمنع» — فالمراقبة هي الشرط، لا التسهيل.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create([
        'email' => 'admin@khulasah.app',
        'name' => 'المشرف العام',
    ]);

    /*
     * الحدود الخمسة **تُثبَّت هنا كلُّها** لا تُترك للمصنع: الاختبار يقيس
     * الفرقَ بين ما كان وما صار، فبدايةٌ مجهولة تجعله يقيس فرقاً لم يُقصد.
     */
    $this->tenant = Tenant::factory()->create([
        'name_ar' => 'جهة الاختبار',
        'slug' => 'tenant-a',
        'monthly_quota' => 3,
        'daily_cap' => 3,
        'max_lecture_minutes' => 90,
        'transcription_minutes_quota' => 0,
        'regenerations_per_summary' => 2,
    ]);
});

/** مهمّةٌ في جهةٍ بعينها — ولا تُستعار من ملفّ اختبارٍ آخر فتنكسر بالترشيح. */
function jobIn(Tenant $tenant): SummaryJob
{
    return SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $tenant->id])->id,
        'state' => JobState::Published->value,
    ]);
}

/*
 * ─── ١. الباب ────────────────────────────────────────────────────────
 */

/**
 * ★ **الثغرة التي أقفلت اللوحة.**
 *
 * `Auth::attempt` تُصادق على الحارس الافتراضي `web`، ثمّ يُحوَّل المشرف
 * إلى `/admin` وهو يشترط حارس `admin` — فيردّ 404. واللوحة مبنيّةٌ
 * محروسةٌ لا يبلغها أحد، وهي عين ثغرة T-16ب.
 */
it('lets a super admin in through the one login form and lands them in the panel', function (): void {
    $this->post('/panel/login', ['email' => 'admin@khulasah.app', 'password' => 'password'])
        ->assertRedirect('/admin');

    expect(auth('admin')->check())->toBeTrue();

    $this->get('/admin')->assertOk();
});

/** ولا يُصادَق المشرف على حارس الجهة، وإلّا رأى شاشاتها بلا جهة. */
it('does not sign a super admin into the tenant guard', function (): void {
    $this->post('/panel/login', ['email' => 'admin@khulasah.app', 'password' => 'password']);

    expect(auth('web')->check())->toBeFalse();
});

it('still lands a tenant user on their own index, not the panel', function (): void {
    $owner = User::factory()->for_($this->tenant)->owner()->create(['email' => 'owner@tenant-a.test']);

    $this->post('/panel/login', ['email' => $owner->email, 'password' => 'password'])
        ->assertRedirect('/panel');

    expect(auth('web')->check())->toBeTrue()
        ->and(auth('admin')->check())->toBeFalse();
});

it('signs the admin out of the admin guard', function (): void {
    $this->actingAs($this->admin, 'admin')->post('/panel/logout')->assertRedirect('/panel/login');

    expect(auth('admin')->check())->toBeFalse();
});

/*
 * ─── ٢. الجهات والحدود ───────────────────────────────────────────────
 */

it('lists the tenants with their plan and limits', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin/tenants')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Tenants/Index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name_ar', 'جهة الاختبار'));
});

/**
 * ★ **رفعُ الحصّة تفعيلُ اشتراك، لا ضبطُ إعداد.**
 *
 * لا بوّابة دفع في هذه المرحلة — قرار مالك المنتج 7 أيلول 2026 — والجهة
 * تحوّل إلى الحساب البنكي. فهذه الشاشة **هي كامل آلية التفعيل**، والصفّ
 * في السجلّ هو الرابط الوحيد بين المال المستلَم والحصّة المرفوعة.
 */
it('records who raised a limit, from what to what, and why', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/limits", [
            'monthly_quota' => 40,
            'daily_cap' => 5,
            'max_lecture_minutes' => 90,
            'transcription_minutes_quota' => 0,
            'regenerations_per_summary' => 2,
            'note' => 'حوالة بنكية 12/رجب — 4500 ليرة، مرجع TR-8891',
        ])
        ->assertRedirect();

    expect($this->tenant->refresh()->monthly_quota)->toBe(40);

    $event = AuditEvent::query()->where('action', AuditAction::TenantLimits->value)->sole();

    expect($event->admin_email)->toBe('admin@khulasah.app')
        ->and($event->subject_label)->toBe('جهة الاختبار')
        ->and($event->note)->toContain('TR-8891')
        /*
         * **من أيّ قيمةٍ إلى أيّ قيمة** — لا القيمةَ الجديدة وحدها.
         *
         * و`toEqual` لا `toBe`: العمود `jsonb`، وهو **لا يحفظ ترتيب
         * المفاتيح** — يرتّبها بالطول ثمّ أبجدياً، فيعود `to` قبل `from`.
         * وترتيبُ مفاتيحَ في كائنٍ ليس معنى يُختبَر.
         */
        ->and($event->changes['monthly_quota'])->toEqual(['from' => 3, 'to' => 40])
        ->and($event->changes['daily_cap'])->toEqual(['from' => 3, 'to' => 5])
        // وما لم يتبدّل لا يُقيَّد، وإلّا أغرق الضجيجُ ما تحته.
        ->and($event->changes)->not->toHaveKey('max_lecture_minutes');
});

/** **ولا يُرفع حدٌّ بلا سبب مكتوب.** الحدث ماليّ، فسببُه من تمامه. */
it('refuses to change a limit without a written reason', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/limits", [
            'monthly_quota' => 40,
            'daily_cap' => 3,
            'max_lecture_minutes' => 90,
            'transcription_minutes_quota' => 0,
            'regenerations_per_summary' => 2,
        ])
        ->assertSessionHasErrors('note');

    expect($this->tenant->refresh()->monthly_quota)->toBe(3)
        ->and(AuditEvent::query()->count())->toBe(0);
});

/**
 * ★ **والنموذج يُرسل نصوصاً لا أعداداً.**
 *
 * وهذا ما يقع من المتصفّح فعلاً: حقلُ `number` في HTML يصل `"3"` لا `3`.
 * فمقارنةٌ صارمة تراه مخالفاً للعدد `3` في قاعدة البيانات، فتُقيّد تغييراً
 * لم يقع **وتطلب له سبباً** — فلا يُحفظ شيءٌ من الشاشة أصلاً بلا سببٍ
 * يُكتب لتغييرٍ وهميّ. والاختبارُ بأعدادٍ وحدها لا يكشف هذا.
 */
it('sees no change when the form posts the same numbers as strings', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/limits", [
            'monthly_quota' => '3',
            'daily_cap' => '3',
            'max_lecture_minutes' => '90',
            'transcription_minutes_quota' => '0',
            'regenerations_per_summary' => '2',
        ])
        ->assertSessionHasNoErrors();

    expect(AuditEvent::query()->count())->toBe(0);
});

/** وحفظٌ بلا تبديل ليس تعديلاً، فلا يُقيَّد ولا يُطلب له سبب. */
it('writes nothing when a save changes no limit', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/limits", [
            'monthly_quota' => 3,
            'daily_cap' => 3,
            'max_lecture_minutes' => 90,
            'transcription_minutes_quota' => 0,
            'regenerations_per_summary' => 2,
        ])
        ->assertSessionHasNoErrors();

    expect(AuditEvent::query()->count())->toBe(0);
});

it('records a suspension and lifts it again', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/status", [
            'status' => 'suspended',
            'note' => 'انقطع السداد شهرين',
        ])
        ->assertRedirect();

    expect($this->tenant->refresh()->status)->toBe('suspended');

    $event = AuditEvent::query()->where('action', AuditAction::TenantStatus->value)->sole();

    expect($event->changes['status'])->toEqual(['from' => 'active', 'to' => 'suspended']);
});

it('creates a tenant with its owner in one step', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/tenants', [
            'name_ar' => 'جهة أخرى',
            'slug' => 'tenant-b',
            'owner_name' => 'أبو بكر',
            'owner_email' => 'owner@tenant-b.test',
            'on_unverified' => 'review',
        ])
        ->assertRedirect();

    $created = Tenant::query()->where('slug', 'tenant-b')->sole();

    expect($created->on_unverified)->toBe(UnverifiedPolicy::Review);
    $owner = User::query()->withoutGlobalScopes()->where('email', 'owner@tenant-b.test')->sole();

    expect($owner->tenant_id)->toBe($created->id)
        ->and($owner->role->value)->toBe('owner')
        ->and(AuditEvent::query()->where('action', AuditAction::TenantCreated->value)->count())->toBe(1);
});

/**
 * **وضعُ البيان يُختار صراحةً** — T-163. فجهةٌ تُنشأ بلا اختيارٍ ظاهر تعمل
 * بوضعٍ لم يره أحد.
 */
it('requires an explicit verification mode when creating a tenant', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/tenants', [
            'name_ar' => 'جهة أخرى',
            'slug' => 'tenant-b',
            'owner_name' => 'أبو بكر',
            'owner_email' => 'owner@tenant-b.test',
        ])
        ->assertSessionHasErrors('on_unverified');

    $this->actingAs($this->admin, 'admin')
        ->post('/admin/tenants', [
            'name_ar' => 'جهة أخرى',
            'slug' => 'tenant-b',
            'owner_name' => 'أبو بكر',
            'owner_email' => 'owner@tenant-b.test',
            'on_unverified' => 'anything',
        ])
        ->assertSessionHasErrors('on_unverified');

    expect(Tenant::query()->where('slug', 'tenant-b')->exists())->toBeFalse();
});

it('switches the verification mode only with a written reason, and audits it', function (): void {
    expect($this->tenant->refresh()->on_unverified)->toBe(UnverifiedPolicy::Disclose);

    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/verification", ['on_unverified' => 'review', 'note' => ''])
        ->assertSessionHasErrors('note');

    expect($this->tenant->refresh()->on_unverified)->toBe(UnverifiedPolicy::Disclose);

    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/verification", [
            'on_unverified' => 'review',
            'note' => 'طلب المالك مراجعة كلّ حديثٍ قبل نشره',
        ])
        ->assertSessionHasNoErrors();

    $event = AuditEvent::query()->where('action', AuditAction::TenantVerification->value)->sole();

    expect($this->tenant->refresh()->on_unverified)->toBe(UnverifiedPolicy::Review)
        ->and($event->changes['on_unverified'])->toEqual(['from' => 'disclose', 'to' => 'review'])
        ->and(AuditAction::TenantVerification->isSensitive())->toBeTrue();
});

it('shows the verification mode on the tenant page', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get("/admin/tenants/{$this->tenant->id}")
        ->assertInertia(fn ($page) => $page->where('tenant.on_unverified', 'disclose'));
});

/**
 * **الرابط المنشور على جذر النطاق** (`/{tenant.slug}/…`، T-127) — فمن
 * اختار لفظاً يصادم مساراً قائماً (`panel`) يبتلع التطبيق أو يُبتلَع به.
 */
it('يرفض slug جهة يصادم مساراً محجوزاً', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->post('/admin/tenants', [
            'name_ar' => 'جهة أخرى',
            'slug' => 'panel',
            'owner_name' => 'أبو بكر',
            'owner_email' => 'owner@tenant-b.test',
        ])
        ->assertSessionHasErrors('slug');

    expect(Tenant::query()->where('slug', 'panel')->exists())->toBeFalse();
});

/*
 * ─── ٣. الانتحال ─────────────────────────────────────────────────────
 */

/** «صلاحية خطيرة تُراقَب لا تُمنع» — فالتقييد شرطُ إتاحتها. */
it('logs the start of an impersonation session', function (): void {
    $owner = User::factory()->for_($this->tenant)->owner()->create();

    $this->actingAs($this->admin, 'admin')
        ->post("/admin/tenants/{$this->tenant->id}/impersonate")
        ->assertRedirect('/panel');

    expect(auth('web')->id())->toBe($owner->id);

    $event = AuditEvent::query()->where('action', AuditAction::ImpersonationStarted->value)->sole();

    expect($event->subject_label)->toBe('جهة الاختبار')
        ->and($event->admin_email)->toBe('admin@khulasah.app');
});

/** والشريط التحذيري دائمٌ ما دامت الجلسة — وإلّا نُسي أنّها قائمة. */
it('shows a standing warning while impersonating', function (): void {
    User::factory()->for_($this->tenant)->owner()->create();

    $this->actingAs($this->admin, 'admin')->post("/admin/tenants/{$this->tenant->id}/impersonate");

    $this->get('/panel')->assertInertia(fn ($page) => $page->where('impersonating.tenant', 'جهة الاختبار'));
});

it('logs the end of the session and how long it lasted', function (): void {
    User::factory()->for_($this->tenant)->owner()->create();

    $this->actingAs($this->admin, 'admin')->post("/admin/tenants/{$this->tenant->id}/impersonate");

    $this->travel(7)->minutes();

    $this->post('/admin/impersonate/stop')->assertRedirect();

    $event = AuditEvent::query()->where('action', AuditAction::ImpersonationEnded->value)->sole();

    expect($event->changes['minutes']['to'])->toBe(7)
        ->and(auth('web')->check())->toBeFalse();
});

/** ولا ينتحل من ليس مشرفاً، ولو عرف المسار. */
it('refuses impersonation to a tenant user', function (): void {
    $owner = User::factory()->for_($this->tenant)->owner()->create();

    $this->actingAs($owner)->post("/admin/tenants/{$this->tenant->id}/impersonate")->assertNotFound();

    expect(AuditEvent::query()->count())->toBe(0);
});

/*
 * ─── ٤. النماذج ──────────────────────────────────────────────────────
 */

it('records a model change from one value to another', function (): void {
    $config = ModelConfig::query()->create([
        'stage' => 'cleaning',
        'provider' => 'anthropic',
        'model_id' => 'claude-haiku-4-5-20251001',
        'timeout_seconds' => 120,
        'max_retries' => 1,
        'on_exhausted' => 'fail',
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put("/admin/models/{$config->id}", [
            'provider' => 'anthropic',
            'model_id' => 'claude-sonnet-5',
            'max_tokens' => 4096,
            'thinking_level' => 'none',
            'timeout_seconds' => 180,
            'max_retries' => 1,
            'on_exhausted' => 'fail',
            'input_price_per_m' => '3.0000',
            'output_price_per_m' => '15.0000',
            'is_active' => true,
        ])
        ->assertRedirect();

    $event = AuditEvent::query()->where('action', AuditAction::ModelConfigUpdated->value)->sole();

    expect($event->changes['model_id'])
        ->toEqual(['from' => 'claude-haiku-4-5-20251001', 'to' => 'claude-sonnet-5'])
        ->and($event->changes['timeout_seconds']['to'])->toBe(180);
});

/*
 * ─── ٥. المهامّ ──────────────────────────────────────────────────────
 */

/** المشرف يرى مهامّ الجهات كلَّها — وإلّا لم تكن لوحةَ مشرف. */
it('shows jobs across every tenant, not one', function (): void {
    $other = Tenant::factory()->create(['slug' => 'other']);

    jobIn($this->tenant);
    jobIn($other);

    $this->actingAs($this->admin, 'admin')
        ->get('/admin/jobs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Jobs/Index')->has('jobs.data', 2));
});

it('keeps the panel shut to a tenant user at every door', function (string $path): void {
    $owner = User::factory()->for_($this->tenant)->owner()->create();

    // 404 لا 403: لا يُؤكَّد وجود اللوحة لمن لا يملكها.
    $this->actingAs($owner)->get($path)->assertNotFound();
})->with(['/admin', '/admin/tenants', '/admin/jobs', '/admin/models']);

/*
 * ─── ٦. النصوص ───────────────────────────────────────────────────────
 */

/**
 * ★ **كل فعلٍ في السجلّ له نصٌّ يُبلَغ بالنقاط.**
 *
 * وقيمة `AuditAction` فيها نقطة (`tenant.limits`)، وجسرُ النصوص في الواجهة
 * **يشقّ المفتاح عند كلّ نقطة** ويمشي في الشجرة. فمفتاحٌ اسمُه
 * `'tenant.limits'` حرفاً موجودٌ في الملفّ ولا يُبلَغ أبداً — ويظهر خاماً
 * في الشاشة. وهذا كسرٌ صامت: الاختبارات خضراء، والسجلّ يقول
 * `admin.audit.actions.tenant.limits` لمن يقرؤه.
 */
it('translates every audit action through a dotted path', function (AuditAction $action): void {
    $key = "admin.audit.actions.{$action->value}";

    expect(Lang::has($key))->toBeTrue("ينقص {$key}");

    // ولا يكفي وجودُه: المطلوب أن يكون نصّاً في شجرةٍ لا مفتاحاً فيه نقطة.
    expect(data_get(trans('admin.audit.actions'), $action->value))->toBeString();
})->with(AuditAction::cases());
