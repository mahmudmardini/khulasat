<?php

declare(strict_types=1);

namespace App\Enums;

enum OutputFormat: string
{
    case Html = 'html';
    case Json = 'json';
    case PngZip = 'png_zip';
}
