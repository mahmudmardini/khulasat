<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the lecture URL points at a playlist — المواصفة §5-أ-1 الفحص ٣.
 *
 * **وليست من الأكواد العشرة، وهذا مقصود.** المواصفة تُعطي الفحصين الأوّلين
 * رمزاً (`host_not_allowed` و`duration_exceeded`) وتقول في الثالث «يُطلب
 * رابط الفيديو المفرد» — أي أنّه طلبٌ من المستخدم لا عطلٌ في مهمّة. ويُصلح
 * في النموذج قبل أن تُنشأ مهمّة، فلا يُكتب في `summary_jobs.error_code`.
 */
final class PlaylistUrlGiven extends RuntimeException
{
    public static function forUrl(string $url): self
    {
        return new self("رابط قائمة تشغيل لا فيديو مفرد: {$url}");
    }

    /** الرسالة العربية التي تُعرض — من `lang/ar/errors.php` وحدها. */
    public function userMessage(): string
    {
        return (string) __('errors.transcript.playlist_given');
    }
}
