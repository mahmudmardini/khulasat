<?php

declare(strict_types=1);

use App\Actions\Summary\ScheduleStageRetry;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\AutomaticRetryRefused;
use App\Domain\Summary\InvalidTransition;
use App\Domain\Summary\JobState;
use App\Domain\Summary\ReviewIncomplete;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\SummaryJobTransition;
use App\Models\Tenant;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->transition = app(TransitionJob::class);
});

/** يقود المهمّة عبر مسارٍ من الحالات بالفعل نفسه الذي يستعمله الإنتاج. */
function drive(SummaryJob $job, JobState ...$states): SummaryJob
{
    foreach ($states as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job;
}

// ── التسجيل: الزمن والكلفة الجزئية — المواصفة §5 ─────────────────

it('records every transition with its time and partial cost', function (): void {
    $this->transition->handle($this->job, JobState::Transcribing, costUsd: 0.0);
    $this->transition->handle($this->job, JobState::Cleaning, costUsd: 0.0210);
    $this->transition->handle($this->job, JobState::ExtractingStructure, costUsd: 0.0075);

    $rows = $this->job->transitions()->get();

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->from_state)->toBe(JobState::Queued)
        ->and($rows[0]->to_state)->toBe(JobState::Transcribing)
        ->and($rows[0]->occurred_at)->not->toBeNull()
        ->and((float) $rows[1]->cost_usd)->toBe(0.021)
        ->and((float) $rows[2]->cost_usd)->toBe(0.0075);
});

it('attributes partial cost to the stage that just ended', function (): void {
    $this->transition->handle($this->job, JobState::Transcribing);
    $this->transition->handle($this->job, JobState::Cleaning, costUsd: 0.05);

    // كلفة ٠٫٠٥ ثمنُ التفريغ الذي انتهى، لا ثمنُ التنظيف الذي لم يبدأ.
    expect($this->job->fresh()->cost_breakdown)->toBe(['transcribing' => 0.05]);
});

it('accumulates the total from the partials', function (): void {
    $this->transition->handle($this->job, JobState::Transcribing, costUsd: 0.10);
    $this->transition->handle($this->job, JobState::Cleaning, costUsd: 0.02);
    $this->transition->handle($this->job, JobState::ExtractingStructure, costUsd: 0.03);

    expect((float) $this->job->fresh()->total_cost_usd)->toBe(0.15);
});

it('stamps started_at when it leaves the queue and finished_at when it ends', function (): void {
    expect($this->job->started_at)->toBeNull();

    $this->transition->handle($this->job, JobState::Transcribing);
    expect($this->job->fresh()->started_at)->not->toBeNull()
        ->and($this->job->fresh()->finished_at)->toBeNull();

    $this->transition->handle($this->job, JobState::Failed, errorCode: 'transcript_failed');

    expect($this->job->fresh()->finished_at)->not->toBeNull()
        ->and($this->job->fresh()->error_code)->toBe('transcript_failed');
});

it('keeps the transition log inside the tenant', function (): void {
    $this->transition->handle($this->job, JobState::Transcribing);

    expect(SummaryJobTransition::query()->sole()->tenant_id)->toBe($this->job->tenant_id);
});

// ── الانتقال غير المسموح ─────────────────────────────────────────

it('throws on a forbidden transition and leaves the job untouched', function (): void {
    expect(fn (): SummaryJob => $this->transition->handle($this->job, JobState::Published))
        ->toThrow(InvalidTransition::class);

    expect($this->job->fresh()->state)->toBe(JobState::Queued)
        ->and(SummaryJobTransition::query()->count())->toBe(0);
});

it('refuses a state written outside the state machine', function (): void {
    // بلا هذا الحارس يتخطّى `update` الخريطةَ كلَّها بسطر واحد.
    expect(fn (): bool => $this->job->update(['state' => JobState::Published]))
        ->toThrow(InvalidTransition::class);

    expect($this->job->fresh()->state)->toBe(JobState::Queued);
});

// ── المراجعة تمنع النشر — المواصفة §5، وCLAUDE.md §2 القاعدة الرابعة ──

it('refuses to leave review while one evidence item is still pending', function (): void {
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::NeedsReview);

    EvidenceItem::factory()->for_($this->job)->create();
    EvidenceItem::factory()->for_($this->job)->pending()->create();

    expect(fn (): SummaryJob => $this->transition->handle($this->job, JobState::Writing))
        ->toThrow(ReviewIncomplete::class);

    expect($this->job->fresh()->state)->toBe(JobState::NeedsReview);
});

it('lets the job leave review once every item is settled', function (ReviewStatus $status): void {
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::NeedsReview);

    $item = EvidenceItem::factory()->for_($this->job)->pending()->create();
    $item->update(['review_status' => $status]);

    $this->transition->handle($this->job, JobState::Writing);

    expect($this->job->fresh()->state)->toBe(JobState::Writing);
})->with([
    'approved' => ReviewStatus::Approved,
    'corrected' => ReviewStatus::Corrected,
    'removed' => ReviewStatus::Removed,
]);

it('refuses to publish while any evidence item is pending', function (): void {
    // الباب الثاني: شاهدٌ أُضيف بعد الكتابة. فحصُ مخرج المراجعة وحده يتركه مفتوحاً.
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering);

    EvidenceItem::factory()->for_($this->job)->pending()->create();

    expect(fn (): SummaryJob => $this->transition->handle($this->job, JobState::Published))
        ->toThrow(ReviewIncomplete::class);

    expect($this->job->fresh()->state)->toBe(JobState::Rendering);
});

it('publishes when the whole path is clean', function (): void {
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering);

    EvidenceItem::factory()->for_($this->job)->count(3)->create();

    $this->transition->handle($this->job, JobState::Published);

    expect($this->job->fresh()->state)->toBe(JobState::Published);
});

// ── الإعادة الآلية — المواصفة §5 ─────────────────────────────────

it('retries a mechanical stage three times with exponential backoff', function (): void {
    drive($this->job, JobState::Transcribing);
    $retry = app(ScheduleStageRetry::class);

    expect($retry->handle($this->job))->toBe(30)
        ->and($retry->handle($this->job))->toBe(60)
        ->and($retry->handle($this->job))->toBe(120)
        ->and($this->job->attempt)->toBe(3);

    expect(fn (): int => $retry->handle($this->job))->toThrow(AutomaticRetryRefused::class);
});

it('refuses an automatic retry of a model stage', function (): void {
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure);

    expect(fn (): int => app(ScheduleStageRetry::class)->handle($this->job))
        ->toThrow(AutomaticRetryRefused::class, 'مرحلة نماذج');
});

it('refuses an automatic retry while waiting for a human', function (): void {
    drive($this->job, JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::NeedsReview);

    expect(fn (): int => app(ScheduleStageRetry::class)->handle($this->job))
        ->toThrow(AutomaticRetryRefused::class);
});

it('resets the attempt counter when the stage is left', function (): void {
    drive($this->job, JobState::Transcribing);
    app(ScheduleStageRetry::class)->handle($this->job);

    expect($this->job->fresh()->attempt)->toBe(1);

    $this->transition->handle($this->job, JobState::Cleaning);

    expect($this->job->fresh()->attempt)->toBe(0);
});
