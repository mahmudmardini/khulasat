<?php

declare(strict_types=1);

namespace App\Domain\Summary;

use App\Actions\Summary\TransitionJob;
use RuntimeException;

/**
 * Thrown when a job is moved along an edge the state machine does not have.
 *
 * لا تُعرض للمستخدم: وقوعها خطأٌ برمجيّ لا خطأ إدخال. وتُوقف العملية بدل
 * أن تمضي المهمّة في مسارٍ لم يُصمَّم.
 */
final class InvalidTransition extends RuntimeException
{
    public static function between(JobState $from, JobState $to): self
    {
        return new self(sprintf(
            'انتقال غير مسموح: %s ← %s. المسموح من %s: %s.',
            $from->value,
            $to->value,
            $from->value,
            $from->allowedTransitions() === []
                ? 'لا شيء — حالة نهائية'
                : implode('، ', array_map(fn (JobState $state): string => $state->value, $from->allowedTransitions())),
        ));
    }

    /**
     * تُرمى حين تُكتب `state` من خارج {@see TransitionJob}.
     *
     * الخريطة وحدها لا تكفي: `$job->update(['state' => 'published'])` يتجاوزها
     * كلَّها. فالحاجز على الكتابة نفسها لا على الطريق إليها.
     */
    public static function outsideStateMachine(string $from, string $to): self
    {
        return new self(sprintf(
            'محاولة كتابة الحالة %s ← %s من خارج آلة الحالات. استعمل %s.',
            $from,
            $to,
            TransitionJob::class,
        ));
    }
}
