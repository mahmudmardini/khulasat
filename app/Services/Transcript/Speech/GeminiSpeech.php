<?php

declare(strict_types=1);

namespace App\Services\Transcript\Speech;

use App\Contracts\SpeechToText;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gemini as the speech-to-text service — المواصفة §5-أ-4، وقرار الفريق في
 * 4 أكتوبر 2026: Gemini Flash مزوّدُ التفريغ.
 *
 * **ويخدم المسارين معاً** من خلف {@see SpeechToText} نفسه: رابط يوتيوب بلا
 * ترجمة عربية (صوتُه من yt-dlp)، وملفٌّ رُفع من الجهاز. فلا يعرف من أين جاء
 * المقطع، ولا يحتاج أن يعرف.
 *
 * **لا يُستعمل إلا بإعداد صريح** (`WHISPER_PROVIDER=gemini`)، والافتراضي
 * {@see FakeSpeechToText} — CLAUDE.md §2 القاعدة السابعة. والمفتاح مفتاحُ
 * Gemini نفسه في البوّابة (`GOOGLE_AI_API_KEY`)، من `.env` وحده.
 *
 * وثلاثةٌ تخالف {@see WhisperApi}:
 *   ١. **نموذجٌ لغويّ لا مفرِّغٌ مخصَّص**، فتعليماتُه نصٌّ يُراجَع
 *      (`prompts/transcription/GEMINI.md`): أهمّ ما فيها ألّا يصحّح آيةً
 *      أو حديثاً نطقه المتكلّم محرَّفاً، وإلّا أخفى ما تبحث عنه طبقة التحقّق.
 *   ٢. **الصوت داخل الطلب** (`inlineData`)، وحدُّ الطلب كلّه 20MB بعد
 *      base64 — فحدُّه أصغر من حدّ Whisper، والتقطيع يبدأ أبكر.
 *   ٣. **نهايةُ الردّ تُقرأ**: نموذجٌ لغويّ قد يقف عند حدّ التوكنز أو يمتنع
 *      (`RECITATION`) فيعيد نصّاً ناقصاً بنجاح. والنصّ الناقص يُعامَل إخفاقاً.
 */
class GeminiSpeech implements SpeechToText
{
    /** علامةُ «لا كلام» في التعليمات — تُميّز الصمت المقصود من الردّ الفارغ. */
    public const NO_SPEECH = '[[NO_SPEECH]]';

    /** صيغ الصوت عند Gemini بلاحقة الملفّ — وما لم يُعرف يُسأل عنه فاحصُ المحتوى. */
    private const MIME_TYPES = [
        'm4a' => 'audio/mp4',
        'mp4' => 'audio/mp4',
        'mp3' => 'audio/mp3',
        'wav' => 'audio/wav',
        'aac' => 'audio/aac',
        'flac' => 'audio/flac',
        'ogg' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'webm' => 'audio/webm',
    ];

    public function name(): string
    {
        return 'gemini';
    }

    public function maxBytes(): int
    {
        return (int) config('khulasah.transcript.gemini.max_bytes');
    }

    public function pricePerMinute(): float
    {
        return (float) config('khulasah.transcript.gemini.price_per_minute');
    }

    /** @param  list<string>  $glossary */
    public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
    {
        $key = trim((string) config('khulasah.model.google.api_key'));

        if ($key === '') {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'مفتاح Gemini غير مضبوط (GOOGLE_AI_API_KEY).',
            );
        }

        $audio = @file_get_contents($audioPath);

        if ($audio === false || $audio === '') {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّرت قراءة مقطع الصوت.',
            );
        }

        $response = $this->send($key, [
            'systemInstruction' => ['parts' => [['text' => self::instruction($languageCode, $glossary)]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    ['inlineData' => ['mimeType' => self::mimeType($audioPath), 'data' => base64_encode($audio)]],
                    ['text' => 'فرّغ هذا المقطع.'],
                ],
            ]],
            // **لا `temperature`** — نظير {@see \App\Services\Model\Drivers\GoogleDriver}.
            'generationConfig' => [
                'maxOutputTokens' => (int) config('khulasah.transcript.gemini.max_output_tokens'),
                'thinkingConfig' => ['thinkingLevel' => (string) config('khulasah.transcript.gemini.thinking_level')],
            ],
        ]);

        return self::textOf($response);
    }

    /**
     * Instructions from the reviewed file, then this request's language and glossary.
     *
     * @param  list<string>  $glossary
     */
    public static function instruction(string $languageCode, array $glossary = []): string
    {
        $path = base_path('prompts/transcription/GEMINI.md');
        $instruction = is_file($path) ? trim((string) file_get_contents($path)) : '';

        if ($instruction === '') {
            // التعليمات شرطٌ لا زينة: بلاها يلخّص النموذج ويصحّح، فلا يُنادى.
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعليمات التفريغ مفقودة: prompts/transcription/GEMINI.md',
            );
        }

        // `language=ar` إلزامياً — §5-أ-4، ولو لم يكن حقلاً هنا بل سطراً.
        $instruction .= "\n\nلغة المقطع: {$languageCode}.";

        if ($glossary !== []) {
            // المسرد تلميحٌ لرسم الأعلام لا قائمةٌ تُدرَج — §5-أ-4.
            $instruction .= "\n\nأعلامٌ ومصطلحات قد ترد في الدرس، فاكتبها بهذا الرسم إن سمعتها، "
                .'ولا تُدخل منها ما لم يُقل: '.implode('، ', $glossary).'.';
        }

        return $instruction;
    }

    /**
     * POST with a short retry on the failures that pass — 429 and 5xx.
     *
     * درسُ ساعةٍ ستّةُ مقاطع، و«النموذج مشغول» (503) يقع على Flash كثيراً.
     * فإخفاقٌ عابرٌ في المقطع الخامس كان يُسقط الدرس كلّه، وإعادتُه من الطابور
     * تُفرِّغ المقاطع الأربعة الناجحة ثانيةً وتدفع ثمنها مرّتين.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws TranscriptFailed
     */
    private function send(string $key, array $payload): Response
    {
        $endpoint = rtrim((string) config('khulasah.model.google.endpoint'), '/')
            .'/'.rawurlencode((string) config('khulasah.transcript.gemini.model')).':generateContent';

        try {
            // **المفتاح ترويسةٌ لا `?key=`**: رسالةُ إخفاق الاتصال تحمل الرابط
            // كاملاً، والرابطُ يبلغ السجلّ وسببَ الإخفاق المحفوظ مع المهمّة.
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout((int) config('khulasah.transcript.gemini.timeout'))
                ->retry(
                    3,
                    (int) config('khulasah.transcript.gemini.retry_sleep_ms', 2_000),
                    static fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->status() === 429 || $exception->response->serverError())),
                    throw: false,
                )
                ->post($endpoint, $payload);
        } catch (Throwable $exception) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّر الوصول إلى Gemini.',
                $exception,
            );
        }

        if ($response->failed()) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                "Gemini أخفق برمز {$response->status()}. ".mb_substr((string) $response->body(), 0, 300),
            );
        }

        return $response;
    }

    /**
     * The transcript, or a failure — never a silently partial text.
     *
     * @throws TranscriptFailed
     */
    private static function textOf(Response $response): string
    {
        $blocked = $response->json('promptFeedback.blockReason');

        if (is_string($blocked) && $blocked !== '') {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                "رفض Gemini المقطع ({$blocked}).",
            );
        }

        $finish = (string) $response->json('candidates.0.finishReason', '');

        /*
         * **ما لم يقف عند `STOP` نصٌّ ناقص.** `MAX_TOKENS` قطعٌ في وسط الدرس،
         * و`RECITATION` امتناعٌ عن نصٍّ محفوظ — وقد يقع على تلاوةٍ طويلة.
         * وقبولُ أيٍّ منهما يُلخّص نصفَ درسٍ على أنّه درسٌ كامل.
         */
        if ($finish !== 'STOP') {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'توقّف Gemini قبل آخر المقطع ('.($finish === '' ? 'بلا سبب' : $finish).').',
            );
        }

        $parts = $response->json('candidates.0.content.parts');

        $text = is_array($parts) ? implode('', array_map(
            static fn (array $part): string => (string) ($part['text'] ?? ''),
            // خلاصةُ التفكير ليست تفريغاً، إن عادت.
            array_filter($parts, static fn (mixed $part): bool => is_array($part) && ($part['thought'] ?? false) !== true),
        )) : '';

        $text = trim(self::withoutFence($text));

        if ($text === self::NO_SPEECH) {
            // صمتٌ أو موسيقى، قاله النموذج صراحةً — مقطعٌ لا نصّ فيه، لا عطل.
            return '';
        }

        if ($text === '') {
            // ردٌّ فارغ بلا علامة الصمت يُعامَل إخفاقاً — نظير {@see WhisperApi}:
            // فالفارغُ صامتاً يُسقط عشر دقائق من الدرس ولا يراه أحد.
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'Gemini ردّ بلا نصّ.',
            );
        }

        return trim(str_replace(self::NO_SPEECH, '', $text));
    }

    /** نموذجٌ لغويّ قد يلفّ النصّ في سياج markdown رغم التعليمات. */
    private static function withoutFence(string $text): string
    {
        $text = trim($text);

        if (preg_match('/\A```[^\n]*\n(.*)\n```\z/su', $text, $match) === 1) {
            return $match[1];
        }

        return $text;
    }

    private static function mimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (isset(self::MIME_TYPES[$extension])) {
            return self::MIME_TYPES[$extension];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $path);

        return is_string($mime) && str_starts_with($mime, 'audio/') ? $mime : 'audio/mp4';
    }
}
