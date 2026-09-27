<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * A stable key for "the same source" — T-65.
 *
 * **الرابطُ الواحد يُكتب صوراً**: `youtu.be/X` و`watch?v=X` و
 * `watch?si=…&v=X` و`shorts/X` — كلُّها فيديو واحد. فمقارنةُ النصّ حرفاً
 * بحرف تُمرّر التكرارَ الذي وقع فعلاً: محاضرتان من نفس الفيديو بفارق
 * ثلاثٍ وستّين ثانية، فجرى الخطُّ مرّتين وصُرف ثمنُه مرّتين.
 *
 * **ولا يُتوسَّع في التطبيع.** ما لم يُعرَف معرّفُه يُقارَن برابطه مُشذَّباً
 * وحده: **تطبيعٌ متحمّس يجمع مصدرين مختلفين فيمنع محاضرةً مشروعة**، وذلك
 * أسوأ من تكرارٍ يُنبَّه عليه.
 */
final class SourceKey
{
    /** معرّف فيديو يوتيوب: أحد عشر محرفاً من مجموعةٍ معلومة. */
    private const YOUTUBE_ID = '[A-Za-z0-9_-]{11}';

    private function __construct() {}

    /** مفتاحُ المقارنة، أو `null` لمصدرٍ غائب. */
    public static function for(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $id = self::youtubeId($url);

        if ($id !== null) {
            return 'yt:'.$id;
        }

        // **ما ليس يوتيوب يُقارَن برابطه**، مُشذَّباً من الشرطة الأخيرة
        // ومن اختلاف حالة المضيف وحدهما — لا أكثر.
        return 'url:'.mb_strtolower(rtrim($url, '/'));
    }

    private static function youtubeId(string $url): ?string
    {
        $patterns = [
            '#youtube\.com/watch\?(?:.*&)?v=('.self::YOUTUBE_ID.')#i',
            '#youtu\.be/('.self::YOUTUBE_ID.')#i',
            '#youtube\.com/(?:embed|shorts|live|v)/('.self::YOUTUBE_ID.')#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $match) === 1) {
                return $match[1];
            }
        }

        return null;
    }
}
