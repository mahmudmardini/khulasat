<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع الاعتراض — دراسة المشروع، المادة 15.
 *
 * **والأنواع مذكورةٌ صراحةً لأنّ لكلٍّ مساراً مختلفاً**: خطأٌ في التخريج
 * يُصلَح، واعتراضُ ملقٍ على نسبة كلامٍ إليه يُرفع فوراً، وشكوى حقوقٍ لها
 * مهلتها. ونموذجٌ بحقلٍ حرٍّ وحده يجعلها كلَّها بريداً يُقرأ متى اتّفق.
 */
enum ComplaintKind: string
{
    /** خطأ في تخريج شاهد أو حكمه. */
    case Evidence = 'evidence';

    /** الملقي يعترض على نسبة الكلام إليه، أو على الملخّص نفسه. */
    case Attribution = 'attribution';

    /** طلب إزالة من صاحب حقّ. */
    case Takedown = 'takedown';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Evidence => 'خطأ في تخريج شاهد أو حكمه',
            self::Attribution => 'اعتراض على نسبة الكلام أو على الملخّص',
            self::Takedown => 'طلب إزالة',
            self::Other => 'غير ذلك',
        };
    }

    /**
     * **المهلة المعلنة، بالساعات.**
     *
     * والدراسة تَعِد بثمانٍ وأربعين ساعة لمسار الحذف. وأمّا خطأ التخريج
     * فأوسع: يُصلَح ولا يُزال، والصفحة قائمةٌ صحيحةٌ في سائرها.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Attribution, self::Takedown => 48,
            self::Evidence, self::Other => 168,
        };
    }
}
