<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Turns a share card's HTML into a PNG — T-144.
 *
 * خلف عقدٍ لأنّه أداةٌ خارجية (CLAUDE.md §1): متصفّحٌ بلا واجهة على
 * الخادم، أو لا شيء. **وإخفاقُه لا يُرفع استثناءً**: البطاقةُ زينةُ مشاركة،
 * وسقوطُها لا يُسقط نشراً — تبقى بطاقةُ المنصّة مكانها.
 */
interface ShareCardCapturer
{
    /**
     * @return string|null PNG bytes, or null when capture is disabled or failed.
     */
    public function capture(string $html, int $width, int $height): ?string;
}
