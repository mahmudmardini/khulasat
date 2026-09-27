<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VenueMode;
use App\Models\Lecture;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lecture>
 */
class LectureFactory extends Factory
{
    protected $model = Lecture::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title_ar' => fake()->sentence(3),
            'subtitle_ar' => null,
            'speaker_name' => fake()->name(),
            'speaker_title' => fake()->jobTitle(),
            'source_url' => 'https://www.youtube.com/watch?v='.fake()->unique()->lexify('???????????'),
            'source_platform' => 'youtube',
            'hijri_date' => '١٢ رجب ١٤٤٧',
            'gregorian_date' => now()->toDateString(),
            'weekday' => 'الجمعة',
            'time_note' => 'بعد صلاة الجمعة',
            'venue_mode' => VenueMode::Institution,
            'duration_seconds' => 2_700,
            'created_at' => now(),
        ];
    }

    public function for_(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->id]);
    }
}
