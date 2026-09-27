<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;

// حارس المشرف منفصل عن حارس الجهة — المواصفة §10.

it('keeps the operator panel invisible to a guest', function (): void {
    $this->get('/admin')->assertNotFound();
});

it('keeps the operator panel invisible to a tenant user', function (): void {
    // 404 لا 403: لا يُؤكَّد وجود اللوحة لمن لا يملكها.
    $owner = User::factory()->for_(Tenant::factory()->create())->owner()->create();

    $this->actingAs($owner)->get('/admin')->assertNotFound();
});

it('opens the operator panel to a super admin on the admin guard', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
});

it('does not let a web session reach the admin guard', function (): void {
    // نفس المستخدم، وحارس آخر. الجلسة لا تعبر بين الحارسين.
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin, 'web')->get('/admin')->assertNotFound();
});

it('refuses to resolve a tenant user through the admin provider', function (): void {
    $owner = User::factory()->for_(Tenant::factory()->create())->owner()->create();

    expect(Admin::find($owner->id))->toBeNull();
});

it('resolves only super admins through the admin provider', function (): void {
    $admin = User::factory()->superAdmin()->create();

    expect(Admin::find($admin->id))->not->toBeNull();
});
