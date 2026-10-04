<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * إقرارُ الكلفة — T-203. النافذةُ في الواجهة، وأرقامُها من الخادم: هذا ما
 * يُختبر هنا. فـ«أعد المحاولة» بعد الفشل إعادةُ توليدٍ تُحتسب، وشاشةُ المتابعة
 * تحمل ما يُحتسب منه وكم بقي، لا تقدّره الواجهة.
 */

it('يحمل شاشةَ المتابعة إعاداتِ الملخّص لإقرار «أعد المحاولة» بأرقامها', function (): void {
    $tenant = Tenant::factory()->create(['regenerations_per_summary' => 3, 'monthly_quota' => 20]);
    $user = User::factory()->owner()->for_($tenant)->create();
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id]);
    $job = SummaryJob::factory()->for_($lecture)->inState(JobState::Failed)->create(['regeneration_count' => 1]);

    $this->actingAs($user)->get("/panel/jobs/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Jobs/Show')
            ->where('job.regenerations', ['used' => 1, 'limit' => 3])
            // والحصّةُ الشهرية مشتركةٌ في كلّ شاشة، فتقرؤها النافذة منها.
            ->where('quota.limit', 20)
        );
});
