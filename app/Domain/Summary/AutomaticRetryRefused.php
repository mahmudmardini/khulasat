<?php

declare(strict_types=1);

namespace App\Domain\Summary;

use RuntimeException;

/**
 * Thrown when something asks for an automatic retry the spec does not allow.
 *
 * المواصفة §5 تحصر الإعادة الآلية في `transcribing` و`cleaning` و`rendering`،
 * ثلاث مرّات. وما عداها — ومنه مراحل النماذج حين ينجح الاستدعاء وتردأ
 * النتيجة — إعادتُه قرارٌ بشريّ يُحتسب من `regenerations_per_summary`.
 */
final class AutomaticRetryRefused extends RuntimeException
{
    public static function inState(JobState $state): self
    {
        $reason = $state->isModelStage()
            ? 'مرحلة نماذج: نجاح الاستدعاء ورداءة النتيجة لا يُعالَجان بإعادة آلية، بل بقرار بشري'
            : 'الإعادة الآلية مقصورة على التفريغ والتنظيف والإخراج';

        return new self(sprintf('لا إعادة آلية في الحالة %s — %s.', $state->value, $reason));
    }

    public static function exhausted(JobState $state, int $attempts): self
    {
        return new self(sprintf(
            'استُنفدت محاولات %s: %d من %d.',
            $state->value,
            $attempts,
            JobState::MAX_AUTOMATIC_ATTEMPTS,
        ));
    }
}
