<?php

declare(strict_types=1);

namespace App\Actions\Summary;

use App\Domain\Summary\AutomaticRetryRefused;
use App\Domain\Summary\JobState;
use App\Models\SummaryJob;

/**
 * Bumps the attempt counter for a stage whose call failed — المواصفة §5.
 *
 * **إعادة استدعاءٍ سقط، لا إعادة نتيجةٍ رديئة.** الأولى آلية ومحصورة في
 * `transcribing` و`cleaning` و`rendering` ثلاث مرّات بتراجع أُسّي. والثانية
 * قرارٌ بشريّ يمرّ بـ {@see RequestRegeneration} ويُحتسب من حصّة الجهة.
 *
 * ولا يغيّر هذا الفعل الحالة: الإعادة تقع **داخل** الحالة نفسها، ولذلك لا
 * حافّة راجعة في الخريطة تُساء استعمالها.
 *
 * @return int عدد ثواني الانتظار قبل المحاولة التالية.
 */
final class ScheduleStageRetry
{
    public function handle(SummaryJob $job): int
    {
        if (! $job->state->allowsAutomaticRetry()) {
            throw AutomaticRetryRefused::inState($job->state);
        }

        if ($job->attempt >= JobState::MAX_AUTOMATIC_ATTEMPTS) {
            throw AutomaticRetryRefused::exhausted($job->state, $job->attempt);
        }

        $job->increment('attempt');

        return JobState::retryDelaySeconds($job->attempt);
    }
}
