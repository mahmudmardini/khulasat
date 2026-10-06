<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;

/**
 * A finished transcript and where it came from — المواصفة §5-أ.
 */
final readonly class TranscriptResult
{
    public function __construct(
        public string $text,
        public TranscriptSource $source,
        public ?CaptionTrack $track = null,
    ) {}

    /**
     * عدد الكلمات، ويُحفَظ في `summary_jobs.transcript_word_count`.
     *
     * ‏`str_word_count` لا يُعتمد: مبنيّ على حروف لاتينية، ويعيد صفراً على
     * نصّ عربي خالص. والعدّ هنا على الفواصل البيضاء.
     */
    public function wordCount(): int
    {
        return self::countWords($this->text);
    }

    /**
     * عددُ كلمات نصٍّ بالقاعدة نفسها — T-221.
     *
     * يقرؤه النموذجُ أيضاً قبل أن يُنشئ المهمّة، فيُردّ النصُّ الملصوق القصير
     * هناك قبل أن يُحتسب من الحصّة، بالعدّ الذي يقف به الخطّ نفسه.
     */
    public static function countWords(string $text): int
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return count(array_filter($words, static fn (string $word): bool => $word !== ''));
    }

    /**
     * المواصفة §5-أ-7: أقلّ من ٥٠٠ كلمة مؤشّرُ ترجمة ناقصة، **ويُرفع لمدير
     * المحتوى قبل صرف أيّ توكن على النماذج**. فالنصّ الناقص يُنتج ملخّصاً
     * ناقصاً بكلفة تامّة، والحاجز هنا أرخص من الحاجز بعد التوليد.
     */
    public function isTooShort(): bool
    {
        return $this->wordCount() < TranscriptErrorCode::MINIMUM_WORDS;
    }
}
