<?php

declare(strict_types=1);

namespace App\Support\Quiz;

use App\Enums\Locale;
use App\Models\Quiz;

/**
 * رابطُ زرّ «اختبر فهمك» في صفحة الملخّص — T-195.
 *
 * **ويغيب كشاهدة العدّ** في المعاينة والملفّ المنزَّل: كلاهما يُفرَّغ
 * `summaryJobId` فيه، فلا يُدعى قارئُ ملفٍّ على قرصه إلى اختبارٍ قد يُغلق.
 *
 * **ويغيب كذلك** حين لا اختبار، أو تعذّر بناؤه، أو أُغلق. فالصفحةُ لا تَعِد
 * بما لا يُفتح.
 */
final class QuizLink
{
    private function __construct() {}

    public static function for(?int $summaryJobId, Locale $locale): ?string
    {
        // العربيةُ وحدها في T-195، فلا يُدعى قارئُ صفحةٍ مترجمة إلى أسئلةٍ بغير لسانه.
        if ($summaryJobId === null || ! $locale->isSource()) {
            return null;
        }

        $quiz = Quiz::acrossTenants()->where('summary_job_id', $summaryJobId)->first();

        return $quiz?->isOpen() === true ? $quiz->publicUrl() : null;
    }
}
