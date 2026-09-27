<?php

declare(strict_types=1);

namespace App\Support\Verification;

/**
 * What the verifier needs to check one piece of evidence.
 *
 * **لماذا كائن قيمة لا نموذج Eloquent:** المواصفة §7-4 تكتب التوقيع
 * `verify(EvidenceItem $item)`، و`EvidenceItem` جدولٌ يُنشأ في T-07. وربط
 * المحقّق بالتخزين يجعله غير قابل للاختبار قبلها، وطبقةُ التحقّق أولى ما
 * يُختبر في هذا المشروع. فبقي المحقّق دالّةً خالصة، ونموذجُ T-07 يُسقَط
 * إليه بـ `EvidenceItem::toVerificationInput()`.
 */
final readonly class EvidenceInput
{
    public function __construct(
        public string $kind,
        public string $rawText,
        public ?string $claimedSource = null,
        public ?string $claimedNarrator = null,
        public ?string $claimedTakhrij = null,
    ) {}
}
