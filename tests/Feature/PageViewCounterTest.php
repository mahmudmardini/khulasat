<?php

declare(strict_types=1);

use App\Actions\Analytics\RecordPageView;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Lecture;
use App\Models\PageView;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * عدّاد فتحات الصفحات المنشورة — SCREENS.md §7، والمهمّة T-31.
 *
 * ★ **والحدّ الحاكم هنا واحد:** قالب الملخّص **لا يُعدَّل منه CSS ولا
 * JavaScript** — CLAUDE.md §2 القاعدة الأولى. فالعدّ بصورةٍ بحجم بكسل،
 * وهي بنيةٌ محضة؛ ونقطةُ عدٍّ تُكتب بـ`fetch` تُخالف القاعدة نصّاً.
 *
 * **والثاني:** لا يُعرف عن القارئ شيء. لا كوكي، ولا بصمة، ولا عنوان شبكة
 * يُحفظ — **والمعدود صفحةٌ لا إنسان**.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://app.khulasah.test');

    Queue::fake();

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'plan' => 'business']);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
    ]);
});

function countedJob(): SummaryJob
{
    $job = SummaryJob::factory()->create([
        'tenant_id' => test()->tenant->id,
        'lecture_id' => test()->lecture->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    test()->actingAs(test()->user)->post("/panel/summaries/{$job->id}")->assertRedirect();

    return $job->refresh();
}

// ── الحدّ: صورةٌ لا شيفرة ────────────────────────────────────────

/**
 * ★ **الاختبار الحاكم في هذا الملفّ.**
 *
 * فلو كُتب العدّ بـ`fetch` لخالف CLAUDE.md §2 القاعدة الأولى نصّاً، **ولمرّ
 * في المراجعة البصرية** — الصفحة تبدو كما هي. فيُفحص آلياً.
 */
it('يعدّ بصورة لا بشيفرة، فلا يمسّ JavaScript القالب', function (): void {
    $job = countedJob();

    $html = Storage::disk('public')->get($job->outputs()->where('type', 'page')->value('storage_path'));

    // **واللسانُ في المسار منذ T-140**: بلا هذا تطلب صفحاتُ اللغات شاهدةً
    // واحدة، فيُجمع الثلاثةُ في صفٍّ ولا يُعرف أيُّ لسانٍ قُرئ.
    expect($html)->toContain("https://app.khulasah.test/v/{$job->id}/page/ar.gif")
        // **داخل `hidden`**: سمةٌ في HTML لا قاعدةٌ في ورقة الأنماط، ولا
        // بكسل يتزحزح بها. ومن غيرها يفتح الوسمُ سطراً في آخر الصفحة.
        ->toMatch('/<div hidden><img src="[^"]+\.gif" alt="" width="1" height="1"><\/div>/');

    // ولا حرف شيفرة أُضيف: لا `fetch` ولا `navigator.sendBeacon`.
    expect($html)->not->toContain('sendBeacon')
        ->and($html)->not->toContain("fetch('https://app.khulasah.test");
});

/** والقالب نفسه لم يُمَسّ منه CSS ولا JavaScript. */
it('لا يعدّل ورقة أنماط القالب ولا شيفرته', function (): void {
    $style = (string) file_get_contents(resource_path('views/summary/partials/_style.blade.php'));
    $script = (string) file_get_contents(resource_path('views/summary/partials/_script.blade.php'));

    foreach (['/v/', 'beacon', 'page-view', 'sendBeacon'] as $trace) {
        expect($style)->not->toContain($trace)
            ->and($script)->not->toContain($trace);
    }
});

// ── العدّ ────────────────────────────────────────────────────────

it('يزيد العدّاد مع كلّ فتحة، ويردّ بكسلاً لا صفحة', function (): void {
    $job = countedJob();

    foreach (range(1, 3) as $ignored) {
        $this->get("/v/{$job->id}/page.gif")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif')
            // **بلا كاش**: صورةٌ محفوظة في المتصفّح لا تُطلب، فلا تُعدّ.
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    expect(PageView::query()->where('summary_job_id', $job->id)->sum('views'))->toBe(3);
});

it('يعدّ بلا تسجيل دخول، فالقارئ ليس زبوناً', function (): void {
    $job = countedJob();

    // ولا جلسة ولا مستخدم — والمسار خارج حاجز `auth` عمداً.
    $this->get("/v/{$job->id}/page.gif")->assertOk();

    expect(PageView::query()->where('summary_job_id', $job->id)->exists())->toBeTrue();
});

// الشرائحُ لا تُنشر بعد T-204، وشاهدتُها في نسخةٍ قديمةٍ محفوظة لا تُعدّ. والبكسلُ يُردّ.
it('لا يعدّ فتحات الشرائح ولا حزمة الصور', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page.gif")->assertOk();
    $this->get("/v/{$job->id}/carousel.gif")->assertOk()->assertHeader('Content-Type', 'image/gif');
    $this->get("/v/{$job->id}/image_set/ar.gif")->assertOk();

    $counts = PageView::query()
        ->where('summary_job_id', $job->id)
        ->pluck('views', 'output_type');

    expect($counts->keys()->all())->toBe([OutputType::Page->value])
        ->and((int) $counts[OutputType::Page->value])->toBe(1);
});

/**
 * ★ **ولا يُحفظ عن القارئ شيء** — معيار القبول الثاني.
 *
 * فالصفّ مجموعٌ يوميّ: **لا عنوان شبكة، ولا كوكي، ولا طابعٌ زمنيّ دقيق.**
 * والطابع الدقيق وحده — مع عنوان الصفحة — أثرٌ يقارب سلوك القارئ.
 */
it('لا يحفظ عن القارئ شيئاً يُعرّفه', function (): void {
    $job = countedJob();

    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 Something'])
        ->get("/v/{$job->id}/page.gif")
        ->assertOk();

    $row = PageView::query()->where('summary_job_id', $job->id)->first();

    /*
     * **و`locale` لسانُ الصفحة لا لسانُ القارئ** (T-140): تُقرأ من المسار
     * الذي كتبه الرسمُ، لا من `Accept-Language` ولا من عنوانٍ يُرسله
     * المتصفّح — فلا تُعرِّف من فتح.
     */
    /*
     * **والمقيسُ أيُّ الأعمدة لا ترتيبُها**: `->after()` لا أثرَ له في
     * Postgres، فالعمودُ المضاف يقع آخراً — وترتيبٌ يُثبَّت هنا يُخفق يومَ
     * يُضاف عمودٌ لعلّةٍ لا علاقةَ لها بما تحرسه هذه.
     */
    $columns = array_keys($row->getAttributes());
    sort($columns);

    expect($columns)->toBe(['day', 'id', 'locale', 'output_type', 'summary_job_id', 'tenant_id', 'views']);
});

it('يجمع فتحات اليوم في صفٍّ واحد لا صفّاً لكل فتحة', function (): void {
    $job = countedJob();

    foreach (range(1, 25) as $ignored) {
        $this->get("/v/{$job->id}/page.gif")->assertOk();
    }

    expect(PageView::query()->where('summary_job_id', $job->id)->count())->toBe(1)
        ->and((int) PageView::query()->where('summary_job_id', $job->id)->value('views'))->toBe(25);
});

// ── اللسان: توزيعُ القراءات — T-140 ─────────────────────────────

/*
 * ★★ **بلاغُ مالك المنتج، ١٦ أيلول ٢٠٢٦**: «all languages views should be
 * calculated and should be accessable in the admin panel (total views, and
 * other languages views ceparated)».
 *
 * وكانت {@see Beacon} تبني الرابط من رقم المهمّة والنوع وحدهما، فصفحاتُ
 * العربية والإنجليزية والتركية تطلب `/v/49/page.gif` نفسَها: **المجموعُ
 * صحيح وتوزيعُه معدوم**.
 */

it('يعدّ كلَّ لسانٍ في صفِّه، ويبقي المجموعَ واحداً', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page/ar.gif")->assertOk();
    $this->get("/v/{$job->id}/page/en.gif")->assertOk();
    $this->get("/v/{$job->id}/page/en.gif")->assertOk();
    $this->get("/v/{$job->id}/page/tr.gif")->assertOk();

    $rows = PageView::query()->where('summary_job_id', $job->id)->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('locale', Locale::Ar)?->views)->toBe(1)
        ->and($rows->firstWhere('locale', Locale::En)?->views)->toBe(2)
        ->and($rows->firstWhere('locale', Locale::Tr)?->views)->toBe(1)
        // **والمجموعُ لا ينقص ولا يُحتسب مرّتين** — معيارُ قبولٍ في T-140.
        ->and((int) $rows->sum('views'))->toBe(4);
});

/*
 * ★★ **والمسارُ القديم يبقى، وما يكتبه لا يُنسَب إلى لسان.**
 *
 * فملفّاتٌ منشورةٌ قبل T-140 تحمل `/v/49/page.gif` على الأقراص **وفي أيدي
 * الناس**، ومنها ما شاركوه. فكسرُها يُفقد عدّاً صحيحاً، ونسبتُها إلى اللغة
 * الأولى تخمينٌ يُعرض رقماً.
 */
it('يقبل الصيغة القديمة ولا ينسب عدَّها إلى لسانٍ مخمَّن', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page.gif")->assertOk();

    $row = PageView::query()->where('summary_job_id', $job->id)->sole();

    expect($row->locale)->toBeNull();
});

/*
 * ★★★ **وهذا حارسُ صحّةٍ لا حارسُ عرض.**
 *
 * فـPostgres يعدّ `null` **مميَّزاً عن `null`** في المفاتيح الفريدة، فمفتاحٌ
 * على `locale` عارياً لا يجد الصفَّ الفارغ القائم — **فتصير كلُّ فتحةٍ من
 * ملفٍّ قديمٍ صفّاً جديداً**، وهو عينُ ما نصّت هجرةُ T-31 على منعه: «صفٌّ
 * لكلّ زيارة يعني جدولاً ينمو بلا حدّ». ولذلك المفتاحُ على `coalesce`.
 */
it('يجمع فتحات الصيغة القديمة في صفٍّ واحد، فلا ينمو الجدول بلا حدّ', function (): void {
    $job = countedJob();

    foreach (range(1, 12) as $ignored) {
        $this->get("/v/{$job->id}/page.gif")->assertOk();
    }

    $row = PageView::query()->where('summary_job_id', $job->id)->sole();

    expect($row->views)->toBe(12);
});

it('لا يكتب لساناً ليس في القائمة، ويردّ البكسل على كلّ حال', function (): void {
    $job = countedJob();

    // **ولا ٤٠٤**: المسارُ عامّ، وردُّ خطأٍ يجعله كاشفاً ويكسر صورةً في
    // صفحةٍ منشورةٍ باسم جهة (T-31).
    $this->get("/v/{$job->id}/page/zz.gif")->assertOk();

    expect(PageView::query()->where('summary_job_id', $job->id)->sole()->locale)->toBeNull();
});

it('يوزّع قراءات الملخّص على ألسنته في شاشة الجهة', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page/ar.gif")->assertOk();
    $this->get("/v/{$job->id}/page/en.gif")->assertOk();
    $this->get("/v/{$job->id}/page/en.gif")->assertOk();

    $views = $this->actingAs($this->user)
        ->get("/panel/summaries/{$job->id}")
        ->viewData('page')['props']['views'];

    // **والأكثرُ قراءةً أوّلاً**: الترتيبُ هو الجوابُ عن «أيُّ لسانٍ أنفع؟».
    expect($views['total'])->toBe(3)
        ->and($views['by_locale'][0]['locale'])->toBe('en')
        ->and($views['by_locale'][0]['total'])->toBe(2)
        ->and($views['by_locale'][1]['locale'])->toBe('ar')
        ->and($views['by_locale'][1]['total'])->toBe(1);
});

it('يوزّعها في لوحة المشرف كذلك، للمهمّة وللجهة', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $job = countedJob();

    $this->get("/v/{$job->id}/page/tr.gif")->assertOk();
    $this->get("/v/{$job->id}/page.gif")->assertOk();

    $props = $this->actingAs($admin, 'admin')
        ->get("/admin/jobs/{$job->id}")
        ->viewData('page')['props'];

    expect($props['job']['views'])->toBe(2)
        ->and($props['views_by_locale'])->toHaveCount(2);

    // **والفارغُ يُقرأ «غير مبيَّنة» لا «العربية»** — والنصُّ من lang/ar.
    $unattributed = collect($props['views_by_locale'])->firstWhere('locale', null);

    expect($unattributed['locale_label'])->toBe(trans('common.views.unattributed'));

    $usage = $this->actingAs($admin, 'admin')
        ->get("/admin/tenants/{$this->tenant->id}")
        ->viewData('page')['props']['usage'];

    expect($usage['views'])->toBe(2)
        ->and($usage['views_by_locale'])->toHaveCount(2);
});

// ── ما لا يُعدّ ──────────────────────────────────────────────────

/**
 * ★ **ومن عاين صفحته عشراً لا يرى «عشر زيارات» وما زارها أحد.**
 *
 * فالمعاينة (§6) تُرسم من المهمّة نفسها بالقالب نفسه، ولو حملت الشاهدة
 * لصار العدّاد مرآةً لصاحب الصفحة لا لقرّائها.
 */
it('لا يضع شاهدة في المعاينة ولا في الملفّ المنزَّل', function (): void {
    $job = countedJob();

    $preview = $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page")->assertOk();

    expect($preview->getContent())->not->toContain('/v/');

    $download = $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/page")->assertOk();

    expect($download->streamedContent())->not->toContain('/v/');
});

/**
 * **ولا يُصنع تاريخُ زياراتٍ لملخّصٍ لم يُنشر.**
 *
 * فالمسار عامٌّ ومفتاحُه رقمُ المهمّة، ومن أراد نفخ عدّادٍ استطاع. وحصرُه
 * في المنشور يُبقي ذلك على صفحةٍ يراها الناس أصلاً.
 */
it('لا يعدّ ملخّصاً لم يُنشر ولا ملخّصاً أُزيل', function (): void {
    $unpublished = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => $this->lecture->id,
    ]);

    $this->get("/v/{$unpublished->id}/page.gif")->assertOk();

    $live = countedJob();
    $this->actingAs($this->user)->delete("/panel/summaries/{$live->id}/publication")->assertRedirect();

    $this->get("/v/{$live->id}/page.gif")->assertOk();

    expect(PageView::query()->count())->toBe(0);
});

/**
 * **والبكسل يُردّ في كلّ حال** — ولو لم يوجد الملخّص.
 *
 * فردُّ ٤٠٤ يجعل المسار كاشفاً: من جرّب الأرقام عرف أيّها ملخّصٌ قائم.
 * **وهو أثرٌ يُرى كذلك**: صورةٌ مكسورة في صفحةٍ منشورة باسم جهة.
 */
it('يردّ البكسل ولو لم يوجد الملخّص، فلا يكشف ولا يكسر صورة', function (): void {
    $this->get('/v/999999/page.gif')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $this->get('/v/999999/nonsense.gif')->assertOk();

    expect(PageView::query()->count())->toBe(0);
});

it('لا يضع شاهدة حين يُطفأ العدّ', function (): void {
    config()->set('khulasah.analytics.enabled', false);

    $job = countedJob();

    $html = Storage::disk('public')->get($job->outputs()->where('type', 'page')->value('storage_path'));

    expect($html)->not->toContain('/v/');
});

// ── العرض ───────────────────────────────────────────────────────

it('يعرض العدد في شاشة الملخّص المنشور', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page.gif")->assertOk();
    $this->get("/v/{$job->id}/page.gif")->assertOk();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('views.total', 2)
            ->where('views.recent', 2)
            ->missing('views.by_output')
        );
});

/**
 * **والتراكميّ وحده يُخفي صفحةً مات عنها القرّاء منذ شهور.**
 */
it('يفصل مجموع آخر ثلاثين يوماً عن المجموع كلّه', function (): void {
    $job = countedJob();

    PageView::query()->create([
        'tenant_id' => $this->tenant->id,
        'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value,
        'day' => now()->subMonths(6)->toDateString(),
        'views' => 400,
    ]);

    $this->get("/v/{$job->id}/page.gif")->assertOk();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('views.total', 401)
            ->where('views.recent', 1)
        );
});

it('لا يُري جهةً عدّاد جهةٍ أخرى', function (): void {
    // **والغريب يُنشأ قبل أن يُملأ سياقُ الجهة**: `BelongsToTenant` يرفض
    // كتابة صفٍّ لجهةٍ غير جهة السياق، وهو حارسٌ مقصود لا عائق.
    $stranger = User::factory()->owner()->for_(Tenant::factory()->create())->create();

    $job = countedJob();

    $this->actingAs($stranger)->get("/panel/summaries/{$job->id}")->assertNotFound();
});

it('يمحو العدّاد مع الحذف النهائي', function (): void {
    $job = countedJob();

    $this->get("/v/{$job->id}/page.gif")->assertOk();

    expect(PageView::query()->count())->toBe(1);

    $this->actingAs($this->user)->delete("/panel/summaries/{$job->id}")->assertRedirect();

    // «يُمحى المتن والشواهد والمخرجات» (T-30) — وعدّادُ صفحةٍ ممحوّة معها.
    expect(PageView::query()->count())->toBe(0);
});

it('لا يعدّ الفعل شيئاً حين يُنادى على مهمّة غير منشورة', function (): void {
    $job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => $this->lecture->id,
    ]);

    app(RecordPageView::class)->handle($job, OutputType::Page);

    expect(PageView::query()->count())->toBe(0);
});

/*
 * ── لوحة المشرف تقرأ العدّ — T-136 ─────────────────────────────────────
 *
 * ★ **والعدُّ كان يُجمع ولا تعرفه اللوحة.** فلا متحكّمَ في `Admin/` يقرأ
 * `PageView` — والمشرفُ يقرّر الترقياتَ والحدودَ بلا أن يعرف أيُّ ملخّصٍ
 * قُرئ وأيُّه لم يُفتح. **وهذا سادسُ عطبٍ من صنفٍ واحد**: عملٌ يُنجَز
 * ويُدفع ثمنُه ثمّ لا يُعرَض.
 */

it('shows page views on the admin jobs index', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $job = SummaryJob::factory()->for($this->lecture)->create([
        'tenant_id' => $this->tenant->id,
        'state' => JobState::Published->value,
    ]);

    // يومان ومخرَجان: المعروضُ مجموعُهما لا آخرُ صفٍّ فيهما.
    PageView::query()->create([
        'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value, 'day' => now()->toDateString(), 'views' => 7,
    ]);
    PageView::query()->create([
        'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value, 'day' => now()->subDay()->toDateString(), 'views' => 5,
    ]);

    $row = $this->actingAs($admin, 'admin')->get('/admin/jobs')
        ->viewData('page')['props']['jobs']['data'][0];

    expect($row['views'])->toBe(12);
});

it('reads views as unmeasured, not zero, before a summary is published', function (): void {
    $admin = User::factory()->superAdmin()->create();

    SummaryJob::factory()->for($this->lecture)->create([
        'tenant_id' => $this->tenant->id,
        'state' => JobState::ExtractingStructure->value,
    ]);

    $row = $this->actingAs($admin, 'admin')->get('/admin/jobs')
        ->viewData('page')['props']['jobs']['data'][0];

    // **و`null` لا صفر**: صفرٌ يُقرأ «نُشرت ولم يقرأها أحد»، وهي لم تُنشر.
    expect($row['views'])->toBeNull();
});

it('keeps the query count flat however many rows the table holds', function (): void {
    $admin = User::factory()->superAdmin()->create();

    foreach (range(1, 6) as $i) {
        $job = SummaryJob::factory()->for($this->lecture)->create([
            'tenant_id' => $this->tenant->id,
            'state' => JobState::Published->value,
        ]);

        PageView::query()->create([
            'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
            'output_type' => OutputType::Page->value, 'day' => now()->toDateString(), 'views' => $i,
        ]);
    }

    /*
     * ★ **والمقيسُ ثباتُ العدد لا قيمتُه.** فرقمٌ مثبَّت يُكسَر بكلّ تعديلٍ
     * بريء في شاشةٍ أخرى، **وما يُراد منعُه استعلامٌ لكلّ صفّ** — وهو ما
     * يجعل العدد يتبع عددَ المهامّ. فيُقاس الفرقُ بين ستّة صفوف وواحد.
     */
    $count = function () use ($admin): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin, 'admin')->get('/admin/jobs')->assertOk();

        return count(DB::getQueryLog());
    };

    $withSix = $count();

    SummaryJob::query()->whereKeyNot(SummaryJob::query()->min('id'))->delete();

    expect($count())->toBe($withSix);
});

it('totals views on the dashboard, and the last thirty days apart', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $job = SummaryJob::factory()->for($this->lecture)->create([
        'tenant_id' => $this->tenant->id,
        'state' => JobState::Published->value,
    ]);

    PageView::query()->create([
        'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value, 'day' => now()->subDays(3)->toDateString(), 'views' => 9,
    ]);

    // أقدمُ من ثلاثين يوماً: يدخل المجموعَ ولا يدخل الحديث.
    PageView::query()->create([
        'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value, 'day' => now()->subDays(90)->toDateString(), 'views' => 40,
    ]);

    $views = $this->actingAs($admin, 'admin')->get('/admin')
        ->viewData('page')['props']['views'];

    expect($views['total'])->toBe(49)
        ->and($views['recent'])->toBe(9);
});

it('shows a tenant its own views only, on its admin card', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $job = SummaryJob::factory()->for($this->lecture)->create([
        'tenant_id' => $this->tenant->id,
        'state' => JobState::Published->value,
    ]);

    PageView::query()->create([
        'tenant_id' => $this->tenant->id, 'summary_job_id' => $job->id,
        'output_type' => OutputType::Page->value, 'day' => now()->toDateString(), 'views' => 4,
    ]);

    // جهةٌ أخرى ومشاهداتُها — ولا تُحتسب في بطاقة الأولى.
    $other = Tenant::factory()->create(['slug' => 'other']);
    $otherLecture = Lecture::factory()->create(['tenant_id' => $other->id]);
    $otherJob = SummaryJob::factory()->for($otherLecture)->create([
        'tenant_id' => $other->id,
        'state' => JobState::Published->value,
    ]);
    PageView::query()->create([
        'tenant_id' => $other->id, 'summary_job_id' => $otherJob->id,
        'output_type' => OutputType::Page->value, 'day' => now()->toDateString(), 'views' => 100,
    ]);

    $usage = $this->actingAs($admin, 'admin')->get("/admin/tenants/{$this->tenant->id}")
        ->viewData('page')['props']['usage'];

    expect($usage['views'])->toBe(4);
});
