<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Verification\EvidenceInput;
use App\Support\Verification\VerificationResult;

/**
 * The verification contract — المواصفة §7-4.
 *
 * **لا نموذج لغوي خلف هذا العقد.** المطابقة نصّية حتمية، ونتيجتها قابلة
 * للتدقيق. ومن وجد نفسه يسأل نموذجاً عن صحّة شاهد فقد أخطأ الطبقة
 * — CLAUDE.md §2 القاعدة الثالثة.
 */
interface EvidenceVerifier
{
    public function verify(EvidenceInput $input): VerificationResult;
}
