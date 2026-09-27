<?php

declare(strict_types=1);

namespace App\Support\Hadith;

use App\Support\Arabic;

/**
 * The chain of transmission, stripped — T-53.
 *
 * **المدوّنة تخزّن الحديث بسنده كاملاً**، فكان يُنشر كما هو: «حَدَّثَنَا
 * مَحْمُودُ بْنُ غَيْلاَنَ، قَالَ حَدَّثَنَا أَبُو عَاصِمٍ، عَنْ جَرِيرِ بْنِ
 * حَازِمٍ…» ثمّ المتن ثمّ «تَابَعَهُ يُونُسُ». وقارئُ صفحةٍ منشورة يريد المتن،
 * والسندُ صناعةُ محدِّثٍ لا مادّةُ ملخّص.
 *
 * **وهذا لا يُصلحه نموذج**: المرحلة ٥ ممنوعةٌ من تغيير لفظ الشاهد («انقلها
 * حرفاً بحرف» — PROMPT-PACK)، فالنصّ يخرج كما يدخل. فالإصلاح عند المصدر.
 *
 * **وحتميٌّ نصّيٌّ بلا نموذج لغويّ** — CLAUDE.md §2 القاعدة الثالثة. ومن سأل
 * نموذجاً «أين ينتهي السند» فقد أدخل النموذجَ في طبقة التحقّق من بابٍ خلفيّ.
 *
 * **والمرساة هي الصلاة على النبيّ ﷺ**، لا عدُّ الحلقات: حلقاتُ السند تتفاوت
 * (ثلاث إلى تسع)، وصيغُ التحمّل تتداخل مع المتن («قَالَ سَمِعْتُ»)، أمّا ذكرُ
 * النبيّ ﷺ فأوّلُ موضعٍ يقع فيه هو مطلعُ المتن في الغالب الأعمّ.
 *
 * **ولا يُخترع حرفٌ ولا يُعاد ترتيب.** ما يُتيقَّن منه يُقطع، وما سواه يخرج
 * كما دخل: حديثٌ بلا مرساةٍ أو بلا سندٍ يُترك كاملاً. **وفواتُ قطعٍ أهونُ
 * من قطعِ متنٍ** — الأوّل إطالةٌ، والثاني تحريفُ نصٍّ يُنشر باسم جهةٍ شرعية.
 *
 * ═══ T-116 — بلاغُ مالك المنتج، ١٢ أيلول ٢٠٢٦ ═══
 *
 * **ومع ذلك كان الفوات يُنشر**: ٦٠٩١ صفّاً من ٣٥٩٨٢ (١٦٫٩٪) تخرج بلا قطع،
 * فتُقرأ الصفحةُ أسانيدَ لا أحاديث. وليست علّةً واحدة بل أربعاً قِيست كلُّها
 * على صفوفٍ بعينها، **وحدُّ القطع نفسُه لم يتغيّر**: الإسنادُ وذيلُ المصنّف
 * يُقطعان، وما بقي يبقى حرفاً بحرف — قرارُ مالك المنتج في هذه المهمّة.
 *
 *   ١. **سندان متوازيان** والمتنُ في الثاني (البخاري ٩٠٨): المرساةُ الأولى
 *      في السند الأوّل، فيقع القطع في غير موضعه. فتُجرَّب المراسي بالترتيب
 *      ولا يُكتفى بأولاها.
 *   ٢. **صفٌّ بلا مرساةٍ أصلاً** (البخاري ٦٥٧٠): «يا رسول الله» و«بشفاعتي»
 *      ولا «صلى الله عليه وسلم» فيه. فيُقطع الإسنادُ المحضُ إن بان حدُّه.
 *   ٣. **ذيلُ المصنّف** (البخاري ١٠): «قَالَ أَبُو عَبْدِ اللَّهِ وَقَالَ أَبُو
 *      مُعَاوِيَةَ حَدَّثَنَا دَاوُدُ…» بعد المتن، وفيه «حدّثنا» فيُوقع الحارس.
 *   ٤. **والحارسُ نفسُه كان يُخطئ** (الترمذي ٢٦١٦): «أَخْبِرْنِي بِعَمَلٍ
 *      يُدْخِلُنِي الْجَنَّةَ» من كلام معاذٍ نفسِه، تُطبَّع «اخبرني» فتُقرأ
 *      صيغةَ تحمّل. **فصيغةُ التحمّل تفتتح جملةً**، وما ورد منها في وسط
 *      الكلام فهو من المتن — وهذا هو التضييق الوحيد على حارس T-68.
 */
final class MatnExtractor
{
    /*
     * **الأنماط كلُّها على الصورة المطبَّعة** ({@see Arabic::normalize}) لا
     * على المكتوبة. وكانت على المجرَّدة من التشكيل وحدها فأخطأت «أَخْبَرَنَا»
     * — الهمزةُ تبقى بعد نزع الحركات ولا تبقى بعد التطبيع — فبقي ٧١٢٠ سنداً
     * غيرَ مقطوع. **وخطوةُ تطبيعٍ ناقصةٌ إخفاقٌ صامت**: النصّ يخرج كاملاً ولا
     * يقول أحدٌ إنّ شيئاً لم يُقطع.
     *
     * و`(?=\s|$)` بدل `\b`: حدُّ الكلمة في PCRE يتبع إعداد UCP، وبناءُ قاعدةٍ
     * على إعدادٍ قد يتبدّل بين نسختين من الامتداد ليس بناءً.
     */

    /** ذكرُ النبيّ ﷺ — مرساةُ حدّ السند. و«صلى» تصير «صلي» بالتطبيع (خطوة ٤). */
    private const PROPHET = '/(?:صل[ىي]\s+الله\s+عليه\s+وسلم|ﷺ)/u';

    /** صيغُ التحمّل التي تصدّر حلقةَ سند. */
    private const LINK = '/^(?:ح\s+)?و?(?:حدثنا|حدثني|حدثناه|حدثنيه|اخبرنا|اخبرني|انبانا|انباني|نا|ثنا)(?=\s|$)/u';

    /** صيغةُ العنعنة: بها تُوصل الحلقة، وبها يُذكر الصحابيّ. */
    private const ANAN = '/^(?:عن|وعن)(?=\s|$)/u';

    /** عنعنةٌ إلى النبيّ ﷺ نفسِه — لا إلى راوٍ عنه. */
    private const TO_PROPHET = '/^(?:عن|وعن)\s+(?:النبي|رسول\s+الله)(?=\s|$)/u';

    /** صدرٌ متدلٍّ من حلقةٍ سابقة: «يقول سمعت…» — يُنزع ولا يُبقي. */
    private const DANGLING_HEAD = '/^(?:يقول|تقول|قال|قالت)\s+(?=سمعت\s)/u';

    /**
     * لواحقُ المتابعات — تُذكر بعد المتن، وليست منه.
     *
     * ★ وكانت بـ`\b`، **فخالفت ما قرّره رأسُ هذا الصنف** وسقطت في الحاوية:
     * PCRE 10.42 لا يعدّ الضمّةَ حرفَ كلمة، فلا حدَّ بعد «تَابَعَهُ» ولا
     * قطع، و10.47 على الجهاز يعدّها — فمرّ الاختبارُ هنا وسقط هناك.
     */
    private const TRAILING = '/\s*(?:تَابَعَهُ|وَتَابَعَهُ|تابعه|وتابعه)(?=\s|$|\p{P}).*$/u';

    /**
     * صيغُ التحمّل **كلمةً مفردة**، مطبَّعةً — T-116.
     *
     * وهذه تُقاس على الكلمة لا على صدر المقطع: بها يُعرف موضعُ الصيغة داخل
     * الكلام، لا كونُها تصدّره وحسب. **وليس فيها «نا» و«ثنا»** الواردتان في
     * {@see self::LINK}: كلتاهما تصلح كلمةً في متنٍ عربيّ، وصدرُ المقطع يحرسهما
     * هناك ولا حارسَ لهما هنا.
     *
     * @var list<string>
     */
    private const LINK_WORDS = [
        'حدثنا', 'حدثني', 'حدثناه', 'حدثنيه',
        'اخبرنا', 'اخبرني', 'انبانا', 'انباني',
    ];

    /**
     * كنى المصنّفين — بها يفتتح صاحبُ الكتاب كلامَه **بعد** المتن، T-116.
     *
     * «قَالَ أَبُو عِيسَى هَذَا حَدِيثٌ حَسَنٌ صَحِيحٌ» حكمُ الترمذيّ على ما
     * روى، لا من الحديث. وهي في المدوّنة ٤٢٩٤ موضعاً.
     *
     * **ولا تُقطع بالكنية وحدها بل بنقطةٍ تسبقها**: «قَالَ أَبُو عَبْدِ اللَّهِ»
     * ترد في متنٍ حقيقيّ — أبو داود ٤٩٧٢: «قَالَ أَبُو مَسْعُودٍ لأَبِي عَبْدِ
     * اللَّهِ أَوْ قَالَ أَبُو عَبْدِ اللَّهِ لأَبِي مَسْعُودٍ…». وقياسُ
     * المدوّنة: ٤١٩٤ موضعاً تسبقه نقطةٌ فيُقطع، و١٠٠ لا تسبقه فيبقى.
     *
     * @var list<list<string>>
     */
    private const COMPILER_TAILS = [
        ['قال', 'ابو', 'عبد', 'الله'],      // البخاري
        ['قال', 'ابو', 'عيسي'],             // الترمذي
        ['قال', 'ابو', 'داود'],             // أبو داود
        ['قال', 'ابو', 'عبد', 'الرحمن'],    // النسائي
    ];

    /**
     * ما يدلّ على كلامٍ مرويّ لا على حلقةِ إسناد — T-116.
     *
     * وبها يُعرف المقطعُ الذي يبدأ عنده المتنُ حين لا مرساةَ في الصفّ:
     * «عَنْ سَعِيدِ بْنِ أَبِي سَعِيدٍ الْمَقْبُرِيِّ» حلقةٌ محضة، و«عَنْ أَبِي
     * هُرَيْرَةَ… أَنَّهُ قَالَ قُلْتُ يَا رَسُولَ اللَّهِ» حلقةٌ ومتنٌ معاً.
     *
     * @var list<string>
     */
    private const SPEECH_WORDS = [
        'قال', 'قالت', 'قلت', 'قلنا', 'سمعت', 'سمعنا', 'يقول', 'تقول',
        'ان', 'انه', 'انها', 'انهم', 'كان', 'كنت', 'بينما',
    ];

    /** وحلقةُ الإسناد قصيرةٌ بطبعها: اسمٌ ونسب. وما طال فليس حلقةً محضة. */
    private const MAX_CHAIN_WORDS = 8;

    /** وأقلُّ ما يُعدّ متناً — كعدد {@see NarrationFormulas::MIN_MATN_WORDS}. */
    private const MIN_MATN_WORDS = 3;

    private function __construct() {}

    /**
     * المتن وحده، ومعه ذكرُ الصحابيّ إن أمكن بلا اختراع.
     *
     * ويُرجع النصّ كما دخل متى لم يُتيقَّن من الحدّ.
     */
    public static function extract(string $text): string
    {
        $text = self::withoutTrailing(trim($text));

        if ($text === '') {
            return '';
        }

        $segments = preg_split('/،\s*/u', $text) ?: [];

        // **حديثٌ بلا فواصل لا سندَ فيه يُقطع** — أو سندُه غيرُ مفصول،
        // وقطعُه حينئذٍ تخمين.
        if (count($segments) < 2) {
            return $text;
        }

        // **السندُ يجب أن يكون سنداً**: إن لم تكن الحلقةُ الأولى صيغةَ تحمّلٍ
        // ولا عنعنة فالنصّ ليس على شكل المدوّنة، ولا يُقطع منه شيء.
        if (! self::isChainLink($segments[0])) {
            return $text;
        }

        // **كلُّ مرساةٍ بدورها** — T-116 العلّة الأولى: السندان المتوازيان
        // مرساتُهما الأولى في الأوّل والمتنُ في الثاني.
        foreach (self::anchorIndexes($segments) as $anchor) {
            $matn = self::cutAt($segments, $anchor, $text);

            if ($matn !== null) {
                return $matn;
            }
        }

        // **ولا مرساةَ في الصفّ كلِّه** — T-116 العلّة الثانية.
        return self::withoutBareChain($segments) ?? $text;
    }

    /**
     * القطعُ عند مرساةٍ بعينها — أو `null` إن لم يصحّ عندها، فتُجرَّب ما بعدها.
     */
    private static function cutAt(array $segments, int $anchor, string $text): ?string
    {
        // المتنُ من أوّل حرف: لا شيء يُقطع بيقين.
        if ($anchor === 0) {
            return $text;
        }

        $start = self::keepsCompanion($segments, $anchor) ? $anchor - 1 : $anchor;

        if ($start === 0) {
            return $text;
        }

        return self::assemble($segments, $start);
    }

    /**
     * المتنُ من مقطعٍ فصاعداً، مفحوصاً بحارسَي T-68 وT-116.
     *
     * @param  int|null  $boundary  حدُّ الصدر الذي لا تجوز فيه صيغةُ تحمّل —
     *                              وهو موضعُ المرساة، أو آخرُ المقطع الأوّل
     *                              حين لا مرساة.
     */
    private static function assemble(array $segments, int $start, ?int $boundary = null): ?string
    {
        $matn = trim(implode('، ', array_slice($segments, $start)));

        if ($matn === '') {
            return null;
        }

        $words = Words::of($matn);
        $matn = self::withoutHeadChain($matn, $words, $boundary ?? self::anchorWord($words) ?? 0);

        if ($matn === null) {
            return null;
        }

        /*
         * ★★ **حارسُ ما بعد القطع** — T-68، وهو أهمّ ما في هذا الصنف.
         *
         * فبلاغُ مالك المنتج أظهر ثالثاً لم يُحسب في T-53: لا متناً نظيفاً
         * ولا نصّاً تامّاً، بل **صدراً مشوّهاً** — «سَلَمَةَ عَنْ أَبِي
         * هُرَيْرَةَ… وَحَدَّثَنَا أَبُو الْيَمَانِ».
         *
         * **ونصٌّ مبتورٌ يُنشر باسم جهةٍ شرعية أخطرُ من نصٍّ مطوَّل.** فما
         * بقي فيه سندٌ موازٍ يُردّ، ثمّ تُجرَّب المرساةُ التي تليه (T-116):
         * يصير الإخفاقُ فواتاً لا تشويهاً، ولا يُفوَّت ما يمكن قطعُه.
         */
        if (self::hasParallelChain($matn)) {
            return null;
        }

        /*
         * **وما لا يبلغ ثلاثَ كلماتٍ ليس متناً** — والعددُ عددُ
         * {@see NarrationFormulas}: «إنما الأعمال بالنيات» ثلاثٌ وهو حديث تامّ.
         *
         * وصفوفُ الإحالة في المدوّنة متنُها كلمةٌ واحدة: «حَدَّثَنَا يَحْيَى
         * بْنُ مُوسَى، … مِثْلَهُ» تحيل على ما قبلها ولا متنَ لها. فقطعُ
         * إسنادها يُخرج «مِثْلَهُ» شاهداً، **ونشرُ كلمةٍ باسم حديثٍ أسوأُ من
         * نشر إسنادٍ معها** — فتُردّ كاملةً كما كانت.
         */
        if (count(explode(' ', Arabic::normalize($matn))) < self::MIN_MATN_WORDS) {
            return null;
        }

        return self::withoutDanglingHead($matn);
    }

    /**
     * ذيلُ المتابعات وذيلُ المصنّف — يُقطعان قبل كلّ شيء، T-116.
     *
     * **وذيلُ المصنّف لا يُقطع إلّا بنقطةٍ تسبقه**: هو جملةٌ تُستأنف بعد
     * انغلاق المتن، لا كلمةٌ تقع في وسطه. {@see self::COMPILER_TAILS}
     */
    private static function withoutTrailing(string $text): string
    {
        $words = Words::of($text);

        foreach ($words as $index => $word) {
            // **والذيلُ لا يفتتح الصفّ**: «قَالَ أَبُو دَاوُدَ قُرِئَ عَلَى
            // الْحَارِثِ…» كلامُ المصنّف **قبل** إسناده لا بعد متنه، وقطعُه
            // من أوّله يُفرغ الصفَّ كلَّه — ٤٨ صفّاً، أبو داود ٣٩١٤ وأخواته.
            if ($word['at'] === 0 || ! self::opensSentence($text, $word['at'])) {
                continue;
            }

            foreach (self::COMPILER_TAILS as $tail) {
                if (Words::match($words, $index, $tail)) {
                    $text = rtrim(substr($text, 0, $word['at']));

                    break 2;
                }
            }
        }

        return trim(preg_replace(self::TRAILING, '', trim($text)) ?? $text);
    }

    /**
     * مواضعُ المراسي كلُّها بالترتيب — أوّلُ حلقةٍ يُذكر فيها النبيّ ﷺ فما بعدها.
     *
     * @param  list<string>  $segments
     * @return list<int>
     */
    private static function anchorIndexes(array $segments): array
    {
        $indexes = [];

        foreach ($segments as $index => $segment) {
            if (preg_match(self::PROPHET, Arabic::normalize($segment)) === 1) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * **الحلقةُ السابقة تُبقى إن كانت عنعنةً** — فهي ذكرُ الصحابيّ، ومنها
     * تخرج الصيغة المألوفة: «عن أبي هريرة، عن النبيّ ﷺ قال…».
     *
     * وما لم تكن عنعنةً تُترك: استخراجُ الاسم منها بناءُ نصٍّ لا قطعُه.
     *
     * ★ **ولا تُبقى إن كان مطلعُ المتن يحمل الصحابيَّ بنفسه.** فـ«عَنْ أَبِي
     * هُرَيْرَةَ رضي الله عنه قَالَ قَالَ رَسُولُ اللَّهِ ﷺ…» حلقةٌ فيها
     * الصحابيُّ والمتن معاً، وإبقاءُ ما قبلها يُقحم تابعيّاً في صدر الشاهد.
     * وإنّما تُبقى حين يكون المطلعُ وصلاً إلى النبيّ ﷺ نفسِه: «عن النبيّ ﷺ».
     *
     * @param  list<string>  $segments
     */
    private static function keepsCompanion(array $segments, int $anchor): bool
    {
        if ($anchor < 1) {
            return false;
        }

        $head = Arabic::normalize(trim($segments[$anchor]));

        if (preg_match(self::ANAN, $head) === 1 && preg_match(self::TO_PROPHET, $head) !== 1) {
            return false;
        }

        return preg_match(self::ANAN, Arabic::normalize(trim($segments[$anchor - 1]))) === 1;
    }

    /**
     * **قطعٌ داخل مقطع المرساة** — T-78، حين تكون حلقاتُه الأخيرة بلا فواصل.
     *
     * «قَالَ حَدَّثَنِي شَدَّادُ بْنُ أَوْسٍ ـ رضى الله عنه ـ عَنِ النَّبِيِّ ﷺ
     * " سَيِّدُ الاِسْتِغْفَارِ…» — فيُقطع ما قبل آخر صيغةِ تحمّلٍ تسبق
     * المرساة، **ويبقى الصحابيُّ بعدها بلا اختراع**: «شَدَّادُ بْنُ أَوْسٍ ـ
     * رضى الله عنه ـ عَنِ النَّبِيِّ ﷺ…».
     *
     * **ولا يُفحص إلّا صدرُ المتن** — ما قبل المرساة: صيغةُ التحمّل بعدها
     * كلامٌ مرويّ لا حلقةُ سند (الترمذي ٢٦١٦، T-116 العلّة الرابعة).
     *
     * @param  list<array{text: string, norm: string, at: int}>  $words
     */
    private static function withoutHeadChain(string $matn, array $words, int $boundary): ?string
    {
        $last = null;

        for ($index = 0; $index < min($boundary, count($words)); $index++) {
            if (in_array(self::bare($words[$index]['norm']), self::LINK_WORDS, true)) {
                $last = $index;
            }
        }

        if ($last === null) {
            return $matn;
        }

        if (! isset($words[$last + 1])) {
            return null;
        }

        $rest = preg_replace('/^[\s،ـ-]+/u', '', substr($matn, $words[$last + 1]['at'])) ?? '';

        return trim($rest) === '' ? null : trim($rest);
    }

    /**
     * أفي المتن سندٌ موازٍ؟ — صيغةُ تحمّلٍ **تفتتح جملة**، T-68 وT-116.
     *
     * وهذا هو حارسُ T-68 بعينه، وإنّما ضُيّق موضعُ فحصه: كان يفحص المتن كلَّه
     * فيقع في «أَخْبِرْنِي بِعَمَلٍ يُدْخِلُنِي الْجَنَّةَ» — كلامُ معاذٍ
     * للنبيّ ﷺ، تُطبَّع «اخبرني» فتُقرأ صيغةَ تحمّل، **فيُردّ الحديثُ كلُّه
     * بإسناده لأنّ في متنه سؤالاً**.
     */
    private static function hasParallelChain(string $matn): bool
    {
        $words = Words::of($matn);

        foreach ($words as $index => $word) {
            if (! in_array(self::bare($word['norm']), self::LINK_WORDS, true)) {
                continue;
            }

            if (self::opensChain($matn, $words, $index)) {
                return true;
            }
        }

        return false;
    }

    /**
     * قطعُ الإسناد المحض حين لا مرساةَ في الصفّ — T-116 العلّة الثانية.
     *
     * فالبخاري ٦٥٧٠ لا ذكرَ فيه لـ«صلى الله عليه وسلم»: «عَنْ أَبِي هُرَيْرَةَ
     * … أَنَّهُ قَالَ قُلْتُ يَا رَسُولَ اللَّهِ مَنْ أَسْعَدُ النَّاسِ
     * بِشَفَاعَتِكَ…». ولا حدَّ يُعرف بالمرساة، **ويُعرف بما ليس إسناداً**:
     * حلقةُ الإسناد اسمٌ ونسبٌ لا فعلَ قولٍ فيها، فأوّلُ مقطعٍ فيه كلامٌ
     * مرويّ هو مطلعُ المتن.
     *
     * @param  list<string>  $segments
     */
    private static function withoutBareChain(array $segments): ?string
    {
        $start = null;

        foreach ($segments as $index => $segment) {
            if (! self::isBareChain($segment)) {
                $start = $index;

                break;
            }
        }

        // الصفُّ كلُّه إسنادٌ فلا متنَ فيه، أو متنُه من أوّله فلا إسنادَ قبله.
        if ($start === null || $start === 0) {
            return null;
        }

        if (self::keepsCompanion($segments, $start)) {
            $start--;
        }

        if ($start === 0) {
            return null;
        }

        return self::assemble($segments, $start, count(Words::of($segments[$start])));
    }

    /** أهذا المقطعُ حلقةَ إسنادٍ محضة — اسمٌ ونسبٌ بلا كلامٍ مرويّ؟ */
    private static function isBareChain(string $segment): bool
    {
        if (! self::isChainLink($segment)) {
            return false;
        }

        $words = Words::of($segment);

        if ($words === [] || count($words) > self::MAX_CHAIN_WORDS) {
            return false;
        }

        foreach ($words as $word) {
            if (in_array($word['norm'], self::SPEECH_WORDS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * صدرٌ متدلٍّ من الحلقة السابقة: «يَقُولُ سَمِعْتُ رَسُولَ اللَّهِ ﷺ».
     *
     * **ويُفحص على الصورة المطبَّعة وتُقطع الكلمةُ من المشكَّلة**: النمطُ
     * لا يطابق «يَقُولُ» بحركاتها، والقطعُ يجب أن يقع على النصّ كما يُنشر.
     */
    private static function withoutDanglingHead(string $matn): string
    {
        if (preg_match(self::DANGLING_HEAD, Arabic::normalize($matn)) !== 1) {
            return $matn;
        }

        $rest = preg_replace('/^\S+\s+/u', '', $matn) ?? $matn;

        return trim($rest) === '' ? $matn : trim($rest);
    }

    private static function isChainLink(string $segment): bool
    {
        $bare = Arabic::normalize(trim($segment));

        return preg_match(self::LINK, $bare) === 1 || preg_match(self::ANAN, $bare) === 1;
    }

    /**
     * موضعُ الكلمة التي يبدأ عندها ذكرُ النبيّ ﷺ.
     *
     * @param  list<array{text: string, norm: string, at: int}>  $words
     */
    private static function anchorWord(array $words): ?int
    {
        foreach ($words as $index => $word) {
            if ($word['norm'] === 'ﷺ') {
                return $index;
            }

            if (Words::match($words, $index, ['صلي', 'الله', 'عليه', 'وسلم'])) {
                return $index;
            }
        }

        return null;
    }

    /**
     * أتفتتح هذه الكلمةُ جملةً؟ — أي أهي في أوّل الكلام أم بعد نقطة.
     *
     * **وعلامةُ الوقف تُقرأ من النصّ المشكَّل**: التطبيع يُبدل الترقيم فراغاً
     * (الخطوة ٧)، فلو سُئل المطبَّعُ عنها لما وجد نقطةً قطّ.
     */
    private static function opensSentence(string $text, int $at): bool
    {
        $before = rtrim(substr($text, 0, $at));

        return $before === '' || str_ends_with($before, '.');
    }

    /**
     * أيفتتح هذا الموضعُ سنداً جديداً؟ — أوسعُ من {@see self::opensSentence}.
     *
     * **و«ح» وحدها تفتح سنداً** — وهي رمزُ التحويل عند المحدّثين: يُكتب بين
     * إسنادين متوازيين بلا نقطةٍ قبله («… صلى الله عليه وسلم ح وَحَدَّثَنَا
     * هَارُونُ بْنُ عَبْدِ اللَّهِ…»، أبو داود ٤٣٦٣). فلو لم تُعدّ فاتحةً
     * لمرّ السندُ الثاني في المتن.
     *
     * @param  list<array{text: string, norm: string, at: int}>  $words
     */
    private static function opensChain(string $text, array $words, int $index): bool
    {
        if (($words[$index - 1]['norm'] ?? null) === 'ح') {
            return true;
        }

        return self::opensSentence($text, $words[$index]['at']);
    }

    /** الكلمةُ بلا واو العطف — «وحدثنا» صيغةُ تحمّلٍ كـ«حدثنا». */
    private static function bare(string $word): string
    {
        return preg_replace('/^و/u', '', $word) ?? $word;
    }
}
