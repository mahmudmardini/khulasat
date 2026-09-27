<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvidenceItem>
 */
class EvidenceItemFactory extends Factory
{
    protected $model = EvidenceItem::class;

    public function definition(): array
    {
        $raw = 'إنما الأعمال بالنيات';

        return [
            'summary_job_id' => SummaryJob::factory(),
            'tenant_id' => fn (array $attributes): int => (int) SummaryJob::withoutGlobalScopes()
                ->findOrFail($attributes['summary_job_id'])->tenant_id,
            'domain' => 'islamic',
            'kind' => 'hadith',
            'raw_text' => $raw,
            'normalized_text' => Arabic::normalize($raw),
            'matched_text' => null,
            'source_ref' => null,
            'source_meta' => [],
            'match_status' => MatchStatus::Exact,
            'review_status' => ReviewStatus::AutoPassed,
        ];
    }

    /** شاهدٌ لم يُطابَق، فينتظر قرار إنسان ويوقف النشر. */
    public function pending(): static
    {
        return $this->state(fn (): array => [
            'match_status' => MatchStatus::None,
            'review_status' => ReviewStatus::Pending,
        ]);
    }

    public function settledAs(ReviewStatus $status): static
    {
        return $this->state(fn (): array => [
            'review_status' => $status,
            'resolved_at' => now(),
        ]);
    }

    public function for_(SummaryJob $job): static
    {
        return $this->state(fn (): array => [
            'summary_job_id' => $job->id,
            'tenant_id' => $job->tenant_id,
        ]);
    }
}
