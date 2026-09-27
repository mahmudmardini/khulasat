<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * One caption track offered for a video — المواصفة §5-أ-2.
 */
final readonly class CaptionTrack
{
    public function __construct(
        public string $languageCode,
        public bool $isAutomatic,
        public ?string $ext = null,
        public ?string $url = null,
    ) {}

    /** المسار الأوّل في جدول §5-أ-2 يدويّ، والثاني آليّ. وكلاهما `captions`. */
    public function isManual(): bool
    {
        return ! $this->isAutomatic;
    }
}
