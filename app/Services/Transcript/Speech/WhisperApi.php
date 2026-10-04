<?php

declare(strict_types=1);

namespace App\Services\Transcript\Speech;

use App\Contracts\SpeechToText;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A Whisper-style transcription API — المواصفة §5-أ-4.
 *
 * **لا يُستعمل إلا بإعداد صريح** (`WHISPER_PROVIDER`)، والافتراضي
 * {@see FakeSpeechToText} — CLAUDE.md §2 القاعدة السابعة.
 *
 * والمفتاح من `.env` وحده، لا من قاعدة البيانات ولا من `model_config`
 * — القاعدة السادسة والمواصفة §12.
 */
class WhisperApi implements SpeechToText
{
    public function name(): string
    {
        return (string) config('khulasah.transcript.whisper.provider', 'whisper');
    }

    public function maxBytes(): int
    {
        return (int) config('khulasah.transcript.whisper.max_bytes');
    }

    public function pricePerMinute(): float
    {
        return (float) config('khulasah.transcript.whisper.price_per_minute');
    }

    /** @param  list<string>  $glossary */
    public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
    {
        $key = (string) config('khulasah.transcript.whisper.api_key');

        if ($key === '') {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'مفتاح خدمة التفريغ غير مضبوط.',
            );
        }

        try {
            $response = Http::withToken($key)
                ->timeout((int) config('khulasah.transcript.whisper.timeout', 600))
                ->attach('file', (string) file_get_contents($audioPath), basename($audioPath))
                ->post((string) config('khulasah.transcript.whisper.endpoint'), array_filter([
                    'model' => (string) config('khulasah.transcript.whisper.model'),

                    // **`language=ar` إلزامياً** — المواصفة §5-أ-4. وبلا تصريح
                    // باللغة يُخمّنها المزوّد، وقد يقرأ العربية نقلاً لاتينياً
                    // أو يُترجمها، وكلاهما يُفسد ألفاظ الشواهد.
                    'language' => $languageCode,

                    // المسرد يُمرَّر تلميحاً: أسماء الرواة والكتب والمصطلحات
                    // الشرعية — «فهو يرفع دقّة الأعلام كثيراً» (§5-أ-4).
                    'prompt' => $glossary === [] ? null : implode('، ', $glossary),
                ]));
        } catch (Throwable $exception) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّر الوصول إلى خدمة التفريغ.',
                $exception,
            );
        }

        if ($response->failed()) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                "خدمة التفريغ أخفقت برمز {$response->status()}.",
            );
        }

        $text = $response->json('text');

        if (! is_string($text) || trim($text) === '') {
            // شكلُ ردٍّ غير متوقّع يُعامَل إخفاقاً لا نصّاً فارغاً: الفرق بين
            // «لم أسمع شيئاً» و«لم أفهم الرد» فرقٌ يُبنى عليه قرارُ نشر.
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'خدمة التفريغ ردّت بشكل غير متوقّع.',
            );
        }

        return $text;
    }
}
