<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The platform operator — المواصفة §10.
 *
 * يشترك مع User في جدول واحد ويفترق عنه في أمرين مقصودين:
 *
 *   ١. **لا يحمل BelongsToTenant.** لو حمله لقُيّد بجهة السياق، ولمُنع
 *      المشرف من رؤية ما وُجد لأجله.
 *   ٢. نطاق ثابت على tenant_id = null. فلا يستطيع مستخدم جهةٍ أن يُصادَق
 *      عليه بحارس admin ولو صحّت كلمته — الحارس لا يراه أصلاً.
 */
class Admin extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(
            'super_admins_only',
            fn (Builder $query) => $query->whereNull('tenant_id'),
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
