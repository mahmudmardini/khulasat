<?php

declare(strict_types=1);

namespace App\Support\Quran;

use App\Enums\Locale;

/**
 * An ayah's citation in the page's own language — T-80.
 *
 * ★ **يُبنى من رقمَي السورة والآية ولا يُترجَم.** كان نموذجُ الترجمة يصوغ
 * «Surah Al-Isra, verse 9» في المتن، والقائمةُ تعرض «سورة الإسراء، الآية ٩»
 * — تخريجان لآيةٍ واحدة في صفحةٍ واحدة (T-69: «واحدٌ لا اثنان»)، وصياغةٌ
 * تتبدّل بين تشغيلين. والرقمان حقيقةٌ تثبت، والاسمُ من {@see SurahNames}.
 *
 * **والعربيةُ ليست هنا**: تخريجُها المحفوظ (`source_ref`) هو الأصل، ولا
 * يُبدَّل في صفحتها.
 */
final class AyahCitation
{
    private function __construct() {}

    /**
     * @param  int|null  $end  آخرُ الآيات حين يكون الشاهدُ آيتين موصولتين.
     */
    public static function for(int $surah, int $ayah, ?int $end, Locale $locale): ?string
    {
        $name = SurahNames::of($surah, $locale);

        if ($name === null) {
            return null;
        }

        // «17:9» اصطلاحُ المصاحف المترجَمة كلِّها، وبه يُبحث عن الآية في أيّها.
        $position = $surah.':'.$ayah.($end !== null && $end > $ayah ? '–'.$end : '');

        return match ($locale) {
            Locale::En => "Surah {$name} {$position}",
            Locale::Tr => "{$name} Suresi {$position}",
            Locale::Ru => "Сура «{$name}» {$position}",
            Locale::Ar => null,
        };
    }
}
