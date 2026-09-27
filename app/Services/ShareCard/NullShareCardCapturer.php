<?php

declare(strict_types=1);

namespace App\Services\ShareCard;

use App\Contracts\ShareCardCapturer;
use App\Http\Controllers\Public\ShareCardController;

/**
 * The default: no browser on the server, so no generated card — T-144.
 *
 * والخلاصةُ لا تبقى بلا صورة: {@see ShareCardController}
 * يُسلّم بطاقةَ المنصّة حين لا بطاقةَ مولَّدة.
 */
final class NullShareCardCapturer implements ShareCardCapturer
{
    public function capture(string $html, int $width, int $height): ?string
    {
        return null;
    }
}
