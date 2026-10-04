<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Contracts\OverflowProbe;

/** لا متصفّح، فلا قياس — `null` لا «لا فيض». */
final class NullOverflowProbe implements OverflowProbe
{
    public function overflowing(string $html): ?array
    {
        return null;
    }
}
