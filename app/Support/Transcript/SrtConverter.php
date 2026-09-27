<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Support\Arabic;

/**
 * Turns an SRT/VTT caption file into paragraphed prose — المواصفة §5-أ-3.
 *
 * **تجريد التوقيتات وحده لا يكفي، وهذا فخّ المرحلة كلّها.** الترجمة الآلية
 * من يوتيوب تُخرج أسطراً متداخلة بنافذة متحرّكة، فيتكرّر آخرُ كلّ سطر في
 * أوّل الذي يليه. ومن ينسخ الملفّ كما هو يحصل على نصّ ضعف طوله، **فيدفع
 * ضعف الكلفة** ويُربك النموذج بتكرار لم يقله المتحدّث.
 *
 * والخطوات الخمس بترتيب المواصفة:
 *   ١. إزالة أرقام الكتل وأسطر التوقيت ووسوم `<c>` و`<00:00:00.000>`.
 *   ٢. إزالة التداخل — **تُقارَن الكلمات لا الأحرف**.
 *   ٣. حذف الأسطر المطابقة تماماً لسابقتها.
 *   ٤. الفقرات: فجوة تتجاوز ثانيتين، أو بلوغ ٤٠٠ كلمة.
 *   ٥. تطبيع المسافات، **مع الإبقاء على التشكيل**.
 *
 * والتوقيتات تُقرأ ولا تُهمَل في الخطوة ١: الخطوة ٤ تحتاجها لتقسيم الفقرات،
 * فلو حُذفت مع أوّل تنظيف لضاع الفاصل الوحيد بين موضوع وموضوع.
 *
 * @see khulasah-build-spec.md §5-أ-3
 */
final class SrtConverter
{
    /** المواصفة §5-أ-3 الخطوة ٤: فقرة جديدة عند فجوة تتجاوز ثانيتين. */
    public const PARAGRAPH_GAP_MS = 2_000;

    /** والحدّ الأعلى للفقرة، حتى لا يخرج جدارُ نصّ بلا فاصل. */
    public const PARAGRAPH_MAX_WORDS = 400;

    /**
     * كم كلمةً من ذيل المخرَج تُقارَن بأوّل الكتلة التالية.
     *
     * نافذة يوتيوب المتحرّكة قصيرة (سطر أو سطران)، والاكتفاء بذيلٍ محدود
     * يمنع أن يُحذف نصٌّ لأنّه صادف تكراراً بعيداً في المحاضرة.
     */
    private const TAIL_WINDOW_WORDS = 40;

    /** محارف تحكّم غير مرئية: ليست تشكيلاً ولا معنى، وتُثقل التوكنز. */
    private const INVISIBLES = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

    private function __construct() {}

    /**
     * @return string فقرات يفصل بينها سطر فارغ، بلا تكرار النافذة المتحرّكة.
     */
    public static function toText(string $srt): string
    {
        $cues = self::parseCues($srt);

        /** @var list<list<string>> $paragraphs الفقرة قائمةُ كلماتها. */
        $paragraphs = [];
        /** @var list<string> $current */
        $current = [];
        /** @var list<string> $tail آخر ما خرج، للمقارنة في الخطوة ٢. */
        $tail = [];
        $previousEnd = null;

        foreach ($cues as $cue) {
            // ٤. الفاصل يُقرَّر قبل الكتابة، وبتوقيت الكتلة لا بطولها. والفجوة
            //    تُقاس من نهاية الكتلة السابقة وإن حُذف نصّها في الخطوة ٢،
            //    فالصمت واقعٌ في الصوت لا في النصّ.
            $isNewParagraph = $previousEnd !== null
                && $cue['start'] - $previousEnd > self::PARAGRAPH_GAP_MS;

            $previousEnd = $cue['end'];

            // ٢ و٣. إزالة التداخل، ومنه تُحذف الكتلة المطابقة تماماً لأنّها
            //       تداخلٌ تامّ فلا يبقى منها شيء.
            $words = self::stripOverlap($tail, $cue['words']);

            if ($words === []) {
                continue;
            }

            if ($isNewParagraph && $current !== []) {
                $paragraphs[] = $current;
                $current = [];
            }

            $current = [...$current, ...$words];
            $tail = array_slice([...$tail, ...$words], -self::TAIL_WINDOW_WORDS);

            if (count($current) >= self::PARAGRAPH_MAX_WORDS) {
                $paragraphs[] = $current;
                $current = [];
            }
        }

        if ($current !== []) {
            $paragraphs[] = $current;
        }

        return implode("\n\n", array_map(
            static fn (array $words): string => implode(' ', $words),
            $paragraphs,
        ));
    }

    /**
     * ١ و٥. الكتل نصّاً وتوقيتاً، بلا وسوم ولا أرقام كتل.
     *
     * @return list<array{start: int, end: int, words: list<string>}>
     */
    private static function parseCues(string $srt): array
    {
        $srt = preg_replace('/^\x{FEFF}/u', '', $srt) ?? $srt;
        $srt = str_replace(["\r\n", "\r"], "\n", $srt);

        $blocks = preg_split('/\n[ \t]*\n/', trim($srt)) ?: [];

        $cues = [];

        foreach ($blocks as $block) {
            $lines = explode("\n", trim($block));

            // رقم الكتلة إن وُجد. وVTT يخرج بلا أرقام، فلا يُفترض وجودها.
            if (preg_match('/^\d+$/', trim($lines[0] ?? ''))) {
                array_shift($lines);
            }

            $timing = array_shift($lines);

            if ($timing === null || ! preg_match(
                '/(\d+):([0-5]\d):([0-5]\d)[.,](\d{1,3})\s*-->\s*(\d+):([0-5]\d):([0-5]\d)[.,](\d{1,3})/',
                $timing,
                $matches,
            )) {
                // كتلة بلا توقيت مقروء — تُترك ولا تُخمَّن. وإدخالُ نصٍّ لا
                // نعرف موضعه من الدرس أسوأ من إسقاطه.
                continue;
            }

            $words = self::cleanWords(implode(' ', $lines));

            if ($words === []) {
                continue;
            }

            $cues[] = [
                'start' => self::toMilliseconds($matches[1], $matches[2], $matches[3], $matches[4]),
                'end' => self::toMilliseconds($matches[5], $matches[6], $matches[7], $matches[8]),
                'words' => $words,
            ];
        }

        return $cues;
    }

    /**
     * ١ و٥. تنقية نصّ الكتلة إلى كلمات.
     *
     * والتشكيل يبقى: النصّ يُعرض على مراجع شرعي ويُقتبس منه، وحذفُ الحركات
     * هنا يجعل «مَنْ» و«مِنْ» شيئاً واحداً. والتطبيع للمطابقة وحدها
     * ({@see Arabic::normalize})، لا للنصّ المحفوظ.
     *
     * @return list<string>
     */
    private static function cleanWords(string $text): array
    {
        // وسوم الترجمة: <c>…</c> و<00:00:23.500> و<i> — تُستبدل بمسافة لا
        // تُحذف، وإلّا التصقت الكلمتان حول الوسم فصارتا كلمةً لا وجود لها.
        $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? $text;

        // تجاوزات التنسيق {\an8} في الملفّات المحوَّلة عن ASS.
        $text = preg_replace('/\{[^}]*\}/u', ' ', $text) ?? $text;

        $text = preg_replace(self::INVISIBLES, '', $text) ?? $text;

        return self::words($text);
    }

    /**
     * ٢. حذف ما تكرّر من أوّل الكتلة لأنّه آخرُ ما قبلها.
     *
     * تُؤخذ **أطول** مطابقة: النافذة المتحرّكة تُعيد عدّة كلمات، والاكتفاء
     * بأقصرها يُبقي بعض التكرار. والمقارنة على المطبَّع لا على الأصل، فالمفرِّغ
     * قد يُشكّل كلمةً في كتلة ويترك تشكيلها في التي بعدها.
     *
     * @param  list<string>  $tail
     * @param  list<string>  $words
     * @return list<string>
     */
    private static function stripOverlap(array $tail, array $words): array
    {
        if ($tail === [] || $words === []) {
            return $words;
        }

        $tailKeys = array_map(self::comparisonKey(...), $tail);
        $wordKeys = array_map(self::comparisonKey(...), $words);

        for ($length = min(count($tailKeys), count($wordKeys)); $length >= 1; $length--) {
            if (array_slice($tailKeys, -$length) === array_slice($wordKeys, 0, $length)) {
                return array_slice($words, $length);
            }
        }

        return $words;
    }

    /**
     * صورة الكلمة التي تُقارَن بها، لا التي تُكتب.
     *
     * {@see Arabic::normalize} هي التنفيذ الوحيد للتطبيع في المشروع، فلا
     * تُنسخ خطوة منها هنا. وقد تُخرج الكلمةَ الواحدةَ كلمتين إن كان فيها
     * ترقيم داخليّ («قال:الحمد») — فتفشل المطابقة حينها، **وتفشل آمنةً**:
     * يبقى التكرار ولا يُحذف كلام لم يتكرّر.
     */
    private static function comparisonKey(string $word): string
    {
        return Arabic::normalize($word);
    }

    /**
     * ٥. تطبيع المسافات: كلّ ما يُعدّ فراغاً فاصلٌ واحد.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        return array_values(array_filter(
            preg_split('/\s+/u', trim($text)) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }

    private static function toMilliseconds(string $hours, string $minutes, string $seconds, string $fraction): int
    {
        return ((int) $hours * 3_600_000)
            + ((int) $minutes * 60_000)
            + ((int) $seconds * 1_000)
            + (int) str_pad($fraction, 3, '0');
    }
}
