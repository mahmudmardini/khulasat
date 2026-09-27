<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => Role::Editor,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => ['role' => Role::Owner]);
    }

    public function editor(): static
    {
        return $this->state(fn (): array => ['role' => Role::Editor]);
    }

    public function viewer(): static
    {
        return $this->state(fn (): array => ['role' => Role::Viewer]);
    }

    /** المشرف العام لا ينتمي إلى جهة — المواصفة §10. */
    public function superAdmin(): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => null,
            'role' => Role::Owner,
        ]);
    }

    public function for_(Tenant $tenant): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenant->id]);
    }
}
