<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\TenantContext;

/**
 * Who may use the one-click judge login, and as whom — T-186.
 *
 * قرارُ مالك المنتج: دخولٌ بنقرة بلا بريدٍ ولا كلمة مرور، لثلاثة أدوار.
 * **والحساباتُ ثلاثةٌ بأعيانها** ينشئها `khulasah:seed-judges` — فالزرُّ لا
 * يُدخل أيَّ حساب، بل هذا البريدَ وحده، ولا يمسّ حساباً حقيقياً.
 */
final class JudgeAccess
{
    /** رابطُ الجهة التجريبية. */
    public const TENANT_SLUG = 'demo';

    /** @var array<string, string> الدورُ ← بريدُ حسابه */
    public const ACCOUNTS = [
        'admin' => 'admin@judges.test',
        'owner' => 'owner@judges.test',
        'editor' => 'editor@judges.test',
    ];

    /**
     * ★ **بلا رمزٍ مضبوط لا يُفتح إلّا في `local`.** فخادمٌ عامّ نُسي رمزُه
     * يبقى مغلقاً، ولا يصير زرُّ المشرف لكلّ زائر.
     */
    public static function allows(mixed $key): bool
    {
        if (! config('khulasah.judges.enabled')) {
            return false;
        }

        $secret = (string) config('khulasah.judges.key');

        if ($secret === '') {
            return app()->environment('local');
        }

        return is_string($key) && hash_equals($secret, $key);
    }

    /** @return list<string> الأدوارُ التي أُنشئ حسابُها */
    public static function roles(): array
    {
        $present = app(TenantContext::class)->withoutScope(
            fn (): array => User::query()->whereIn('email', self::ACCOUNTS)->pluck('email')->all(),
        );

        return array_values(array_keys(array_intersect(self::ACCOUNTS, $present)));
    }

    public static function user(string $role): ?User
    {
        $email = self::ACCOUNTS[$role] ?? null;

        return $email === null ? null : app(TenantContext::class)->withoutScope(
            fn (): ?User => User::query()->where('email', $email)->first(),
        );
    }
}
