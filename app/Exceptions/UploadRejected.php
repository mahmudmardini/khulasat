<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A chunked upload refused, with a message the uploader can show as is.
 *
 * والرسالةُ مترجمةٌ من `lectures.create.source.*` لا نصٌّ تقنيّ: تُعرض في
 * الشاشة كما هي، ومن يرفع درساً لا ينتفع بـ«chunk 7 size mismatch».
 */
final class UploadRejected extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    /** @param  array<string, mixed>  $replace */
    public static function because(string $reason, int $status = 422, array $replace = []): self
    {
        return new self($reason, (string) trans("lectures.create.source.{$reason}", $replace), $status);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'reason' => $this->reason], $this->status);
    }
}
