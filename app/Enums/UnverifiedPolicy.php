<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a tenant does with evidence that is not exact-and-sound — المواصفة §7-5.
 *
 * إعدادٌ **يملكه مالك الجهة ويُضبط مرّة**. وهو يغيّر **متى تُدخَل حالة
 * `needs_review`، لا ما تفعله فيها**: الحدّ الرابع لا يُمسّ — `needs_review`
 * تمنع النشر منعاً باتّاً، ولا إعداد يتجاوزها (CLAUDE.md §2 القاعدة الرابعة).
 */
enum UnverifiedPolicy: string
{
    /**
     * **الافتراض.** يُنشر ما عُرف مصدره مقروناً بدرجته، بلا مراجعة بشرية.
     *
     * وجاز نشر الضعيف لأنّه يُنشر مقروناً ببيانه، **وهذا عمل أهل العلم**.
     * والآفة في نقل الضعيف موهِماً صحّته، لا في نقله مبيَّناً.
     */
    case Disclose = 'disclose';

    /** كلّ ما ليس `exact` وصحيحاً يقف عند `needs_review`. */
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Disclose => 'يُنشر مبيَّناً',
            self::Review => 'يقف للمراجعة',
        };
    }
}
