<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * ما يملك المراجع أن يقرّره في شاهد — SCREENS.md الشاشة 5.
 *
 * **ثلاثة لا أكثر.** والقائمة مغلقة عمداً: كلّ خيارٍ رابع يعني قراراً لم
 * يُفكَّر فيه، وهذه الشاشة هي التي يوقّع بها مدير المحتوى على ما يُنشر.
 */
enum ReviewDecision: string
{
    /** يُنشر بلفظ المصدر — وهو الأصل. */
    case Source = 'source';

    /**
     * يُنشر بلفظ الدرس كما نطقه المتكلّم.
     *
     * **ينبّه ولا يُمنع** (SCREENS.md §5): القرار للإنسان لا للنظام. وقد
     * يكون المتكلّم على روايةٍ أخرى صحيحة لم تبلغها مدوّنتنا.
     */
    case AsQuoted = 'as_quoted';

    /** يُحذف من الملخّص. */
    case Remove = 'remove';

    public function resultingStatus(): ReviewStatus
    {
        return match ($this) {
            self::Source => ReviewStatus::Approved,
            self::AsQuoted => ReviewStatus::Corrected,
            self::Remove => ReviewStatus::Removed,
        };
    }
}
