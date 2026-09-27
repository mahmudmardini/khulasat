<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * The Qur'an text and approved translations, fetched from their licensed source — T-161.
 *
 * المصدر اليوم Quran Foundation بحساب مطوّر، وشروطُه تمنع حفظ محتواه أكثر من
 * أسبوعٍ إلّا بمزامنةٍ كلّ سبعة أيام. **فكلُّ ما يُحفظ منه يمرّ من هنا**، لا
 * من نداءٍ مباشرٍ في أمرٍ أو صنف.
 */
interface QuranContent
{
    /**
     * الرسم العثماني وأسماء السور.
     *
     * @return array{chapters: array<int, string>, verses: array<string, string>}
     *                                                                            `chapters`: رقم السورة ← اسمها العربي. `verses`: `2:255` ← النصّ.
     */
    public function core(): array;

    /**
     * النصّ الإملائي — عليه تجري المطابقة (T-03ب).
     *
     * @return array<string, string> `2:255` ← النصّ.
     */
    public function imlaei(): array;

    /**
     * ترجمةٌ معتمدةٌ بمعرّفها، كما وردت بحواشيها.
     *
     * @return array<string, string> `2:255` ← النصّ.
     */
    public function translation(int $id): array;
}
