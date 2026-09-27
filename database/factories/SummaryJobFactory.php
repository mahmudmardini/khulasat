<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\TranscriptSource;
use App\Models\Lecture;
use App\Models\SummaryJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SummaryJob>
 */
class SummaryJobFactory extends Factory
{
    protected $model = SummaryJob::class;

    public function definition(): array
    {
        $lecture = Lecture::factory();

        return [
            'lecture_id' => $lecture,
            'tenant_id' => fn (array $attributes): int => (int) Lecture::withoutGlobalScopes()
                ->findOrFail($attributes['lecture_id'])->tenant_id,
            'state' => JobState::Queued,
            'attempt' => 0,
            'regeneration_count' => 0,
            'transcript_source' => TranscriptSource::Captions,
            'cost_breakdown' => [],
            'total_cost_usd' => 0,
        ];
    }

    /**
     * Place the job directly in a state, bypassing the machine.
     *
     * **للاختبار وحده.** الطريق الحيّ إلى الحالة هو
     * {@see TransitionJob}، وهذا إنشاءٌ لا تحديث،
     * فلا يمرّ بحارس `updating` أصلاً.
     */
    public function inState(JobState $state): static
    {
        return $this->state(fn (): array => ['state' => $state]);
    }

    public function for_(Lecture $lecture): static
    {
        return $this->state(fn (): array => [
            'lecture_id' => $lecture->id,
            'tenant_id' => $lecture->tenant_id,
        ]);
    }
}
