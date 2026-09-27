<?php

declare(strict_types=1);

use App\Domain\Summary\InvalidTransition;
use App\Domain\Summary\JobState;

// ── الخريطة — المواصفة §5 ────────────────────────────────────────

it('walks the happy path from queued to published', function (): void {
    $path = [
        JobState::Queued,
        JobState::Transcribing,
        JobState::Cleaning,
        JobState::ExtractingStructure,
        JobState::ExtractingEvidence,
        JobState::Verifying,
        JobState::Writing,
        JobState::Rendering,
        JobState::Published,
    ];

    $state = array_shift($path);

    foreach ($path as $next) {
        $state = $state->transitionTo($next);
    }

    expect($state)->toBe(JobState::Published);
});

it('branches from verifying into review', function (): void {
    expect(JobState::Verifying->canTransitionTo(JobState::NeedsReview))->toBeTrue()
        ->and(JobState::NeedsReview->canTransitionTo(JobState::Writing))->toBeTrue();
});

it('throws on a transition the map does not have', function (): void {
    JobState::Queued->transitionTo(JobState::Published);
})->throws(InvalidTransition::class);

it('refuses to skip verification on the way to writing', function (): void {
    // القفز من استخراج الشواهد إلى الكتابة يتخطّى طبقة التحقّق كلّها.
    JobState::ExtractingEvidence->transitionTo(JobState::Writing);
})->throws(InvalidTransition::class);

it('refuses to move backwards', function (): void {
    JobState::Rendering->transitionTo(JobState::Writing);
})->throws(InvalidTransition::class);

it('refuses to stay in place', function (): void {
    JobState::Cleaning->transitionTo(JobState::Cleaning);
})->throws(InvalidTransition::class);

it('names the states allowed from the current one in the message', function (): void {
    expect(fn (): JobState => JobState::Queued->transitionTo(JobState::Rendering))
        ->toThrow(InvalidTransition::class, 'transcribing');
});

// ── الحالات النهائية ─────────────────────────────────────────────

it('closes the three terminal states', function (JobState $state): void {
    expect($state->isTerminal())->toBeTrue()
        ->and($state->allowedTransitions())->toBe([]);
})->with([
    'published' => JobState::Published,
    'failed' => JobState::Failed,
    'cancelled' => JobState::Cancelled,
]);

it('leaves no way out of a terminal state', function (JobState $to): void {
    JobState::Published->transitionTo($to);
})->with([
    'writing' => JobState::Writing,
    'rendering' => JobState::Rendering,
    'queued' => JobState::Queued,
])->throws(InvalidTransition::class);

// ── الإعادة الآلية — المواصفة §5 ─────────────────────────────────

it('allows automatic retry in the three mechanical states only', function (): void {
    $retryable = array_values(array_filter(
        JobState::cases(),
        fn (JobState $state): bool => $state->allowsAutomaticRetry(),
    ));

    expect($retryable)->toBe([JobState::Transcribing, JobState::Cleaning, JobState::Rendering]);
});

it('caps automatic attempts at three', function (): void {
    expect(JobState::MAX_AUTOMATIC_ATTEMPTS)->toBe(3);
});

it('backs off exponentially between attempts', function (): void {
    expect(JobState::retryDelaySeconds(1))->toBe(30)
        ->and(JobState::retryDelaySeconds(2))->toBe(60)
        ->and(JobState::retryDelaySeconds(3))->toBe(120);
});

it('never re-enters a model stage automatically', function (JobState $stage): void {
    // مراحل النماذج لا تُعاد آلياً عند نجاح الاستدعاء ورداءة النتيجة:
    // لا حافّة راجعة إليها في الخريطة، فلا مسار آليّ يُعيد الدخول.
    expect($stage->isModelStage())->toBeTrue();

    $incoming = array_filter(
        JobState::cases(),
        fn (JobState $from): bool => $from !== $stage && $from->canTransitionTo($stage),
    );

    foreach ($incoming as $from) {
        expect($stage->canTransitionTo($from))->toBeFalse();
    }
})->with([
    'cleaning' => JobState::Cleaning,
    'extracting_structure' => JobState::ExtractingStructure,
    'extracting_evidence' => JobState::ExtractingEvidence,
    'writing' => JobState::Writing,
]);

// ── المراجعة لا تنتهي بمهلة ──────────────────────────────────────

it('never times out of review', function (): void {
    expect(JobState::NeedsReview->expiresByTimeout())->toBeFalse()
        ->and(JobState::NeedsReview->blocksPublishing())->toBeTrue();
});

it('offers review no route to failure', function (): void {
    // الانتظار البشري لا يفشل من نفسه. ولو جاز لصار الإهمالُ فشلاً آلياً.
    expect(JobState::NeedsReview->allowedTransitions())
        ->toBe([JobState::Writing, JobState::Cancelled]);
});

it('offers review no direct route to publishing', function (): void {
    JobState::NeedsReview->transitionTo(JobState::Published);
})->throws(InvalidTransition::class);

it('times out of every non-terminal state except review', function (JobState $state): void {
    expect($state->expiresByTimeout())->toBe(! $state->isTerminal() && $state !== JobState::NeedsReview);
})->with(fn (): array => array_map(
    fn (JobState $state): array => [$state],
    JobState::cases(),
));
