<?php

declare(strict_types=1);

namespace App\Support\Quran;

use App\Support\Arabic;

/**
 * The Qur'anic text as it is published — T-56.
 *
 * **المصحف يُخزَّن بلا تقويسٍ ولا علامةِ آية**، فكان الشاهد يُنشر عارياً:
 * «من عمل صالحا من ذكر أو أنثى…» بلا ﴿﴾ ولا ۝٩٧، وفي ورقة المهارة صنفُ
 * `.ayah-no` جاهزٌ لا يستعمله أحد.
 *
 * **وحتميٌّ لا يُطلب من نموذج** — CLAUDE.md §2 القاعدة ٣ ونصُّ المهمّة.
 * والنموذجُ ممنوعٌ أصلاً من تغيير لفظ الشاهد، فلا يملك أن يزيد فيه علامة.
 *
 * **والتزيينُ عند المحقّق لا عند العارض**: هو وحده يعرف رقم الآية وأهي
 * تامّةٌ أم شذرة. فيخرج اللفظُ مزيَّناً مرّةً واحدة، وتتّفق عليه الصفحةُ
 * وقائمةُ التخريج والسرلوح — ولو زُيّن في ثلاثة مواضع لاختلفت الثلاثة.
 *
 * **والشذرةُ لا تُعلَّم.** «۝» علامةُ انتهاء آية، ووضعُها في آخر نصفِ آيةٍ
 * يقول للقارئ إنّ الآية انتهت هناك — وذلك خبرٌ كاذب عن كتاب الله.
 */
final class AyahText
{
    public const OPEN = '﴿';

    public const CLOSE = '﴾';

    /** علامة نهاية الآية، ثمّ رقمها بالعربية الهندية. */
    public const MARK = '۝';

    /** العلامةُ ورقمُها في النصّ المزيَّن — لتُلبَس صنفَها عند العرض. */
    private const MARKED = '/'.self::MARK.'([٠-٩]+)/u';

    private function __construct() {}

    /**
     * اللفظ مقوَّساً ومعلَّماً — يُحفظ في `matched_text` كما يُنشر.
     *
     * @param  array<string, mixed>  $meta  `source_meta` من المحقّق.
     */
    public static function decorate(string $text, array $meta = []): string
    {
        $text = trim($text);

        // **مرّةٌ واحدة**: إعادةُ التزيين على نصٍّ مزيَّن تُضاعف الأقواس.
        if ($text === '' || str_starts_with($text, self::OPEN)) {
            return $text;
        }

        // شذرةٌ من آية: تُقوَّس ولا تُعلَّم.
        if (($meta['is_fragment'] ?? false) === true) {
            return self::OPEN.$text.self::CLOSE;
        }

        $last = $meta['ayah_number_end'] ?? $meta['ayah_number'] ?? null;

        if (! is_int($last) && ! is_string($last)) {
            return self::OPEN.$text.self::CLOSE;
        }

        // آيتان موصولتان: تُعلَّم الأولى في موضع وصلها، فلا تُقرآن واحدة.
        $break = $meta['ayah_break_at'] ?? null;
        $first = $meta['ayah_number'] ?? null;

        if (is_int($break) && $break > 0 && $break < mb_strlen($text) && $first !== null) {
            $text = rtrim(mb_substr($text, 0, $break)).' '.self::mark($first)
                .' '.ltrim(mb_substr($text, $break));
        }

        return self::OPEN.$text.' '.self::mark($last).self::CLOSE;
    }

    /**
     * اللفظ بلا زينته — للمطابقة لا للعرض (T-80).
     *
     * **والزينةُ ليست من اللفظ**: القوسان والعلامةُ ورقمُها يضعها
     * {@see self::decorate()}، فلفظٌ مزيَّن ولفظُ المصحف الخام لا يتساويان
     * إلّا بعد نزعها.
     */
    public static function plain(string $text): string
    {
        $text = (string) preg_replace(self::MARKED, ' ', $text);

        return trim(str_replace([self::OPEN, self::CLOSE], ' ', $text));
    }

    /** النصّ مهرَّباً، وعلاماتُه ملبَسةٌ صنفَ المهارة `.ayah-no`. */
    public static function html(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return (string) preg_replace(
            self::MARKED,
            '<span class="ayah-no">'.self::MARK.'$1</span>',
            $escaped,
        );
    }

    private static function mark(int|string $number): string
    {
        return self::MARK.Arabic::toArabicIndicDigits((string) $number);
    }
}
