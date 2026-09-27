<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * صلاحيات الجهة — المواصفة §10.
 *
 * **والعزل شرطٌ أوّل لا ثانٍ:** كلّ فحصٍ هنا يبدأ بأنّ الفاعل من الجهة
 * نفسها. ومن فحص الدور قبل الانتماء أذن لمالك جهةٍ أن يعدّل جهةً أخرى.
 */
class TenantPolicy
{
    public function view(User $actor, Tenant $tenant): bool
    {
        return $actor->belongsToTenant($tenant->id);
    }

    /** **الهوية للمالك وحده.** ومحرّر المحتوى ينشئ الملخّصات ولا يبدّل الهوية. */
    public function updateBrand(User $actor, Tenant $tenant): bool
    {
        return $actor->belongsToTenant($tenant->id) && $actor->role->isOwner();
    }
}
