<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A language that cannot be added to this summary — T-166.
 *
 * رسالتُه عربيةٌ من `lang/ar/jobs.php`، فتُعرض كما هي على مدير المحتوى.
 */
final class LocaleNotAddable extends RuntimeException
{
    public static function because(string $key): self
    {
        return new self(trans('jobs.add_locale.refused.'.$key));
    }
}
