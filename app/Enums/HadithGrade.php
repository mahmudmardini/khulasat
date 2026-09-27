<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Arabic;

/**
 * The ruling on a hadith, reduced to what publishing needs to know.
 *
 * أحكام المحدّثين أدقّ من هذه الفئات بكثير، وليس غرضنا تصنيفها. الغرض
 * سؤال واحد: **أيمرّ هذا الشاهد بلا إنسان أم يقف؟**
 */
enum HadithGrade: string
{
    case Sahih = 'sahih';
    case Hasan = 'hasan';
    case Daif = 'daif';
    case Mawdu = 'mawdu';
    case Unknown = 'unknown';

    /**
     * الألفاظ التي تُصنَّف بها أحكام المزوّدين، مطبَّعةً.
     *
     * تُقارَن مطبَّعة لأنّ المزوّدين يكتبون «ضعيف» و«ضَعِيف» و«ضعيفٌ».
     *
     * @var array<string, list<string>>
     */
    private const MARKERS = [
        'mawdu' => ['موضوع', 'لا اصل له', 'مكذوب', 'باطل', 'لا يصح'],
        'daif' => ['ضعيف', 'منكر', 'شاذ', 'واه', 'متروك', 'ضعيف جدا'],
        'hasan' => ['حسن'],
        'sahih' => ['صحيح', 'متفق عليه'],
    ];

    /**
     * Read a provider's free-text ruling.
     *
     * **الترتيب مقصود:** يُفحص الموضوع ثمّ الضعيف قبل الحسن والصحيح.
     * فحكمٌ نصُّه «ضعيف بهذا اللفظ وصحيح بغيره» يجب أن يُقرأ ضعيفاً،
     * ولو قُدّم «صحيح» لمرّ ما لا يُمرَّر.
     */
    public static function fromRuling(?string $ruling): self
    {
        if ($ruling === null || trim($ruling) === '') {
            return self::Unknown;
        }

        $normalized = Arabic::normalize($ruling);

        foreach (self::MARKERS as $grade => $markers) {
            foreach ($markers as $marker) {
                if (self::mentions($normalized, $marker)) {
                    return self::from($grade);
                }
            }
        }

        return self::Unknown;
    }

    /**
     * Does the ruling contain this marker as a whole word?
     *
     * **على حدّ الكلمة لا كسلسلة فرعية.** فـ«واه» علامةُ ضعف، وهي داخل
     * «رواه» — ولو فُتّش بالسلسلة الفرعية لصار «رواه البخاري» حكماً بالضعف،
     * فيُحجب الصحيح. وكلّ علامة تُطابَق على أوّل الكلمة فتُلتقط تصاريفها
     * («ضعيفة» ← «ضعيفه» تبدأ بـ«ضعيف»).
     *
     * وما فات هذا الفحص يعود `Unknown`، و`Unknown` **لا يمرّ آلياً** —
     * فالإخفاق في الاتّجاه الآمن.
     */
    private static function mentions(string $normalized, string $marker): bool
    {
        if (str_contains($marker, ' ')) {
            return str_contains(" {$normalized} ", " {$marker} ");
        }

        foreach (explode(' ', $normalized) as $word) {
            if (str_starts_with($word, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * **القاعدة فوق كلّ شيء** — المواصفة §7-3 البند 5.
     *
     * الضعيف والموضوع والمجهول تُرفع للمراجعة **مهما بلغت نسبة التطابق**.
     * فالمطابقة تقول «هذا اللفظ موجود»، لا «هذا اللفظ صحيح». وهما سؤالان.
     */
    public function mayAutoPass(): bool
    {
        return $this === self::Sahih || $this === self::Hasan;
    }

    public function label(): string
    {
        return match ($this) {
            self::Sahih => 'صحيح',
            self::Hasan => 'حسن',
            self::Daif => 'ضعيف',
            self::Mawdu => 'موضوع',
            self::Unknown => 'لم يُحكم عليه',
        };
    }
}
