<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Role permissions inside one tenant — المواصفة §10.
 *
 * **هذه طبقة ثانية لا أولى.** الحاجز (TenantScope) يمنع رؤية سجلّات جهة
 * أخرى أصلاً، وهذه تحكم ما يفعله العضو داخل جهته. ولو سقطت هذه وحدها
 * لبقي العزل قائماً.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->tenant_id === $target->tenant_id;
    }

    /** الفريق للمالك وحده — SCREENS.md §10. */
    public function create(User $actor): bool
    {
        return $actor->role->isOwner();
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->role->isOwner() && $actor->tenant_id === $target->tenant_id;
    }

    public function delete(User $actor, User $target): bool
    {
        // ولا يحذف المالك نفسه، فتبقى الجهة بلا مالك.
        return $actor->role->isOwner()
            && $actor->tenant_id === $target->tenant_id
            && $actor->id !== $target->id;
    }
}
