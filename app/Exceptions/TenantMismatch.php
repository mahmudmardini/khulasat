<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a record is written for a tenant other than the current one.
 *
 * لا تُعرض للمستخدم. وقوعها يعني خطأً برمجياً لا خطأ إدخال: محاولة كتابة
 * سجلّ لجهة غير جهة السياق. تُوقف العملية بدل أن تُكتب البيانات في المكان الخطأ.
 */
final class TenantMismatch extends RuntimeException
{
    public static function forWrite(string $model, ?int $attempted, ?int $current): self
    {
        return new self(sprintf(
            'محاولة كتابة %s للجهة %s والسياق على الجهة %s.',
            $model,
            $attempted === null ? 'null' : (string) $attempted,
            $current === null ? 'null' : (string) $current,
        ));
    }
}
