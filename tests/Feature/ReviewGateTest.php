<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Jobs\RunSummaryPipeline;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * بوّابة المراجعة — SCREENS.md الشاشة 5، والمهمّة T-17.
 *
 * **وما يُختبر هنا ليس الشاشة بل التوقيع**: أيّ لفظٍ يظهر على صفحة الجهة
 * بعد قرار المراجع. فالقرار يكتب `matched_text`، وهو ما تقرؤه الكتابة
 * والعرض معاً.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    $this->job = SummaryJob::factory()->inState(JobState::NeedsReview)->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'transcript_text' => "فقرةٌ أولى لا شاهد فيها.\n\nوقد قال النبيّ صلّى الله عليه وسلّم أحب الأعمال إلى الله أكثرها وإن قلّ، وهذا في المداومة.",
    ]);

    $this->item = EvidenceItem::factory()->pending()->for_($this->job)->create([
        'raw_text' => 'أحب الأعمال إلى الله أكثرها وإن قلّ',
        'matched_text' => 'أحبُّ الأعمال إلى الله أدومها وإن قلّ',
        'match_status' => MatchStatus::Partial,
        'source_ref' => 'متّفق عليه — البخاري ومسلم',
        'source_meta' => ['narrator' => 'عائشة رضي الله عنها', 'grade' => 'sahih', 'grade_label' => 'صحيح'],
    ]);
});

function decide(string $decision, ?EvidenceItem $item = null): TestResponse
{
    $item ??= test()->item;

    return test()->actingAs(test()->user)
        ->post("/panel/jobs/{$item->summary_job_id}/evidence/{$item->id}/decide", ['decision' => $decision]);
}

// ── الشاشة ───────────────────────────────────────────────────────

it('shows one evidence item with everything the decision needs', function (): void {
    $this->actingAs($this->user)
        ->get("/panel/jobs/{$this->job->id}/review")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Review')
            ->where('pending', 1)
            ->where('items.0.quoted', 'أحب الأعمال إلى الله أكثرها وإن قلّ')
            ->where('items.0.source', 'أحبُّ الأعمال إلى الله أدومها وإن قلّ')
            ->where('items.0.narrator', 'عائشة رضي الله عنها')
            ->where('items.0.grade_label', 'صحيح')
            // ★ السياق معروض، فلا يحتاج المراجع فتح تبويب آخر ليقرّر.
            ->where('items.0.context', fn (?string $c): bool => $c !== null && str_contains($c, 'المداومة'))
        );
});

// ── القرارات الثلاثة، وأثرها في المنشور ──────────────────────────

it('publishes the source wording when the reviewer keeps it', function (): void {
    decide('source')->assertRedirect();

    $this->item->refresh();

    expect($this->item->review_status)->toBe(ReviewStatus::Approved)
        ->and($this->item->matched_text)->toBe('أحبُّ الأعمال إلى الله أدومها وإن قلّ')
        ->and($this->item->resolved_by)->toBe($this->user->id);
});

it('publishes the lecture wording when the reviewer insists on it', function (): void {
    decide('as_quoted')->assertRedirect();

    $this->item->refresh();

    /*
     * ★ **اللفظ يُنسخ إلى `matched_text` ولا يُترك فارغاً.** فالكتابة تقرأ
     *   `matched_text ?? raw_text`، ولو تُرك لبدا الأمر صحيحاً حتى يملأ
     *   الحقلَ قرارٌ آخر، فينقلب المنشور بلا أن يقرّر أحد.
     */
    expect($this->item->review_status)->toBe(ReviewStatus::Corrected)
        ->and($this->item->matched_text)->toBe('أحب الأعمال إلى الله أكثرها وإن قلّ');
});

it('drops the item from the summary when the reviewer removes it', function (): void {
    decide('remove')->assertRedirect();

    expect($this->item->refresh()->review_status)->toBe(ReviewStatus::Removed);
});

it('refuses to keep a source wording that does not exist', function (): void {
    $orphan = EvidenceItem::factory()->pending()->for_($this->job)->create([
        'matched_text' => null,
        'match_status' => MatchStatus::None,
    ]);

    decide('source', $orphan)->assertSessionHasErrors('decision');

    expect($orphan->refresh()->review_status)->toBe(ReviewStatus::Pending);
});

// ── من يملك أن يوقّع ─────────────────────────────────────────────

it('refuses a decision from a viewer, who may only read', function (): void {
    $viewer = User::factory()->viewer()->for_($this->tenant)->create();

    $this->actingAs($viewer)
        ->post("/panel/jobs/{$this->job->id}/evidence/{$this->item->id}/decide", ['decision' => 'remove'])
        ->assertSessionHasErrors('decision');

    expect($this->item->refresh()->review_status)->toBe(ReviewStatus::Pending);
});

it('hides another tenant evidence behind a 404, not a 403', function (): void {
    $other = Tenant::factory()->create();
    $stranger = User::factory()->owner()->for_($other)->create();

    $this->actingAs($stranger)->get("/panel/jobs/{$this->job->id}/review")->assertNotFound();
});

// ── الاستئناف ────────────────────────────────────────────────────

it('refuses to resume while one item is still pending', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->post("/panel/jobs/{$this->job->id}/resume")
        ->assertSessionHasErrors('resume');

    Queue::assertNotPushed(RunSummaryPipeline::class);
});

it('resumes the pipeline once every item is settled', function (): void {
    Queue::fake();

    decide('source')->assertRedirect();

    $this->actingAs($this->user)
        ->post("/panel/jobs/{$this->job->id}/resume")
        ->assertRedirect(route('jobs.show', $this->job));

    Queue::assertPushed(RunSummaryPipeline::class);
});

it('carries the settled wording all the way into the published body', function (): void {
    decide('as_quoted')->assertRedirect();

    // ما يدخل الكتابة هو `matched_text` — T-12، ومعيار القبول الرابع فيها.
    $publishable = $this->job->evidenceItems()
        ->whereNot('review_status', ReviewStatus::Removed->value)
        ->pluck('matched_text')
        ->all();

    expect($publishable)->toBe(['أحب الأعمال إلى الله أكثرها وإن قلّ']);
});
