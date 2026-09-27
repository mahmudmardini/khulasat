<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'locale',
    ];

    /*
     * ★ **افتراضُ اللغة في النموذج كما هو في الجدول** — T-133.
     *
     * فالافتراضُ في الهجرة يعمل عند الإدراج وحده، والصفُّ المنشأُ للتوّ يبقى
     * في الذاكرة بلا لغة. ومن قرأ `$user->locale` قبل إعادة التحميل أخذ
     * `null`، فسقط `SetAppLocale` إلى الجلسة — سلوكٌ صحيحٌ بالصدفة لا بالقصد.
     */
    protected $attributes = [
        'locale' => 'ar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'locale' => Locale::class,
        ];
    }

    /**
     * المشرف العام لا ينتمي إلى جهة — المواصفة §10.
     *
     * وهذا **ليس** بوّابة الدخول إلى لوحة المشرف وحدها؛ لها حارس منفصل.
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * رسالة الاستعادة بالعربية — T-32.
     *
     * **ولا قالب لارافل الافتراضي**: إنجليزيٌّ من اليسار إلى اليمين، ورسالةٌ
     * كذلك تصل جهةً عربية **تُقرأ رسالةَ احتيال** فتُحذف، ويبقى صاحبها
     * خارج حسابه.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function belongsToTenant(int $tenantId): bool
    {
        return $this->tenant_id === $tenantId;
    }
}
