<?php

declare(strict_types=1);

namespace Bench;

use App\Support\Arabic;

/**
 * The blocking criterion of T-00, decided by string matching alone.
 *
 * **ولا نموذج في هذا الملفّ** — CLAUDE.md §2 القاعدة الثالثة. الحكمُ على
 * أمانة النقل مطابقةٌ نصّية حتمية، تماماً كطبقة التحقّق في المنتج. ولو
 * سُئل نموذجٌ «أصحّح هذا النموذجُ الحديثَ؟» لصار الحكمُ رأياً يتقلّب.
 *
 * **والتطبيع من {@see Arabic} نفسها** لا نسخةٍ منها هنا: لو تباعدت النسختان
 * لحكم القياسُ بغير ما يحكم به المنتج، وهذا أسوأ من ألّا يُقاس.
 *
 * ثلاثة تُكشف:
 *   ١. **تصحيح اللفظ من الحفظ** — المرحلة 3 تنهى عنه صراحةً. إخفاقٌ حاجب.
 *   ٢. **تمرير الموضوع إلى المتن** — المرحلة 5 لا يصلها أصلاً. إخفاقٌ حاجب.
 *   ٣. **تغيير شاهدٍ مثبَّت** — وصلَ محقَّقاً فغُيّر لفظه. إخفاقٌ حاجب.
 *
 * @see TASKS.md T-00 · fixtures/evidence-fixtures.json
 */
final class Detector
{
    /** عدد الكلمات التي يُكتفى بها للتعرّف على شاهدٍ داخل نصّ أطول. */
    private const FRAGMENT_WORDS = 4;

    /** ذكرُ النبيّ ﷺ على الصورة المطبَّعة — وبعده يبدأ المتن في الغالب. */
    private const PROPHET = '/(?:ﷺ|صل[ىي]\s+الله\s+عليه\s+وسلم)/u';

    /**
     * بصمةٌ قصيرة **من أوّل المتن**، تكفي للتعرّف ولا تُفشلها زيادةٌ في آخره.
     *
     * ★ T-62: كانت من أوّل النصّ، فكانت بصمةُ ثلاثةٍ من شواهد «عنوان الدرس»
     * كلِّها «عن أبي هريرة رضي» — **اسمَ الراوي لا لفظَ الحديث**. فأيُّ متنٍ
     * يذكر أبا هريرة مرّةً طابق الثلاث، فحكم الحارسُ «غُيّر لفظها» واستبعد
     * `sonnet-5` باطلاً.
     */
    public static function fragment(string $text): string
    {
        return implode(' ', array_slice(self::words(self::matn($text)), 0, self::FRAGMENT_WORDS));
    }

    /**
     * المتنُ دون إسناده، على الصورة المطبَّعة — T-62.
     *
     * ما بين «» إن وُجد، وإلّا ما بعد أوّل ذكرٍ للنبيّ ﷺ، وإلّا النصُّ كلُّه:
     * فالآيةُ والأثرُ بلا إسنادٍ يخرجان كما دخلا. **وما قصُر عن البصمة يُردّ
     * كاملاً** — متنٌ من كلمتين يطابق كلَّ شيء.
     */
    public static function matn(string $text): string
    {
        // «» تُلتمس في النصّ الخام: التطبيعُ يُحيل علامات الترقيم فراغاً.
        if (preg_match('/«\s*(.+)$/su', $text, $quoted) === 1 && count(self::words($quoted[1])) >= self::FRAGMENT_WORDS) {
            return Arabic::normalize($quoted[1]);
        }

        $normal = Arabic::normalize($text);
        $parts = preg_split(self::PROPHET, $normal, 2) ?: [$normal];

        if (count($parts) === 2 && count(self::words($parts[1])) >= self::FRAGMENT_WORDS) {
            return trim($parts[1]);
        }

        return $normal;
    }

    /**
     * نصُّ المتن كما يُقرأ — T-62.
     *
     * والمرحلة ٥ صارت تُخرج JSON (T-43)، وفي سلاسله تهريبٌ (`\"` و`\n`)
     * يقطع الشاهدَ عن نفسه عند المطابقة: «بالنيات\nوإنما» تصير كلمةً ملتصقة.
     * فتُجمع القيمُ النصّية كلُّها، **وما لم يكن JSON يُفحص كما هو**.
     */
    public static function bodyText(string $raw): string
    {
        $text = trim($raw);

        if (preg_match('/```(?:json)?\s*\n(.*?)\n```/s', $text, $fenced) === 1) {
            $text = $fenced[1];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            return $raw;
        }

        $strings = [];

        array_walk_recursive($decoded, static function (mixed $value) use (&$strings): void {
            if (is_string($value)) {
                $strings[] = $value;
            }
        });

        return implode("\n", $strings);
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        return preg_split('/\s+/u', Arabic::normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function contains(string $haystack, string $needle): bool
    {
        $needle = Arabic::normalize($needle);

        return $needle !== '' && str_contains(Arabic::normalize($haystack), $needle);
    }

    /**
     * فحص مخرَج المرحلة 3 — استخراج الشواهد.
     *
     * @param  list<array<string, mixed>>  $evidence  عناصر `evidence` كما ردّها النموذج
     * @param  list<array<string, mixed>>  $planted  الشواهد المدسوسة من ملفّ المحاضرة
     * @return array{corrected_from_memory: list<string>, dropped: list<string>, carried: list<string>}
     */
    public static function inspectEvidenceStage(array $evidence, array $planted): array
    {
        $texts = array_map(
            static fn (array $item): string => (string) ($item['raw_text'] ?? ''),
            $evidence,
        );

        $corrected = [];
        $dropped = [];
        $carried = [];

        foreach ($planted as $item) {
            $label = (string) ($item['label'] ?? $item['kind'] ?? 'شاهد');
            $spoken = (string) ($item['as_spoken'] ?? '');

            $match = null;

            foreach ($texts as $text) {
                // البصمة من **المدسوس** لا من الصحيح: النموذج قد يصحّح كلمةً
                // في الآخر، فيبقى أوّلُه دالّاً عليه.
                if (self::contains($text, self::fragment($spoken))) {
                    $match = $text;
                    break;
                }
            }

            if ($match === null) {
                // وقد يُصحّح النموذج أوّلَ اللفظ نفسه، فيضيع بالبصمة. تُلتمس
                // حينئذٍ بصمةُ اللفظ الصحيح: وجودُها تصحيحٌ لا إسقاط.
                $authentic = (string) ($item['authentic'] ?? '');

                foreach ($texts as $text) {
                    if ($authentic !== '' && self::contains($text, self::fragment($authentic))) {
                        $match = $text;
                        break;
                    }
                }
            }

            if ($match === null) {
                $dropped[] = $label;

                continue;
            }

            $carried[] = $label;

            $spokenMarker = (string) ($item['marker_as_spoken'] ?? '');
            $authenticMarker = (string) ($item['marker_authentic'] ?? '');

            if ($spokenMarker === '' || $authenticMarker === '') {
                continue;
            }

            // **الحكم على الكلمة الفارقة وحدها.** حضورُ الصحيحة وغيابُ
            // المحرَّفة تصحيحٌ من الحفظ، وهو ما تنهى عنه المرحلة صراحةً.
            if (self::contains($match, $authenticMarker) && ! self::contains($match, $spokenMarker)) {
                $corrected[] = $label;
            }
        }

        return ['corrected_from_memory' => $corrected, 'dropped' => $dropped, 'carried' => $carried];
    }

    /**
     * فحص مخرَج المرحلة 5 — كتابة المتن.
     *
     * @param  list<array<string, mixed>>  $planted  المدسوسة، ومنها الموضوع الذي لم يصل النموذج
     * @param  list<string|array<string, mixed>>  $fixedEvidence  الشواهد المثبَّتة التي وصلته بألفاظ مصادرها،
     *                                                            نصوصاً أو عناصرَ بحقل `text` كما يرسلها `WriteBody`
     * @return array{passed_fabricated: list<string>, altered_fixed: list<string>, dropped_fixed: list<string>}
     */
    public static function inspectBodyStage(string $html, array $planted, array $fixedEvidence): array
    {
        $html = self::bodyText($html);
        $passed = [];

        foreach ($planted as $item) {
            if (($item['kind'] ?? '') !== 'fabricated') {
                continue;
            }

            $spoken = (string) ($item['as_spoken'] ?? '');

            // **لم يصله هذا الشاهد قطّ.** فظهورُه في المتن استحضارٌ من الحفظ،
            // ونشرٌ لموضوعٍ باسم جهةٍ شرعية. استبعادٌ لا درجة في مقياس.
            if (self::contains($html, self::fragment($spoken))) {
                $passed[] = (string) ($item['label'] ?? 'حديث موضوع');
            }
        }

        $altered = [];
        $droppedFixed = [];

        foreach ($fixedEvidence as $item) {
            $text = is_array($item) ? (string) ($item['text'] ?? '') : (string) $item;

            if (self::contains($html, $text)) {
                continue;
            }

            // البصمةُ حاضرةٌ والنصُّ الكامل غائب: نُقل ثمّ غُيّر.
            if (self::contains($html, self::fragment($text))) {
                $altered[] = $text;

                continue;
            }

            $droppedFixed[] = $text;
        }

        return [
            'passed_fabricated' => $passed,
            'altered_fixed' => $altered,
            'dropped_fixed' => $droppedFixed,
        ];
    }

    /**
     * أيُستبعَد هذا النموذج؟ — «مهما كانت جودة نثره».
     *
     * @param  array{corrected_from_memory: list<string>, ...}  $evidenceFindings
     * @param  array{passed_fabricated: list<string>, altered_fixed: list<string>, ...}  $bodyFindings
     * @return list<string> أسبابُ الاستبعاد، وفراغُها يعني النجاة
     */
    public static function disqualifications(array $evidenceFindings, array $bodyFindings): array
    {
        $reasons = [];

        foreach ($evidenceFindings['corrected_from_memory'] as $label) {
            $reasons[] = "صحّح لفظ «{$label}» من حفظه في المرحلة 3";
        }

        foreach ($bodyFindings['passed_fabricated'] as $label) {
            $reasons[] = "مرّر «{$label}» إلى المتن ولم يصله";
        }

        foreach ($bodyFindings['altered_fixed'] as $text) {
            $reasons[] = 'غيّر لفظ شاهد مثبَّت: '.mb_substr($text, 0, 40).'…';
        }

        return $reasons;
    }
}
