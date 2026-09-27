<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name_ar' => $name,
            'name_ar_full' => $name,
            'name_latin' => Str::upper(fake()->unique()->lexify('????')),
            'slug' => fake()->unique()->slug(2),
            'plan' => 'free',
            'status' => 'active',
            'domain' => 'islamic',
            'brand_kit' => ['palette_id' => 'emerald_gold'],
            'monthly_quota' => 20,
            'daily_cap' => 3,
            'max_lecture_minutes' => 180,
        ];
    }
}
