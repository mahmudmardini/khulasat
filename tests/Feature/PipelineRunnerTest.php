<?php

declare(strict_types=1);

use App\Actions\Summary\TransitionJob;
use App\Actions\Usage\RecordUsage;
use App\Actions\Usage\RefundUnstartedSummary;
use App\Contracts\VerifierRegistry;
use App\Domain\Summary\JobState;
use App\Enums\ReviewStatus;
use App\Enums\TranscriptErrorCode;
use App\Enums\UsageEvent;
use App\Jobs\RunSummaryPipeline;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Quota\QuotaGuard;
use App\Services\Verification\HadithVerifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\FakeHadithProvider;

/*
 * T-11ب — المشغّل الذي يقود المهمّة من `queued` إلى `published`.
 *
 * **والمعيار الحاكم هنا تشغيلٌ كامل**، لا نداءُ الأفعال بيدٍ واحدةً بعد
 * واحدة: اختباراتُ T-11 تنادي المراحل بنفسها فتمرّ خضراء وهي لا تُثبت أنّ
 * شيئاً يجري الخطّ.
 */

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('public');

    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', 'https://cdn.khulasah.test');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
    ]);
});

function queuedJob(array $attributes = []): SummaryJob
{
    return SummaryJob::factory()->create([
        'tenant_id' => test()->tenant->id,
        'lecture_id' => test()->lecture->id,
        'state' => JobState::Queued->value,
        // النصّ ملصوق، فيُعتمد مصدراً ولا يُنادى yt-dlp — §5-أ-2 المسار ٥.
        // وهو أطول من حدّ §5-أ-7، وإلّا وقف الخطّ قبل أن يبلغ النماذج.
        'transcript_text' => str_repeat('كلمةٌ من نصّ التفريغ الخام ', 200),
        ...$attributes,
    ]);
}

function runPipeline(SummaryJob $job): SummaryJob
{
    (new RunSummaryPipeline((int) $job->id))->handle();

    return $job->refresh();
}

// ── الوقوف عند المراجعة ──────────────────────────────────────────

it('stops at needs_review and publishes nothing', function (): void {
    $job = queuedJob();

    // شاهدٌ معلّق يُدخله التحقّقُ في `needs_review` ويمنع تجاوزها.
    app(TransitionJob::class)->handle($job, JobState::Transcribing);
    app(TransitionJob::class)->handle($job, JobState::Cleaning);
    app(TransitionJob::class)->handle($job, JobState::ExtractingStructure);
    app(TransitionJob::class)->handle($job, JobState::ExtractingEvidence);
    app(TransitionJob::class)->handle($job, JobState::Verifying);
    app(TransitionJob::class)->handle($job, JobState::NeedsReview);

    EvidenceItem::factory()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $this->tenant->id,
        'review_status' => ReviewStatus::Pending->value,
    ]);

    $job = runPipeline($job);

    expect($job->state)->toBe(JobState::NeedsReview)
        ->and($job->outputs()->count())->toBe(0);
});

// ── الإخفاق ──────────────────────────────────────────────────────

it('writes the error code with the failing transition, not before it', function (): void {
    // لا نصّ ولا رابط: لا مصدر يُفرَّغ منه.
    $job = queuedJob(['transcript_text' => null]);
    $this->lecture->update(['source_url' => null]);

    $job = runPipeline($job);

    expect($job->state)->toBe(JobState::Failed)
        ->and($job->error_code)->not->toBeNull()
        ->and($job->error_detail)->not->toBeNull();

    $last = $job->transitions()->get()->last();

    expect($last->to_state)->toBe(JobState::Failed)
        ->and($last->error_code)->toBe($job->error_code);
});

/*
 * ★ T-213 — **القاعدةُ متعطّلة فالملخّصُ يقف، لا يُنشر بلا أحاديثه.** كان
 * المحقّقُ يقول عن كلّ حديثٍ «لم يُعثر عليه» فيُحذف، ويمضي الخطّ إلى النشر.
 */
it('halts at verifying with corpus_unavailable when the hadith corpus is down, and saves no evidence', function (): void {
    app()->when(HadithVerifier::class)->needs('$providers')
        ->give(fn (): array => [new FakeHadithProvider(failWith: 'انقطاع القاعدة', name: 'graded')]);
    app()->forgetInstance(VerifierRegistry::class);

    $job = queuedJob(['evidence_json' => [['kind' => 'hadith', 'raw_text' => 'أحب الأعمال إلى الله أدومها وإن قل']]]);

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure, JobState::ExtractingEvidence, JobState::Verifying] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $job = runPipeline($job);

    expect($job->state)->toBe(JobState::Failed)
        ->and($job->error_code)->toBe('corpus_unavailable')
        ->and($job->evidenceItems()->count())->toBe(0)
        ->and($job->outputs()->count())->toBe(0)
        ->and($job->transitions()->get()->last()->from_state)->toBe(JobState::Verifying);
});

/*
 * ★ T-222 — **وقفت عند نصّ الدرس فلم يُكتب ملخّص: تعود الوحدة.** كانت تُقيَّد
 * عند الإنشاء وتبقى، والرسالة تقول «لم نصرف من حصّتكم شيئاً».
 */
function chargedJob(array $attributes = []): SummaryJob
{
    $job = queuedJob($attributes);

    app(RecordUsage::class)->handle(test()->tenant, UsageEvent::Generate, units: 1, job: $job);

    return $job;
}

it('gives the unit back when the job stops at the transcript', function (): void {
    $job = runPipeline(chargedJob(['transcript_text' => str_repeat('كلمة ', 120)]));

    expect($job->state)->toBe(JobState::Failed)
        ->and($job->error_code)->toBe(TranscriptErrorCode::TranscriptTooShort->value)
        ->and(app(QuotaGuard::class)->monthlyQuota($this->tenant->refresh())->used)->toBe(0)
        // ومرّةً واحدة: إعادةُ الردّ لا تُنشئ رصيداً.
        ->and(app(RefundUnstartedSummary::class)->handle($job))->toBeNull()
        ->and(UsageRecord::query()->where('summary_job_id', $job->id)->sum('units'))->toBe(0);
});

it('keeps the unit when the job stops after the transcript', function (): void {
    app()->when(HadithVerifier::class)->needs('$providers')
        ->give(fn (): array => [new FakeHadithProvider(failWith: 'انقطاع القاعدة', name: 'graded')]);
    app()->forgetInstance(VerifierRegistry::class);

    $job = chargedJob(['evidence_json' => [['kind' => 'hadith', 'raw_text' => 'أحب الأعمال إلى الله أدومها وإن قل']]]);

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure, JobState::ExtractingEvidence, JobState::Verifying] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $job = runPipeline($job);

    expect($job->error_code)->toBe('corpus_unavailable')
        ->and(app(QuotaGuard::class)->monthlyQuota($this->tenant->refresh())->used)->toBe(1);
});

it('gives nothing back for a job that was never charged', function (): void {
    // الخَلَفُ من «أعد المحاولة» دفع على سلفه، فلا يُردّ عليه ما لم يُقيَّد.
    $job = runPipeline(queuedJob(['transcript_text' => str_repeat('كلمة ', 120)]));

    expect($job->state)->toBe(JobState::Failed)
        ->and(UsageRecord::query()->where('summary_job_id', $job->id)->count())->toBe(0);
});

it('never leaves a job spinning when a stage fails to advance it', function (): void {
    $job = queuedJob(['transcript_text' => null]);
    $this->lecture->update(['source_url' => null]);

    // لو دار المشغّل لما رجع أصلاً؛ فرجوعُه بحالةٍ نهائية هو الاختبار.
    expect(runPipeline($job)->state->isTerminal())->toBeTrue();
});

// ── الإرسال إلى الطابور ──────────────────────────────────────────

it('is queued when a lecture is created, not left sitting', function (): void {
    Queue::fake();

    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'درسٌ جديد',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
    ])->assertRedirect();

    Queue::assertPushed(RunSummaryPipeline::class);
});

it('queues the replacement a retry creates, which nothing did before', function (): void {
    Queue::fake();

    $job = queuedJob(['state' => JobState::Failed->value, 'error_code' => 'ytdlp_timeout']);
    $user = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($user)->post("/panel/jobs/{$job->id}/retry")->assertRedirect();

    Queue::assertPushed(RunSummaryPipeline::class);
});

it('declares tries and a backoff ladder so Horizon can show them', function (): void {
    $pipeline = new RunSummaryPipeline(1);

    expect($pipeline->tries)->toBeGreaterThan(1)
        ->and($pipeline->backoff())->toBe([30, 60, 120]);
});
