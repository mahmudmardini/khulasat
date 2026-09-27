<?php

declare(strict_types=1);

namespace App\Domain\Summary;

use RuntimeException;

/**
 * Thrown when a regeneration is asked for outside its bounds — المواصفة §5 و§11.
 */
final class RegenerationRefused extends RuntimeException
{
    public static function limitReached(int $used, int $allowed): self
    {
        return new self(sprintf(
            'استُنفدت إعادات التوليد لهذا الملخّص: %d من %d.',
            $used,
            $allowed,
        ));
    }

    public static function insufficientRole(string $role): self
    {
        return new self(sprintf('الدور %s لا يملك إعادة التوليد.', $role));
    }

    public static function foreignActor(int $jobId): self
    {
        return new self(sprintf('محاولة إعادة توليد المهمّة %d من مستخدم جهةٍ أخرى.', $jobId));
    }
}
