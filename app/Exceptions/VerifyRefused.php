<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * طلبُ «تحقّق» رُدّ قبل أن يُكلّف شيئاً — T-181: حدُّ المعدّل، أو وقفُ الإنفاق.
 */
final class VerifyRefused extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly int $retryAfter,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(int $seconds): self
    {
        return new self('rate_limited', $seconds, (string) __('verify.errors.rate_limited', [
            'minutes' => max(1, (int) ceil($seconds / 60)),
        ]));
    }

    public static function paused(): self
    {
        return new self('paused', 0, (string) __('verify.errors.paused'));
    }

    /** رمزُ HTTP في الواجهة البرمجية. */
    public function status(): int
    {
        return $this->reason === 'rate_limited' ? 429 : 503;
    }
}
