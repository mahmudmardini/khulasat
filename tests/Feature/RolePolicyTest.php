<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

// الأدوار الثلاثة داخل الجهة — المواصفة §10.
// هذه طبقة ثانية فوق الحاجز، لا بديلاً عنه.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->for_($this->tenant)->owner()->create();
    $this->editor = User::factory()->for_($this->tenant)->editor()->create();
    $this->viewer = User::factory()->for_($this->tenant)->viewer()->create();
});

it('lets only the owner manage the team', function (): void {
    expect($this->owner->can('create', User::class))->toBeTrue()
        ->and($this->editor->can('create', User::class))->toBeFalse()
        ->and($this->viewer->can('create', User::class))->toBeFalse();
});

it('lets only the owner update a teammate', function (): void {
    expect($this->owner->can('update', $this->editor))->toBeTrue()
        ->and($this->editor->can('update', $this->viewer))->toBeFalse();
});

it('stops the owner deleting themselves', function (): void {
    // وإلّا بقيت الجهة بلا مالك ولا سبيل إلى استعادتها.
    expect($this->owner->can('delete', $this->owner))->toBeFalse()
        ->and($this->owner->can('delete', $this->editor))->toBeTrue();
});

it('grants publishing to owner and editor but not viewer', function (): void {
    expect($this->owner->role->canPublish())->toBeTrue()
        ->and($this->editor->role->canPublish())->toBeTrue()
        ->and($this->viewer->role->canPublish())->toBeFalse();
});

it('denies acting on a user from another tenant', function (): void {
    $stranger = User::factory()->for_(Tenant::factory()->create())->editor()->create();

    expect($this->owner->can('view', $stranger))->toBeFalse()
        ->and($this->owner->can('update', $stranger))->toBeFalse();
});
