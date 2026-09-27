<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

// Horizon تكشف طوابير كلّ المستأجرين — للمشرف العام وحده.

it('denies the Horizon dashboard to a guest', function (): void {
    expect(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

it('denies the Horizon dashboard to a tenant user', function (): void {
    $owner = User::factory()->for_(Tenant::factory()->create())->owner()->create();

    expect(Gate::forUser($owner)->allows('viewHorizon'))->toBeFalse();
});

it('allows the Horizon dashboard to a super admin', function (): void {
    User::factory()->superAdmin()->create();
    $admin = Admin::first();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue();
});
