<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * Chooses where to cut a long recording — المواصفة §5-أ-4.
 *
 * **«عند الصمت لا عند زمن ثابت، حتى لا تُقطع الكلمة».** والقطع عند دقيقةٍ
 * محسوبة يقع في وسط كلمةٍ حتماً، فيخرج من المقطع الأوّل نصفُها ومن الثاني
 * نصفُها، **فيسمع المفرِّغ كلمتين لا يقولهما أحد** ويكتبهما في النصّ. وفي
 * درسٍ شرعيّ قد تكون تلك الكلمة اسمَ راوٍ أو لفظَ حديث.
 *
 * والحساب هنا مفصولٌ عن `ffmpeg` عمداً: اختيار نقطة القطع هو المنطق، وهو
 * ما يستحقّ اختباراً لا يحتاج ملفّ صوت.
 *
 * @see khulasah-build-spec.md §5-أ-4
 */
final class SilenceCutPoints
{
    /**
     * كم يُبحث حول النقطة المستهدَفة عن صمت، نسبةً إلى طول المقطع.
     *
     * ٢٠٪ من عشر دقائق دقيقتان. والتوسيع أكثر يُخرج مقاطع متفاوتة الطول
     * بلا فائدة، والتضييق يُفوّت السكتة الطبيعية بين الجملتين.
     */
    private const SEARCH_WINDOW_RATIO = 0.2;

    /** أقصر مقطع مقبول، نسبةً إلى طول المقطع — لئلّا يخرج مقطعٌ ثوانٍ. */
    private const MIN_SEGMENT_RATIO = 0.25;

    private function __construct() {}

    /**
     * Segment boundaries as [start, end] pairs, in order.
     *
     * @param  list<array{start: float, end: float}>  $silences  من `silencedetect`.
     * @return list<array{start: float, end: float}>
     */
    public static function segments(float $duration, array $silences, int $chunkSeconds): array
    {
        if ($duration <= 0.0) {
            return [];
        }

        $segments = [];
        $position = 0.0;

        foreach (self::cuts($duration, $silences, $chunkSeconds) as $cut) {
            $segments[] = ['start' => $position, 'end' => $cut];
            $position = $cut;
        }

        $segments[] = ['start' => $position, 'end' => $duration];

        return $segments;
    }

    /**
     * @param  list<array{start: float, end: float}>  $silences
     * @return list<float>
     */
    public static function cuts(float $duration, array $silences, int $chunkSeconds): array
    {
        if ($chunkSeconds <= 0 || $duration <= $chunkSeconds) {
            return [];
        }

        $window = $chunkSeconds * self::SEARCH_WINDOW_RATIO;
        $minSegment = $chunkSeconds * self::MIN_SEGMENT_RATIO;

        $cuts = [];
        $position = 0.0;

        while ($duration - $position > $chunkSeconds) {
            $remaining = $duration - $position;

            // **موازنةُ الذيل.** لو قُطع عند المقطع الكامل دائماً لخرج آخرُ
            // مقطعٍ ثوانيَ معدودة: تسجيلٌ من ١٢٠٠ ثانية يُقطع ٦٠٠ ثمّ ٦٠٠،
            // ومن ١٢١٨ يُقطع ٦٠٠ ثمّ ٦٠٠ ثمّ **١٨**. وذلك نداءُ تفريغٍ كامل
            // على جملةٍ ناقصة. فإن كان الباقي لا يكفي مقطعين، قُسم نصفين
            // متساويين — ويبقى كلاهما دون الحدّ.
            $target = $remaining <= $chunkSeconds + $minSegment
                ? $position + ($remaining / 2)
                : $position + $chunkSeconds;

            $cut = self::nearestSilence($silences, $target, $window, $position + $minSegment)
                // لا سكتة في النافذة: يُقطع عند الهدف. مقطعٌ مقطوعُ الكلمة
                // خيرٌ من مقطعٍ يتجاوز حدّ المزوّد فيُرفض كلّه.
                ?? $target;

            // حارسٌ ضدّ الدوران: نقطةٌ لا تتقدّم تُبقي الحلقة أبداً.
            if ($cut <= $position) {
                break;
            }

            $cuts[] = $cut;
            $position = $cut;
        }

        return $cuts;
    }

    /**
     * وسطُ أقرب سكتةٍ إلى الهدف داخل النافذة، أو `null`.
     *
     * ويُقطع في **وسط** السكتة لا في أوّلها: القطع عند أوّلها يقصّ ذيل النَفَس
     * الأخير من الكلمة، وعند آخرها يبتلع بداية الكلمة التالية.
     *
     * @param  list<array{start: float, end: float}>  $silences
     */
    private static function nearestSilence(array $silences, float $target, float $window, float $earliest): ?float
    {
        $best = null;
        $bestDistance = null;

        foreach ($silences as $silence) {
            $middle = ($silence['start'] + $silence['end']) / 2;

            if ($middle < $earliest) {
                continue;
            }

            $distance = abs($middle - $target);

            if ($distance > $window) {
                continue;
            }

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $middle;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
