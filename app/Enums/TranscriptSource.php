<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a transcript came from — المواصفة §4 و§5-أ.
 */
enum TranscriptSource: string
{
    /** ترجمات المنصّة — المسار الأوّل، وأرخصها. */
    case Captions = 'captions';

    /** تفريغ صوتي — يُحتسب من `transcription_minutes_quota`. */
    case Whisper = 'whisper';

    /** نصّ يلصقه المستخدم — لا كلفة تفريغ. */
    case Manual = 'manual';

    /** المسار اليدوي لا يمرّ بالتفريغ، فلا يُحتسب من دقائقه — المواصفة §11. */
    public function consumesTranscriptionMinutes(): bool
    {
        return $this === self::Whisper;
    }
}
