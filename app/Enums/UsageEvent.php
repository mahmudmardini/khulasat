<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a `usage_ledger` row records — المواصفة §4 و§11.
 */
enum UsageEvent: string
{
    /** ملخّص جديد — وحدة واحدة من الحصّة الشهرية. */
    case Generate = 'generate';

    /** إعادة توليد بقرار بشري — **تُحتسب من الحصّة الشهرية** كالتوليد. */
    case Regenerate = 'regenerate';

    /** دقائق تفريغ صوتي — حصّة مستقلّة عن حصّة الملخّصات. */
    case Transcribe = 'transcribe';

    /** أيّ الأحداث تُخصم من `monthly_quota`، وأيّها من حصّة التفريغ. */
    public function countsAgainstMonthlyQuota(): bool
    {
        return $this === self::Generate || $this === self::Regenerate;
    }
}
