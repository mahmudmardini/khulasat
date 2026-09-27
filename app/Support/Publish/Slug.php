<?php

declare(strict_types=1);

namespace App\Support\Publish;

/**
 * `slug` لاتيني بشرطات من عنوانٍ عربي — المواصفة §9.
 *
 * «`al-durus`». **والنقحرة تُكتب هنا ولا تُترك لـ `Str::slug`**:
 * الأخيرة تُسقط العربية كلَّها فيعود الناتج فارغاً، فتصير كلُّ صفحةٍ
 * `summary-1` و`summary-2` — وهو ما يُذهب نصف قيمة الرابط في البحث.
 */
final class Slug
{
    /**
     * نقحرة الحروف العربية.
     *
     * وهي **عملية لا علمية**: غرضها رابطٌ يُقرأ ويُشارَك ويُفهرَس، لا نظامُ
     * نقحرةٍ أكاديمي. فالهمزات تسقط، والتاء المربوطة `a`، والمدود تُكتب
     * حرفاً واحداً — لأنّ `al-duruws` أسوأ من `al-durus`.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'ا' => 'a', 'أ' => 'a', 'إ' => 'i', 'آ' => 'a', 'ٱ' => 'a',
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h',
        'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z',
        'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
        'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
        'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
        'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'a',
        'ء' => '', 'ؤ' => 'u', 'ئ' => 'i',
        'پ' => 'p', 'چ' => 'ch', 'ژ' => 'zh', 'گ' => 'g', 'ڤ' => 'v',
    ];

    /** أل التعريف تبقى `al-` — وهي علامة العربية في الرابط. */
    private const MAX_LENGTH = 80;

    /** الحركات القصيرة حين تُوجد — والعنوان المشكول يُعطي رابطاً أقرب للنطق. */
    private const SHORT_VOWELS = [
        "\u{064E}" => 'a',  // فتحة
        "\u{064F}" => 'u',  // ضمّة
        "\u{0650}" => 'i',  // كسرة
        "\u{0651}" => '',   // شدّة — تُطوى، والتضعيف لا يُكتب في رابط
        "\u{0652}" => '',   // سكون
        "\u{064B}" => 'an', // تنوين فتح
        "\u{064C}" => 'un',
        "\u{064D}" => 'in',
    ];

    public static function make(string $title): string
    {
        $out = '';

        foreach (self::words($title) as $word) {
            $out .= '-'.self::transliterate($word);
        }

        $out = trim((string) preg_replace('/-+/', '-', $out), '-');

        if (mb_strlen($out) > self::MAX_LENGTH) {
            // القطع عند شرطة لا في وسط كلمة، فيبقى الرابط مقروءاً.
            $out = (string) mb_substr($out, 0, self::MAX_LENGTH);
            $lastDash = mb_strrpos($out, '-');
            $out = $lastDash === false ? $out : (string) mb_substr($out, 0, $lastDash);
        }

        // عنوانٌ بلا حرفٍ قابل للنقحرة — رموزٌ أو أرقام وحدها.
        return $out === '' ? 'summary' : $out;
    }

    /** @return list<string> */
    private static function words(string $title): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{Arabic}\p{L}\p{N}]+/u', $title) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }

    /**
     * كلمةٌ واحدة.
     *
     * و**أل التعريف تُفصل بشرطة** — `al-durus` لا `aldurus`. فهي في كلّ عنوان
     * عربي تقريباً، ووصلُها بما بعدها يُخرج كتلةً لا تُقرأ. والمواصفة §9
     * تكتبها مفصولة: `al-durus`.
     */
    private static function transliterate(string $word): string
    {
        $prefix = '';

        // أل التعريف: ألفٌ ولامٌ يليهما حرفان فأكثر.
        if (preg_match('/^(ال|الْ)(.{2,})$/u', $word, $matches) === 1) {
            $prefix = 'al-';
            $word = $matches[2];
        }

        $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        $count = count($letters);

        // أآخرُ ما أُضيف حركةٌ؟ فبها وحدها يُعرف الإعراب من حرف المدّ.
        $endsInHarakah = false;

        foreach ($letters as $index => $char) {
            if (isset(self::SHORT_VOWELS[$char])) {
                $out .= self::SHORT_VOWELS[$char];
                $endsInHarakah = self::SHORT_VOWELS[$char] !== '';

                continue;
            }

            $endsInHarakah = false;

            /*
             * ما بقي من علامات التشكيل يسقط. و`\p{Mn}` لا مدى محارفَ صريحاً:
             * مدى المحارف العربية يُكتب في `Support/Arabic.php` **وحدها**
             * (CLAUDE.md §1 وT-03)، ونسخةٌ ثانية منه تتباعد عنها بمرور الوقت.
             * والصنف العامّ يغطّي المدّة والألف الخنجرية وما بعدهما جميعاً.
             */
            if (preg_match('/\p{Mn}/u', $char) === 1) {
                continue;
            }

            if (isset(self::MAP[$char])) {
                /*
                 * **الواو والياء حرفا مدٍّ في جوف الكلمة وصامتان في صدرها.**
                 * فـ«المحمود» تُقرأ `mahmud` لا `mahmwd`، و«يوم» تبدأ بـ`y`.
                 * والفرق يُرى في كلّ عنوان تقريباً.
                 */
                $out .= match (true) {
                    $char === 'و' && $index > 0 => 'u',
                    $char === 'ي' && $index > 0 && $index === $count - 1 => 'i',
                    default => self::MAP[$char],
                };

                continue;
            }

            $out .= preg_match('/[A-Za-z0-9]/', $char) === 1 ? strtolower($char) : '';
        }

        /*
         * تسويتان أخيرتان:
         *   ١. **حركةٌ يتبعها حرفُ مدِّها تُكتب مرّةً.** الضمّة ثمّ الواو في
         *      «الدُّرُوسُ» كلتاهما `u`، فيخرج `duruusu` — وهو خطأ نقحرة
         *      لا خيارَ أسلوب.
         *   ٢. **إعرابُ آخر الكلمة يسقط.** «دراسةٌ» في رابطٍ هي `drasa`،
         *      والتنوين علامةٌ نحوية لا جزءٌ من الاسم.
         */
        $out = (string) preg_replace('/([aui])\\1+/', '$1', $out);

        /*
         * **ويسقط الإعراب وحده.** والفاصل أنّه حركةٌ لا حرف: فضمّة آخر
         * «الدُّرُوسُ» تسقط، وألفُ «الجمعة» وياءُ «في» تبقيان. ولولا هذا
         * الفصل لصارت «في» حرفاً واحداً.
         */
        if ($endsInHarakah) {
            $out = (string) preg_replace('/(?:an|un|in|a|u|i)$/', '', $out);
        }

        return $prefix.$out;
    }

    /**
     * **التصادم بلاحقة رقمية** — §9.
     *
     * و`$taken` دالّةٌ تُسأل، لا قائمةٌ تُمرَّر: الفحص يقع على قاعدة البيانات
     * داخل الجهة، والتوليد لا يعرف التخزين.
     *
     * @param  callable(string): bool  $taken
     */
    public static function unique(string $title, callable $taken): string
    {
        $base = self::make($title);

        if (! $taken($base)) {
            return $base;
        }

        // يبدأ من 2: الأوّل بلا لاحقة، فـ«-2» تعني الثاني لا الأوّل.
        for ($suffix = 2; $suffix < 1000; $suffix++) {
            $candidate = "{$base}-{$suffix}";

            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        return $base.'-'.substr(bin2hex(random_bytes(4)), 0, 6);
    }
}
