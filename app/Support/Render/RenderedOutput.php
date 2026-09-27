<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\OutputFormat;
use App\Enums\OutputType;

/** ما يعود من عارض، قبل أن يُخزَّن في `outputs` — §8-أ. */
final readonly class RenderedOutput
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public OutputType $type,
        public OutputFormat $format,
        public string $contents,
        public string $rendererVersion,
        public array $meta = [],
    ) {}

    public function bytes(): int
    {
        return strlen($this->contents);
    }
}
