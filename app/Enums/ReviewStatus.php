<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Verification\DomainPolicy;
use App\Support\Verification\VerificationResult;

/**
 * Where one piece of evidence stands with the human reviewer — المواصفة §4.
 */
enum ReviewStatus: string
{
    /** طابق تماماً فمرّ بلا إنسان. */
    case AutoPassed = 'auto_passed';

    /** ينتظر قراراً بشرياً. **وهذه وحدها تمنع النشر.** */
    case Pending = 'pending';

    case Approved = 'approved';
    case Corrected = 'corrected';
    case Removed = 'removed';

    /**
     * Whether a human decision is no longer owed on this item.
     *
     * الحذف حسمٌ كالإقرار: الشاهد خرج من الملخّص، فلم يبقَ ما يُنشر بلا تحقّق.
     */
    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }

    /**
     * القرار الابتدائي على نتيجة تحقّق — المواصفة §7-5، سياسة البيان.
     *
     * **ثلاثة مآلات لا اثنان:**
     *
     *   ١. `Removed` — **ما جُهل مصدره لا يُنشر البتّة.** لا مطابقة، أو
     *      مطابقةٌ بلا درجة منصوصة. ويُحذف **صامتاً** ويُسجَّل لصاحب الجهة،
     *      ولا يُوصَف بشيء: **عجزُنا عن التخريج ليس حكماً بالوضع**.
     *   ٢. `AutoPassed` — عُرف مصدره، ووضعُ الجهة يسمح بنشره مقروناً ببيانه.
     *   ٣. `Pending` — عُرف مصدره، والجهة اختارت أن يراه إنسان أوّلاً.
     *
     * ولذلك تأخذ `VerificationResult` كاملاً لا `MatchStatus` وحده: حالةُ
     * المطابقة لا تحمل درجة المحدّث، ومن حسم بها وحدها نشر ما لا درجة له.
     *
     * **والحدّ الرابع لا يُمسّ:** `Pending` تمنع النشر منعاً باتّاً، ووضعُ
     * الجهة يغيّر **متى** تُدخَل الحالة لا ما تفعله فيها.
     */
    public static function decide(
        VerificationResult $result,
        DomainPolicy $policy,
        UnverifiedPolicy $mode = UnverifiedPolicy::Disclose,
    ): self {
        if (! $policy->hasStatedSource($result)) {
            return self::Removed;
        }

        return $policy->publishes($result, $mode) ? self::AutoPassed : self::Pending;
    }
}
