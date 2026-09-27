<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;

/*
 * فرزُ الفهرس — T-86.
 *
 * **وُجد في T-82:** الترتيب ثابت، ومن أراد الأقدم أو بالعنوان لا سبيل له.
 * ★ **والافتراضُ يبقى كما في SCREENS.md §2**: «`needs_review` أوّلاً، ثمّ
 * الأحدث» — والفرزُ في الخادم لا في الصفحة الظاهرة، فالصفحة الثانية لا
 * تعرف ما في الأولى.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    $this->old = sortedJob('ب — درسٌ قديم ينتظر المراجعة', '2026-09-01 10:00', JobState::NeedsReview);
    $this->newer = sortedJob('ت — درسٌ أحدث', '2026-09-05 10:00');
    $this->newest = sortedJob('أ — أحدث الدروس', '2026-09-09 10:00');
});

function sortedJob(string $title, string $created, JobState $state = JobState::Published): SummaryJob
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => $title,
        'created_at' => Carbon::parse($created),
    ]);

    return SummaryJob::factory()->inState($state)->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => $lecture->id,
    ]);
}

/** @return array{ids: list<int>, sort: string|null} */
function indexOrder(string $query = ''): array
{
    $page = test()->actingAs(test()->user)->get('/panel'.$query)->assertOk()->viewData('page');

    return [
        'ids' => array_column($page['props']['jobs']['data'], 'id'),
        'sort' => $page['props']['filters']['sort'] ?? null,
    ];
}

it('keeps what needs a decision first by default', function (): void {
    expect(indexOrder())->toBe([
        'ids' => [$this->old->id, $this->newest->id, $this->newer->id],
        'sort' => 'smart',
    ]);
});

it('sorts by the chosen order, in the server', function (string $sort, array $order): void {
    expect(indexOrder("?sort={$sort}"))->toBe([
        'ids' => array_map(fn (string $name): int => $this->{$name}->id, $order),
        'sort' => $sort,
    ]);
})->with([
    'newest' => ['newest', ['newest', 'newer', 'old']],
    'oldest' => ['oldest', ['old', 'newer', 'newest']],
    'title' => ['title', ['newest', 'old', 'newer']],
]);

it('falls back to the default for a sort it does not know', function (): void {
    // قائمةٌ بيضاء: ما وصل من الرابط لا يبلغ `orderBy` بحال.
    expect(indexOrder('?sort=lectures.id;drop'))->toBe([
        'ids' => [$this->old->id, $this->newest->id, $this->newer->id],
        'sort' => 'smart',
    ]);
});

it('sorts within a search, not instead of it', function (): void {
    expect(indexOrder('?sort=oldest&search='.urlencode('أحدث')))->toBe([
        'ids' => [$this->newer->id, $this->newest->id],
        'sort' => 'oldest',
    ]);
});
