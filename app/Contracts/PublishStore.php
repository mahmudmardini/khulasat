<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * تخزين الصفحات المنشورة — المواصفة §9.
 *
 * خلف عقدٍ لأنّ الاختبارات لا ترفع إلى S3، **ولأنّ المزوّد يتبدّل**: R2 أو
 * Spaces أو غيرهما، والمواصفة §2 تكتبها «S3-compatible» لا S3.
 */
interface PublishStore
{
    /** يرفع ويعيد الرابط العامّ. */
    public function put(string $path, string $contents, string $contentType): string;

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function url(string $path): string;

    /** يقرأ المحتوى، أو `null` إن لم يوجد — T-127: القارئ مقابل {@see self::put()}. */
    public function get(string $path): ?string;
}
