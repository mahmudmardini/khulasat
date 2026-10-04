<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حالُ طلبٍ في أداة «تحقّق» — T-181.
 *
 * مراحلُ ثلاث يراها الباحث بأسمائها، **بلا نسبة مئوية**: الاستخراجُ نداءُ
 * نموذج، والمطابقةُ حتمية بلا نموذج، ثمّ التقرير.
 */
enum VerifyCheckStatus: string
{
    case Queued = 'queued';
    case Extracting = 'extracting';
    case Verifying = 'verifying';
    case Done = 'done';
    case Failed = 'failed';

    public function isSettled(): bool
    {
        return $this === self::Done || $this === self::Failed;
    }
}
