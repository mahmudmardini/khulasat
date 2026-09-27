<?php

declare(strict_types=1);

namespace App\Services\Model\Drivers;

use App\Contracts\ModelDriver;
use App\Exceptions\ModelCallFailed;
use App\Models\ModelConfig;
use App\Support\Model\DriverResult;
use App\Support\Model\Effort;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google's Gemini `generateContent` API — المواصفة §6-أ، T-41.
 *
 * **المزوّد الثالث** الذي أجازه {@see ModelDriver} دون مسّ منطق: «فإضافة
 * مزوّدٍ ثالثٍ لا تمسّ منطقاً». وشكلها يخالف الاثنين معاً في أربعة:
 *   ١. النموذج جزءٌ من الرابط لا من جسم الطلب (`models/{id}:generateContent`).
 *   ٢. المفتاح مفتاح استعلامٍ `?key=`، لا ترويسة — كما في `bench/lib/Providers.php`.
 *   ٣. الرسالة `parts` — قائمة كتل — لا `content` نصّاً، حتى بلا صور.
 *   ٤. `system` معاملٌ مستقلّ `systemInstruction`، كأنثروبيك لا كـOpenAI.
 */
class GoogleDriver implements ModelDriver
{
    use ClassifiesHttpFailures;

    public function name(): string
    {
        return 'google';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('khulasah.model.google.api_key')) !== '';
    }

    /**
     * ترجمة رسالة إلى `parts` عند Gemini — T-09ب لصورها، وشكلها هنا وحده.
     *
     * الصورة `{inlineData: {mimeType, data}}`، كتلةٌ ثالثة تخالف
     * `image_url` عند OpenAI و`source.base64` عند أنثروبيك.
     *
     * @param  array<string, mixed>  $message
     * @return array{role: string, parts: list<array<string, mixed>>}
     */
    private static function toContent(array $message): array
    {
        $parts = [['text' => (string) $message['content']]];

        foreach ($message['images'] ?? [] as $image) {
            $parts[] = ['inlineData' => ['mimeType' => $image['mime'], 'data' => $image['base64']]];
        }

        return ['role' => $message['role'], 'parts' => $parts];
    }

    /** @param  list<array{role: string, content: string}>  $messages */
    public function send(ModelConfig $config, array $messages): DriverResult
    {
        if (! $this->isConfigured()) {
            throw ModelCallFailed::permanent(
                'provider_not_configured',
                'مفتاح google غير مضبوط.',
            );
        }

        // `system` معاملٌ مستقلّ عند Gemini أيضاً، ولا يدخل `contents`.
        $system = trim(implode("\n\n", array_map(
            static fn (array $message): string => (string) $message['content'],
            array_values(array_filter($messages, static fn (array $m): bool => $m['role'] === 'system')),
        )));

        $contents = array_values(array_map(
            self::toContent(...),
            array_filter($messages, static fn (array $message): bool => $message['role'] !== 'system'),
        ));

        // **لا `temperature`** — نظير T-34 عند المزوّدين الآخرين: العمق
        // يُضبط بـ`thinkingLevel`، لا بحرارة.
        $payload = array_filter([
            'systemInstruction' => $system === '' ? null : ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $config->max_tokens,
                'thinkingConfig' => ['thinkingLevel' => Effort::forGoogle((string) $config->thinking_level)],
            ],
        ], static fn (mixed $value): bool => $value !== null);

        $endpoint = rtrim((string) config('khulasah.model.google.endpoint'), '/')
            .'/'.rawurlencode($config->model_id).':generateContent'
            .'?key='.rawurlencode((string) config('khulasah.model.google.api_key'));

        try {
            $response = Http::timeout($config->timeout_seconds)->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            throw ModelCallFailed::retryable('connection_failed', 'تعذّر الوصول إلى google.', null, $exception);
        } catch (Throwable $exception) {
            throw ModelCallFailed::retryable('connection_failed', $exception->getMessage(), null, $exception);
        }

        if ($response->failed()) {
            throw $this->classify($response->status(), $this->name(), (string) $response->body());
        }

        $parts = $response->json('candidates.0.content.parts');

        if (! is_array($parts)) {
            throw ModelCallFailed::retryable('malformed_response', 'google ردّ بشكل غير متوقّع.');
        }

        $text = implode('', array_map(
            static fn (array $part): string => (string) ($part['text'] ?? ''),
            array_filter($parts, 'is_array'),
        ));

        return new DriverResult(
            content: $text,
            inputTokens: (int) $response->json('usageMetadata.promptTokenCount', 0),
            // **توكنز التفكير تُجمع هنا يدوياً**: جوجل تفصلها في الردّ عن
            // توكنز الخرج، وهي مسعَّرة معه — أُغفلت لأُنقصت الكلفة الحقيقية.
            outputTokens: (int) $response->json('usageMetadata.candidatesTokenCount', 0)
                + (int) $response->json('usageMetadata.thoughtsTokenCount', 0),
        );
    }
}
