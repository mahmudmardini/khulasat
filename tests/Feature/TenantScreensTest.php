<?php

declare(strict_types=1);

use App\Actions\Summary\TransitionJob;
use App\Actions\Usage\RecordUsage;
use App\Domain\Summary\JobState;
use App\Enums\UsageEvent;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ui\JobProgress;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * شاشات الجهة — SCREENS.md §٢ و§٣ و§٤، والمهمّة T-16.
 *
 * والمقياس الحاكم هنا معيارُ القبول الثاني: **الفحص المسبق يظهر قبل أيّ صرف
 * موارد**. فالرفض بعد الصرف يعني أنّ المال ذهب، والرسالة بعده تُغضب من
 * انتظر ثمّ عرف.
 */

beforeEach(function (): void {
    Process::preventStrayProcesses();

    $this->tenant = Tenant::factory()->create(['monthly_quota' => 20, 'max_lecture_minutes' => 90]);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

function jobFor(Tenant $tenant, JobState $state, array $lecture = []): SummaryJob
{
    return SummaryJob::factory()
        ->inState($state)
        ->create([
            'tenant_id' => $tenant->id,
            'lecture_id' => Lecture::factory()->create(['tenant_id' => $tenant->id, ...$lecture])->id,
        ]);
}

// ── الحاجز: لا شاشة بلا مستخدم ──────────────────────────────────

it('sends a guest to the login page instead of showing another tenant everything', function (string $path): void {
    // الحاجز في `BelongsToTenant` يقرأ من `TenantContext`، وهو يُملأ من
    // المستخدم. فطلبٌ بلا مستخدم **يرى كلّ الجهات لا شيئاً منها**.
    $this->get($path)->assertRedirect('/panel/login');
})->with(['/panel', '/panel/lectures/create']);

// ── ٢. الفهرس ───────────────────────────────────────────────────

it('puts the summary that needs a decision above everything else', function (): void {
    // «الترتيب الافتراضي: needs_review أولاً، ثم الأحدث» — §2. وهي الحالة
    // الوحيدة التي تطلب فعلاً من المستخدم، فدفنُها زمنياً يُفقدها غرضها.
    $old = jobFor($this->tenant, JobState::Published, ['title_ar' => 'الأقدم']);
    $newest = jobFor($this->tenant, JobState::Queued, ['title_ar' => 'الأحدث']);
    $waiting = jobFor($this->tenant, JobState::NeedsReview, ['title_ar' => 'ينتظرك']);

    $this->actingAs($this->user)->get('/panel')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Lectures/Index')
            ->where('jobs.data.0.id', $waiting->id)
            ->where('jobs.data.0.needs_review', true)
            ->where('jobs.data.1.id', $newest->id)
            ->where('jobs.data.2.id', $old->id)
        );
});

it('never shows one tenant the summaries of another', function (): void {
    $other = Tenant::factory()->create();
    jobFor($other, JobState::Published, ['title_ar' => 'درس جهة أخرى']);
    jobFor($this->tenant, JobState::Published, ['title_ar' => 'درسنا']);

    $this->actingAs($this->user)->get('/panel')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('jobs.total', 1)
            ->where('jobs.data.0.title', 'درسنا')
        );
});

it('tells an empty index apart from an empty filter', function (): void {
    // «كل حالة فارغة لها رسالة وفعل» — §القواعد العامّة. ورسالةٌ واحدة
    // للحالين تُضلّل إحداهما: الأولى تُلغى تصفيتها، والثانية تُنشئ ملخّصاً.
    $this->actingAs($this->user)->get('/panel')
        ->assertInertia(fn (Assert $page): Assert => $page->where('has_any', false));

    jobFor($this->tenant, JobState::Published);

    $this->actingAs($this->user)->get('/panel/?search='.urlencode('لا يوجد كذا'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('has_any', true)
            ->where('jobs.total', 0)
        );
});

it('filters by status, speaker and search', function (): void {
    jobFor($this->tenant, JobState::NeedsReview, ['title_ar' => 'عنوان أوّل', 'speaker_name' => 'ملقٍ أوّل']);
    jobFor($this->tenant, JobState::Published, ['title_ar' => 'عنوان ثانٍ', 'speaker_name' => 'ملقٍ ثانٍ']);

    $this->actingAs($this->user)->get('/panel/?status=needs_review')
        ->assertInertia(fn (Assert $page): Assert => $page->where('jobs.total', 1)
            ->where('jobs.data.0.title', 'عنوان أوّل'));

    $this->actingAs($this->user)->get('/panel/?speaker='.urlencode('ملقٍ ثانٍ'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('jobs.total', 1)
            ->where('jobs.data.0.title', 'عنوان ثانٍ'));

    $this->actingAs($this->user)->get('/panel/?search='.urlencode('ثان'))
        ->assertInertia(fn (Assert $page): Assert => $page->where('jobs.total', 1));
});

it('shows the quota bar with what the ledger says, not a row count', function (): void {
    // «لا تُحسب الحصص من عدّ الصفوف في summary_jobs» — المواصفة §4.
    jobFor($this->tenant, JobState::Published);
    jobFor($this->tenant, JobState::Published);

    $this->actingAs($this->user)->get('/panel')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('quota.limit', 20)
            ->where('quota.used', 0)
        );
});

// ── ٣. الإنشاء ──────────────────────────────────────────────────

it('shows the locked outputs rather than hiding them', function (): void {
    // «تظهران معطَّلتين مع سطر ترقية، لا مخفيّتين» — §3-ب. «رؤية ما لا
    // تملكه دافع للترقية، وإخفاؤه يمنع معرفته».
    $this->actingAs($this->user)->get('/panel/lectures/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Lectures/Create')
            ->where('rich_outputs', false)
            ->where('limits.max_lecture_minutes', 90)
            ->has('venue_modes', 3)
        );
});

it('creates the lecture and its job, and lands on the follow screen', function (): void {
    // الإنشاء يُرسل الخطّ إلى الطابور منذ T-11ب، و`sync` في الاختبارات
    // يجريه كلَّه فوراً. وهذا اختبارُ شاشةٍ لا اختبارُ خطّ، فيُزيَّف الطابور.
    Queue::fake();

    $response = $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'عنوان مُدخَل',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
    ]);

    $job = SummaryJob::query()->firstOrFail();

    $response->assertRedirect(route('jobs.show', $job));

    expect($job->state)->toBe(JobState::Queued)
        ->and($job->lecture->title_ar)->toBe('عنوان مُدخَل')
        ->and($job->lecture->tenant_id)->toBe($this->tenant->id);
});

it('refuses a url the SSRF guard rejects, before any process runs', function (): void {
    // ★ **المدخل من المستخدم لا يُوثَق به** — §12 المخطر الثالث. وقاعدة
    //   `url` في Laravel تقبل `http://169.254.169.254`، وهو عنوان بيانات
    //   السحابة. والحارس يردّه، **ولا تُشغَّل عملية خارجية أصلاً**.
    $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'url',
        'source_url' => 'http://169.254.169.254/latest/meta-data',
        'title_ar' => 'عنوان',
        'speaker_name' => 'ملقٍ',
        'venue_mode' => 'institution',
    ])->assertSessionHasErrors('source_url');

    expect(SummaryJob::query()->count())->toBe(0);
});

it('stops a new summary when the monthly quota is spent, before it is queued', function (): void {
    // ★ CLAUDE.md §2 القاعدة الخامسة: الفحص **قبل** وضع المهمّة في الطابور.
    //   «الفحص بعد الوضع يعني أنّ التوكنز صُرفت».
    $this->tenant->update(['monthly_quota' => 1]);

    app(RecordUsage::class)->handle(
        tenant: $this->tenant,
        event: UsageEvent::Generate,
        units: 1,
    );

    $this->actingAs($this->user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => 'نصّ',
        'title_ar' => 'عنوان',
        'speaker_name' => 'ملقٍ',
        'venue_mode' => 'institution',
    ])->assertSessionHasErrors('quota');

    expect(SummaryJob::query()->count())->toBe(0);
});

// ── الفحص المسبق: قبل أيّ صرف موارد ────────────────────────────

it('refuses a preflight on a host outside the allowlist without running yt-dlp', function (): void {
    // `preventStrayProcesses` هو ما يُثبت الدعوى: لو نُودي yt-dlp لأخفق
    // الاختبار. فالرفض **قبل** النداء لا بعده.
    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', ['source_url' => 'https://vimeo.com/12345'])
        ->assertStatus(422)
        ->assertJson(['ok' => false, 'message' => trans('errors.transcript.host_not_allowed')]);
});

it('asks for a single lecture when given a playlist', function (): void {
    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', [
            'source_url' => 'https://www.youtube.com/playlist?list=PL1234',
        ])
        ->assertStatus(422)
        ->assertJson(['message' => trans('errors.transcript.playlist_given')]);
});

it('reports the duration, the captions, and whether the plan allows it', function (): void {
    Process::fake([
        '*' => Process::result(json_encode([
            'title' => 'عنوان مستخرج',
            'duration' => 3600,
            'language' => 'ar',
            'subtitles' => ['ar' => [['url' => 'https://example.test/ar.vtt', 'ext' => 'vtt']]],
        ], JSON_UNESCAPED_UNICODE)),
    ]);

    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', ['source_url' => 'https://www.youtube.com/watch?v=abc12345678'])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'title' => 'عنوان مستخرج',
            'duration_minutes' => 60,
            'has_arabic_captions' => true,
            'exceeds_limit' => false,
        ]);
});

it('says the lecture is too long here, not after the work has begun', function (): void {
    // ★ «وإن تجاوزت المدّة حدّ الاشتراك ظهرت الرسالة **هنا**، لا بعد البدء»
    //   — §3-أ. ويُحسم في الخادم لا في المتصفّح، فلا يُبدَّل من أدوات المطوّر.
    $this->tenant->update(['max_lecture_minutes' => 30]);

    Process::fake([
        '*' => Process::result(json_encode(['title' => 'درس طويل', 'duration' => 7200])),
    ]);

    $this->actingAs($this->user)
        ->postJson('/panel/lectures/preflight', ['source_url' => 'https://youtu.be/abc12345678'])
        ->assertOk()
        ->assertJson([
            'duration_minutes' => 120,
            'limit_minutes' => 30,
            'exceeds_limit' => true,
        ]);
});

// ── ٤. المتابعة ─────────────────────────────────────────────────

it('shows named steps and no invented percentage', function (): void {
    // «بلا نسب مئوية. النسبة المخترعة تكذب، والمستخدم يكتشف كذبها» — §4.
    $job = jobFor($this->tenant, JobState::ExtractingEvidence);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Jobs/Show')
            ->has('job.steps', 8)
            ->where('job.steps.3.key', 'extracting')
            ->where('job.steps.3.state', 'active')
            ->where('job.steps.2.state', 'done')
            ->where('job.steps.4.state', 'pending')
            ->where('job.settled', false)
            ->missing('job.percent')
        );
});

it('says a job is waiting on the reader, not working', function (): void {
    // «بانتظارك» غير «جارية»: الجارية تعمل من نفسها، وهذه لا تتحرّك حتى
    // يفعل المستخدم شيئاً.
    $job = jobFor($this->tenant, JobState::NeedsReview);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.steps.5.key', 'review')
            ->where('job.steps.5.state', 'awaiting')
            ->where('job.needs_review', true)
        );
});

it('marks the step that stalled, not the last one in the row', function (): void {
    // الحالة `failed` لا تقول أين وقعت، فتُقرأ من آخر انتقال. ولو عُرضت
    // في آخر الصفّ لظهرت المراحل كلّها «اكتملت» ثمّ توقّف المجهول.
    $job = jobFor($this->tenant, JobState::Queued);
    $transition = app(TransitionJob::class);
    $transition->handle($job, JobState::Transcribing);
    // الرمز يُمرَّر مع الانتقال لا يُكتب قبله: `TransitionJob` يكتب ما يُعطى
    // ويمحو ما لا يُعطى، وهو الصواب — الرمز خبرُ الانتقال لا حالٌ سابقة له.
    $transition->handle($job, JobState::Failed, errorCode: 'video_private');

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.steps.0.key', 'transcribing')
            ->where('job.steps.0.state', 'failed')
            ->where('job.error', trans('errors.transcript.video_private'))
            ->where('job.settled', true)
            // مصدرُ الدرس هو العلّة فعلاً هنا، فتبديلُه فعلٌ صحيح — T-91.
            ->where('job.error_offers_change_source', true)
        );
});

it('gives an unknown failure a sentence, never a code', function (): void {
    // «كل خطأ يذكر ما حدث ولماذا وما الإجراء. **لا كود خطأ**» — §القواعد العامّة.
    $job = jobFor($this->tenant, JobState::Failed);
    $job->update(['error_code' => 'something_we_never_named']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.error', trans('jobs.follow.failed_fallback'))
            ->where('job.error_offers_change_source', false)
        );

    expect((string) $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")->getContent())
        ->not->toContain('something_we_never_named');
});

/*
 * ══ T-91 — تعطّلٌ لا علاقة له بمصدر الدرس، فلا رسالةَ «غيّرْ المصدر» ══
 *
 * وُجد فعلياً على المهمّتين ٤٠ (حصّة مزوّدٍ منتهية) و٤٣ (عاملٌ بكودٍ قديم
 * بعد نشر — T-70): كلتاهما عرضت «غيّرْ مصدر الدرس» على عطلٍ لا صلة له بالمصدر.
 */
it('gives an honest message for a model-provider outage, and offers no source change', function (): void {
    $job = jobFor($this->tenant, JobState::Failed);
    $job->update(['error_code' => 'rate_limited']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.error', trans('errors.pipeline.rate_limited'))
            ->where('job.error_offers_change_source', false)
        );

    // والرسالة لا تُلقي اللوم على مصدر الدرس فيما لم يكن هو العلّة.
    expect(trans('errors.pipeline.rate_limited'))->toContain('لا علاقة لمصدر درسكم');
});

it('gives an honest message for an internal pipeline fault, and offers no source change', function (): void {
    // `pipeline_failed` هو ما يلتقط عاملاً بكودٍ قديم بعد نشر — T-70.
    $job = jobFor($this->tenant, JobState::Failed);
    $job->update(['error_code' => 'pipeline_failed']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.error', trans('errors.pipeline.pipeline_failed'))
            ->where('job.error_offers_change_source', false)
        );
});

it('answers the three-second poll with the state alone', function (): void {
    $job = jobFor($this->tenant, JobState::Cleaning);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertOk()
        ->assertJsonPath('job.state', 'cleaning')
        ->assertJsonPath('job.settled', false)
        ->assertJsonCount(8, 'job.steps');
});

it('never lets one tenant follow another tenant job', function (): void {
    $other = Tenant::factory()->create();
    $foreign = jobFor($other, JobState::Queued);

    $this->actingAs($this->user)->get("/panel/jobs/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->user)->getJson("/panel/jobs/{$foreign->id}/status")->assertNotFound();
});

// ── إعادة المحاولة: قرار إنسان محسوبٌ من الحصّة ────────────────

it('retries a stalled job as a fresh one, counted against the tenant allowance', function (): void {
    // الحالة `failed` نهائية ولا انتقال منها (§5)، فالإعادة **مهمّة جديدة**
    // ويبقى سجلّ الانتقالات صادقاً: ما وقع قد وقع.
    Queue::fake();

    $job = jobFor($this->tenant, JobState::Failed, ['title_ar' => 'الدرس المتوقّف']);
    $job->update(['transcript_text' => 'نصّ ملصوق']);

    $this->actingAs($this->user)->post("/panel/jobs/{$job->id}/retry")->assertRedirect();

    $replacement = SummaryJob::query()->where('id', '!=', $job->id)->firstOrFail();

    expect($replacement->state)->toBe(JobState::Queued)
        ->and($replacement->lecture_id)->toBe($job->lecture_id)
        // النصّ الملصوق يُنقل، فلا يُطلب من المستخدم لصقُه ثانيةً.
        ->and($replacement->transcript_text)->toBe('نصّ ملصوق')
        ->and($job->fresh()->regeneration_count)->toBe(1);
});

// ★ T-93 — الزرّ نفسه يستأنف من آخر مرحلةٍ ناجحة حين لها سجلّ حقيقيّ،
// لا من الصفر دائماً كما كان: المنطق مشتركٌ مع `AdminJobController::retry()`.
it('retries a job that failed mid-pipeline from where it stalled, not from the start', function (): void {
    Queue::fake();

    $job = jobFor($this->tenant, JobState::Queued);
    $transition = app(TransitionJob::class);
    $transition->handle($job, JobState::Transcribing);
    $transition->handle($job, JobState::Cleaning);
    $transition->handle($job, JobState::ExtractingStructure);
    $job->forceFill(['transcript_text' => 'نصٌّ منظَّف بعد التنقية'])->save();
    $transition->handle($job, JobState::Failed, errorCode: 'rate_limited');

    $this->actingAs($this->user)->post("/panel/jobs/{$job->id}/retry")->assertRedirect();

    $replacement = SummaryJob::query()->where('id', '!=', $job->id)->firstOrFail();

    expect($replacement->state)->toBe(JobState::ExtractingStructure)
        ->and($replacement->transcript_text)->toBe('نصٌّ منظَّف بعد التنقية')
        ->and($job->fresh()->regeneration_count)->toBe(1);
});

it('refuses a retry past the tenant regeneration limit', function (): void {
    $this->tenant->update(['regenerations_per_summary' => 0]);
    $job = jobFor($this->tenant, JobState::Failed);

    $this->actingAs($this->user)->post("/panel/jobs/{$job->id}/retry")->assertSessionHasErrors('retry');

    expect(SummaryJob::query()->count())->toBe(1);
});

// ── الإلغاء: `cancelled` مسموحة من كلّ حالةٍ غير نهائية (§5) ────

it('cancels a job stuck mid-pipeline', function (): void {
    $job = jobFor($this->tenant, JobState::Cleaning);

    $this->actingAs($this->user)->post("/panel/jobs/{$job->id}/cancel")->assertRedirect();

    expect($job->fresh()->state)->toBe(JobState::Cancelled);
});

it('refuses to cancel a job that already settled', function (): void {
    $job = jobFor($this->tenant, JobState::Published);

    $this->actingAs($this->user)->post("/panel/jobs/{$job->id}/cancel")->assertSessionHasErrors('cancel');

    expect($job->fresh()->state)->toBe(JobState::Published);
});

it('never lets one tenant cancel another tenant job', function (): void {
    $other = Tenant::factory()->create();
    $foreign = jobFor($other, JobState::Cleaning);

    $this->actingAs($this->user)->post("/panel/jobs/{$foreign->id}/cancel")->assertNotFound();

    expect($foreign->fresh()->state)->toBe(JobState::Cleaning);
});

// ── الترجمة من آلة الحالات إلى لغة الشاشة ──────────────────────

it('reads eight machine states as one badge', function (): void {
    // «قيد الإعداد» تجمع ثمانياً: التمييز بينها يعني شيئاً لنا ولا يعني
    // شيئاً لمن ينتظر ملخّصه.
    foreach ([JobState::Queued, JobState::Transcribing, JobState::Cleaning, JobState::Verifying] as $state) {
        expect(trans('jobs.status.'.JobProgress::badge($state)))->toBe('قيد الإعداد');
    }

    expect(JobProgress::badge(JobState::NeedsReview))->toBe('needs_review')
        ->and(JobProgress::badge(JobState::Published))->toBe('published')
        ->and(JobProgress::badge(JobState::Failed))->toBe('failed')
        // الإلغاء قرارٌ لا عطل، فلا يُعرض «متوقّف».
        ->and(JobProgress::badge(JobState::Cancelled))->toBe('unpublished');
});

it('names every step and state it hands the interface', function (): void {
    // مفتاحٌ بلا نصّ يظهر في الشاشة مفتاحاً — SCREENS.md §القواعد العامّة.
    $job = jobFor($this->tenant, JobState::Writing);

    foreach (JobProgress::steps($job) as $step) {
        expect(trans("jobs.steps.{$step['key']}"))->not->toBe("jobs.steps.{$step['key']}")
            ->and(trans("jobs.step_state.{$step['state']}"))->not->toBe("jobs.step_state.{$step['state']}");
    }
});
