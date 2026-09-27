<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Resolves the verifier for a (domain, kind) pair — المواصفة §7-4.
 *
 * **ولا يُربط المحقّق بنوع الشاهد ربطاً مباشراً.** الربط المباشر يكتب
 * `match ($kind)` في موضعٍ ثمّ في آخر، فيصير كلّ نوعٍ جديد تعديلاً في كلّ
 * موضع. والسجلّ يجعل الإضافة كتلةَ إعدادات.
 *
 * **والنوع الذي لا محقّق له يعود `null`** — فيُصنَّف شاهده `none` ويُرفع
 * للمراجعة. **ولا يمرّ.** فعجزُنا عن التحقّق ليس شهادةً بالصحّة.
 */
interface VerifierRegistry
{
    public function for(string $domain, string $kind): ?EvidenceVerifier;
}
