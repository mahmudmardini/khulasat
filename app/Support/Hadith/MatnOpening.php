<?php

declare(strict_types=1);

namespace App\Support\Hadith;

use App\Support\Render\RenderedEvidence;

/**
 * مطلعُ الحديث نفسِه، لا مطلعُ روايته — T-116، البلاغ الثاني.
 *
 * **قائمةُ التخريج كانت تُقرأ رواةً لا أحاديث.** فطرفُ الشاهد أوّلُ كلماته
 * ({@see RenderedEvidence::excerpt()})، وأوّلُ كلمات
 * الحديث في المدوّنة إسنادُه أو صيغةُ روايته دائماً:
 *
 *   ٦. رواه البخاري، رقم ٩٠٨ — حَدَّثَنَا آدَمُ، قَالَ حَدَّثَنَا ابْنُ…
 *   ٧. رواه البخاري، رقم ٦٣٨ — عَنْ أَبِيهِ، قَالَ قَالَ رَسُولُ اللَّهِ…
 *  ١٧. رواه البخاري، رقم ٦٤٦٣ — عَنْ أَبِي هُرَيْرَةَ ـ رضى الله عنه…
 *
 * فتتشابه السطورُ ولا يُعرف منها حديثٌ من حديث، **وهي إنّما وُضعت ليُعرف**.
 *
 * **ولفظُ الطرف لفظُ المصدر حرفاً — يُقطع ولا يُبدَّل** (T-81). فهذا الصنف
 * يدلّ على موضع البداية وحده، ولا ينشئ حرفاً ولا يُعيد ترتيباً. وما لم
 * يتبيّن مطلعُه رُدّ كما دخل: طرفٌ طويلٌ أهونُ من طرفٍ مخترع.
 *
 * **وحتميٌّ بلا نموذج لغويّ** — CLAUDE.md §2 القاعدة الثالثة.
 */
final class MatnOpening
{
    /**
     * علامةُ الاقتباس في المدوّنة — بها يُحاط كلامُ النبيّ ﷺ.
     *
     * وهي في ٦٠٪ من الصفوف لا في كلّها، فلا يُبنى عليها حدُّ المتن
     * ({@see MatnExtractor})، **ويُبنى عليها مطلعُه**: إن وُجدت فما بعدها
     * كلامُه ﷺ يقيناً، وهو أدلُّ ما في الحديث عليه.
     */
    private const QUOTES = ['"', '«', '“', '”'];

    /** ذكرُ النبيّ ﷺ كما يُطبَّع — ومطلعُ المتن بعده في الغالب. */
    private const PROPHET = ['صلي', 'الله', 'عليه', 'وسلم'];

    /**
     * ما يتوسّط ذكرَ النبيّ ﷺ وكلامَه — يُتخطّى ليبدأ الطرفُ بالحديث.
     *
     * «عَنْ أَبِيهِ، قَالَ قَالَ رَسُولُ اللَّهِ ﷺ **قَالَ** إِذَا أُقِيمَتِ…»
     *
     * @var list<string>
     */
    private const PREAMBLE = [
        'قال', 'قالت', 'يقول', 'تقول', 'فقال', 'وقال', 'قالوا',
        'ثم', 'انه', 'انها', 'ان', 'رضي', 'عنه', 'عنها', 'عنهما', 'عنهم',
    ];

    /** وما دون ثلاثِ كلماتٍ لا يصلح طرفاً يُعرف به حديث. */
    private const MIN_WORDS = 3;

    private function __construct() {}

    /**
     * النصُّ من حيث يبدأ الحديثُ نفسُه.
     *
     * ويُرجع النصّ كما دخل متى لم يتبيّن المطلع.
     */
    public static function of(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return self::afterQuote($text)
            ?? self::afterProphet($text)
            ?? $text;
    }

    /**
     * ما بعد أوّل علامة اقتباس — كلامُ النبيّ ﷺ بعينه.
     */
    private static function afterQuote(string $text): ?string
    {
        $at = null;

        foreach (self::QUOTES as $quote) {
            $found = mb_strpos($text, $quote);

            if ($found !== false && ($at === null || $found < $at)) {
                $at = $found;
            }
        }

        if ($at === null) {
            return null;
        }

        return self::sufficient(trim(mb_substr($text, $at + 1)));
    }

    /**
     * ما بعد ذكر النبيّ ﷺ وما تلاه من صيغة قولٍ — حين لا اقتباسَ في الصفّ.
     */
    private static function afterProphet(string $text): ?string
    {
        $words = Words::of($text);

        foreach ($words as $index => $word) {
            $length = match (true) {
                $word['norm'] === 'ﷺ' => 1,
                Words::match($words, $index, self::PROPHET) => count(self::PROPHET),
                default => 0,
            };

            if ($length === 0) {
                continue;
            }

            $start = $index + $length;

            // وتُتخطّى صيغةُ القول بعده: «ﷺ قَالَ» و«ﷺ يَقُولُ».
            while (in_array($words[$start]['norm'] ?? '', self::PREAMBLE, true)) {
                $start++;
            }

            return isset($words[$start])
                ? self::sufficient(trim(substr($text, $words[$start]['at'])))
                : null;
        }

        return null;
    }

    /** **وطرفٌ لا يُعرف به حديثٌ ليس طرفاً** — فيُردّ النصّ كما دخل. */
    private static function sufficient(string $opening): ?string
    {
        if ($opening === '') {
            return null;
        }

        return count(Words::of($opening)) < self::MIN_WORDS ? null : $opening;
    }
}
