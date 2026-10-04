<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The books of the seeded corpus — T-05ب والمواصفة §7-3.
 *
 * **الكتب التسعة.** ستّةُ الكتب والموطّأ بأحكامها، ثمّ مسندُ أحمد وسننُ
 * الدارمي طبقةً ثانية بلا أحكام (T-170). وما في المصدر عدا هذه
 * السبعة مجاميعُ أربعينيّةٍ (النووية والقدسية والدهلوية) **لا تُبذر**:
 * متونها مكرّرة من هذه الكتب، وهي **بلا أحكام**، فتزاحم الصفَّ المحكوم
 * عليه بصفٍّ مجهولِ الحكم — والمجهول لا يُنشر (§7-5).
 */
enum HadithBook: string
{
    case Bukhari = 'bukhari';
    case Muslim = 'muslim';
    case AbuDawud = 'abudawud';
    case Tirmidhi = 'tirmidhi';
    case Nasai = 'nasai';
    case IbnMajah = 'ibnmajah';
    case Malik = 'malik';

    /*
     * ★ **الطبقةُ الثانية — بلا أحكام** (T-170، قرار مالك المنتج ٤ أكتوبر ٢٠٢٦).
     * من Open-Hadith-Data، ولا مصدرَ مفتوحاً فيه أحكامُ المحدّثين عليهما. فأحاديثُهما
     * تُطابَق ليُعرف موضعُها، **ولا تُنشر**: المجهولُ الحكم لا يمرّ (§7-5).
     */
    case Ahmad = 'ahmad';
    case Darimi = 'darimi';

    /** اسم الكتاب كما يُذكر في العرض. */
    public function title(): string
    {
        return match ($this) {
            self::Bukhari => 'صحيح البخاري',
            self::Muslim => 'صحيح مسلم',
            self::AbuDawud => 'سنن أبي داود',
            self::Tirmidhi => 'جامع الترمذي',
            self::Nasai => 'سنن النسائي',
            self::IbnMajah => 'سنن ابن ماجه',
            self::Malik => 'موطّأ مالك',
            self::Ahmad => 'مسند أحمد',
            self::Darimi => 'سنن الدارمي',
        };
    }

    /** صاحب الكتاب كما يُذكر في «رواه …». */
    public function narratedBy(): string
    {
        return match ($this) {
            self::Bukhari => 'البخاري',
            self::Muslim => 'مسلم',
            self::AbuDawud => 'أبو داود',
            self::Tirmidhi => 'الترمذي',
            self::Nasai => 'النسائي',
            self::IbnMajah => 'ابن ماجه',
            self::Malik => 'مالك',
            self::Ahmad => 'أحمد',
            self::Darimi => 'الدارمي',
        };
    }

    /**
     * **الاستثناء الوحيد: إخراج الشيخين هو الحكم** — قرار مالك المنتج، ٦ أيلول ٢٠٢٦.
     *
     * لا مصدر في الدنيا يحكم على أحاديث البخاري ومسلم حديثاً حديثاً، ولذلك
     * جاء الصفّ في المدوّنة بلا حكم — **وهذا صواب لا نقص**. فيُعتدّ بإخراجهما
     * حكماً بالصحّة، وهو **نقلٌ لتلقّي الأمّة بالقبول لا اجتهادٌ منّا**.
     *
     * **ولا يُقاس عليه غيره.** بقيّة الكتب أحكامها منصوصة من المحكِّمين، وما
     * لا حكم له فيها يبقى مجهولاً ولا يمرّ — فلا يُشتقّ حكمٌ من كون الحديث
     * في كتاب.
     */
    public function isSahihayn(): bool
    {
        return $this === self::Bukhari || $this === self::Muslim;
    }

    /**
     * كتابٌ من الطبقة الثانية: **لا يُسأل عنه إلّا إذا لم تجد الكتبُ المحكومة
     * الحديثَ أصلاً** — T-170. فالمسندُ يروي كثيراً ممّا في الصحيحين بلفظٍ
     * قريب، ولو زاحمهما في البحث لأخرج نسخةً بلا حكمٍ مكانَ نسخةٍ صحيحة.
     */
    public function isSecondary(): bool
    {
        return $this === self::Ahmad || $this === self::Darimi;
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /** @return list<self> */
    public static function primary(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $book): bool => ! $book->isSecondary()));
    }

    /** @return list<self> */
    public static function secondary(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $book): bool => $book->isSecondary()));
    }
}
