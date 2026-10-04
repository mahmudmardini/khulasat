<?php

declare(strict_types=1);

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\SummaryJobTransition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ui\JobProgress;
use Illuminate\Support\Carbon;

/*
 * زمنُ كلّ مرحلة — T-92، وSCREENS.md §4: «كلّ مرحلة: تمّت (✓ وزمنها)».
 *
 * ★ **والزمن من سجلّ الانتقالات لا تقديراً.** دخولُ المرحلة انتقالٌ إليها،
 * وخروجُها انتقالٌ منها، والفرق بينهما هو ما استغرقته — فلا يُعرض زمنٌ لم
 * يُسجَّل، كما لا تُعرض نسبةٌ لم تُقَس.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
    ]);

    $this->travelTo(Carbon::parse('2026-09-11 10:00:00'));
});

/** @param  array<int, array{0: JobState, 1: int}>  $path  كلّ مرحلةٍ وما يمضي قبل الخروج منها */
function walk(array $path): void
{
    foreach ($path as [$state, $seconds]) {
        app(TransitionJob::class)->handle(test()->job, $state);
        test()->travel($seconds)->seconds();
    }
}

/** @return list<array<string, mixed>> */
function stepsNow(): array
{
    return test()->actingAs(test()->user)->getJson('/panel/jobs/'.test()->job->id.'/status')->assertOk()->json('job.steps');
}

it('times each finished step from its own transitions', function (): void {
    walk([[JobState::Transcribing, 40], [JobState::Cleaning, 95], [JobState::ExtractingStructure, 20]]);

    $steps = stepsNow();

    expect($steps[0])->toMatchArray(['key' => 'transcribing', 'state' => 'done', 'seconds' => 40, 'skipped' => false])
        ->and($steps[1])->toMatchArray(['key' => 'cleaning', 'state' => 'done', 'seconds' => 95]);

    // الجارية ببدئها لا بمدّتها — المدّة تُحسب في المتصفّح إلى ساعته.
    expect($steps[2])->toMatchArray(['key' => 'structuring', 'state' => 'active', 'seconds' => null])
        ->and(Carbon::parse($steps[2]['started_at'])->equalTo(Carbon::parse('2026-09-11 10:02:15')))->toBeTrue();

    // وما لم يبدأ لا بدءَ له ولا مدّة.
    expect($steps[3])->toMatchArray(['state' => 'pending', 'started_at' => null, 'seconds' => null]);
});

it('marks a review that was never needed instead of timing it', function (): void {
    walk([
        [JobState::Transcribing, 10], [JobState::Cleaning, 10], [JobState::ExtractingStructure, 10],
        [JobState::ExtractingEvidence, 10], [JobState::Verifying, 10], [JobState::Writing, 70],
        [JobState::Rendering, 5],
    ]);

    $steps = stepsNow();

    expect($steps[5])->toMatchArray(['key' => 'review', 'state' => 'done', 'skipped' => true, 'seconds' => null])
        ->and($steps[6])->toMatchArray(['key' => 'writing', 'state' => 'done', 'seconds' => 70]);
});

it('times the review the reader took', function (): void {
    walk([
        [JobState::Transcribing, 10], [JobState::Cleaning, 10], [JobState::ExtractingStructure, 10],
        [JobState::ExtractingEvidence, 10], [JobState::Verifying, 10], [JobState::NeedsReview, 300],
        [JobState::Writing, 5],
    ]);

    expect(stepsNow()[5])->toMatchArray(['key' => 'review', 'state' => 'done', 'skipped' => false, 'seconds' => 300]);
});

/*
 * ═══ T-171 — وقتُ المراجعة ليس وقتَ إعداد، طلبُ مالك المنتج ═══
 *
 * «استغرق ٢٠ دقيقة» كانت تضمّ ست عشرة دقيقةً بيد الجهة. فيُرجَع وقتُ المراجعة
 * مستقلّاً، ويطرحه المتصفّح من المدّة الكلّية، ويقف عدّاده عند بدئها.
 */

/** @return array{seconds: int, open_since: string|null} */
function reviewNow(): array
{
    return test()->actingAs(test()->user)->getJson('/panel/jobs/'.test()->job->id.'/status')->assertOk()->json('job.review');
}

it('returns where an open review began so the counter stops there', function (): void {
    walk([
        [JobState::Transcribing, 60], [JobState::Cleaning, 60], [JobState::ExtractingStructure, 30],
        [JobState::ExtractingEvidence, 30], [JobState::Verifying, 60], [JobState::NeedsReview, 900],
    ]);

    $review = reviewNow();

    expect($review['seconds'])->toBe(0)
        ->and(Carbon::parse($review['open_since'])->equalTo(Carbon::parse('2026-09-11 10:04:00')))->toBeTrue();
});

it('leaves preparation time without the review, and gives the admin both', function (): void {
    walk([
        [JobState::Transcribing, 60], [JobState::Cleaning, 60], [JobState::ExtractingStructure, 30],
        [JobState::ExtractingEvidence, 30], [JobState::Verifying, 60], [JobState::NeedsReview, 960],
        [JobState::Writing, 30], [JobState::Rendering, 15], [JobState::Published, 0],
    ]);

    expect(reviewNow())->toBe(['seconds' => 960, 'open_since' => null]);

    // المدّةُ الكلّية ١٢٤٥ ثانية، منها ٩٦٠ بيد الجهة.
    $props = $this->actingAs(User::factory()->superAdmin()->create(), 'admin')
        ->get('/admin/jobs/'.$this->job->id)
        ->viewData('page')['props'];

    expect($props['job']['preparation_seconds'])->toBe(285)
        ->and($props['job']['review_seconds'])->toBe(960)
        // والمشرفُ يرى مدّة المراجعة في المعالج كما هي.
        ->and($props['job']['steps'][5])->toMatchArray(['key' => 'review', 'state' => 'done']);
});

it('has no review time for a job that never needed one', function (): void {
    walk([
        [JobState::Transcribing, 10], [JobState::Cleaning, 10], [JobState::ExtractingStructure, 10],
        [JobState::ExtractingEvidence, 10], [JobState::Verifying, 10], [JobState::Writing, 70],
        [JobState::Rendering, 5], [JobState::Published, 0],
    ]);

    expect(reviewNow())->toBe(['seconds' => 0, 'open_since' => null]);
});

it('sums every review period when a job enters review more than once', function (): void {
    $at = Carbon::parse('2026-09-11 10:00:00');

    // مهمّةٌ أُعيدت بعد إخفاقٍ فدخلت المراجعة مرّةً ثانية: ١٦ دقيقة ثمّ دقيقتان.
    foreach ([
        [JobState::Verifying, JobState::NeedsReview, 0],
        [JobState::NeedsReview, JobState::Writing, 960],
        [JobState::Writing, JobState::Failed, 990],
        [JobState::Verifying, JobState::NeedsReview, 1100],
        [JobState::NeedsReview, JobState::Writing, 1220],
    ] as [$from, $to, $offset]) {
        SummaryJobTransition::query()->create([
            'summary_job_id' => $this->job->id,
            'tenant_id' => $this->tenant->id,
            'from_state' => $from,
            'to_state' => $to,
            'attempt' => 0,
            'occurred_at' => $at->copy()->addSeconds($offset),
        ]);
    }

    expect(JobProgress::review($this->job->fresh()))->toBe(['seconds' => 1080, 'open_since' => null]);
});
