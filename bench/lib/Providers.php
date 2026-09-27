<?php

declare(strict_types=1);

namespace Bench;

use RuntimeException;

/**
 * Raw HTTP to three providers — deliberately outside the Laravel app.
 *
 * **ولا يُستعمل محوّل المنتج هنا.** القياس يقيس المزوّدين لا شيفرتنا،
 * ويشمل مزوّداً ثالثاً (Google) لا محوّل له في المنتج بعد. ولو مرّ عبر
 * `DatabaseModelGateway` لاحتاج قاعدة بيانات وحصصاً وسقفَ إنفاق، وقاس
 * طبقاتنا معها.
 *
 * **والمفاتيح من البيئة وحدها** — CLAUDE.md §2 القاعدة السادسة.
 */
final class Providers
{
    /** @return array{text: string, in: int, out: int, ms: int, http: int, error: ?string} */
    public static function call(string $modelKey, string $system, string $user, int $maxTokens, int $timeout): array
    {
        $model = Catalog::get($modelKey);

        return match ($model['provider']) {
            'anthropic' => self::anthropic($model, $system, $user, $maxTokens, $timeout),
            'openai' => self::openai($model, $system, $user, $maxTokens, $timeout),
            'google' => self::google($model, $system, $user, $maxTokens, $timeout),
            default => throw new RuntimeException("مزوّد غير معروف: {$model['provider']}"),
        };
    }

    /**
     * سببُ التوقّف إخفاقاً معلَناً، و`null` لتوقّفٍ طبيعيّ — T-62.
     *
     * **فالمبتور ليس نجاحاً.** بلغ `sonnet-5` في T-59 سقفَه بالتمام
     * (١٦٬٠٠٠) فانقطع JSON في منتصف كلمة، وطبع الحارسُ ✓ — ولم يكن يقرأ
     * `stop_reason` ولا `finish_reason`. **ونتيجةٌ مبتورة تُعدّ ناجحةً أسوأُ
     * من نتيجةٍ ساقطة**: الساقطةُ تُرى.
     *
     * وما سوى البتر إخفاقٌ كذلك: رفضٌ، أو حجبُ محتوى، أو `RECITATION` عند
     * جوجل — وهي تحجب نقلَ نصٍّ محفوظٍ حرفاً، **ونقلُ الآية حرفاً عملُ
     * المرحلة ٥ كلُّه**. وسببٌ مجهول لا يُعدّ إخفاقاً: لا يُحكم بما لم يُقل.
     */
    public static function stopError(string $provider, ?string $reason): ?string
    {
        $reason = trim((string) $reason);

        $normal = match ($provider) {
            'anthropic' => ['end_turn', 'stop_sequence'],
            'openai' => ['stop'],
            'google' => ['STOP'],
            default => [],
        };

        if ($reason === '' || in_array($reason, $normal, true)) {
            return null;
        }

        if (in_array($reason, ['max_tokens', 'length', 'MAX_TOKENS'], true)) {
            return "مبتور: بلغ سقفَ التوكنز ({$reason})";
        }

        return "توقّف قبل تمامه: {$reason}";
    }

    public static function keyFor(string $provider): ?string
    {
        $variable = match ($provider) {
            'anthropic' => 'ANTHROPIC_API_KEY',
            'openai' => 'OPENAI_API_KEY',
            'google' => 'GOOGLE_AI_API_KEY',
            default => null,
        };

        $key = $variable === null ? '' : (string) (getenv($variable) ?: '');

        return trim($key) === '' ? null : trim($key);
    }

    /** @param array{model: string, effort: string} $model */
    private static function anthropic(array $model, string $system, string $user, int $maxTokens, int $timeout): array
    {
        $key = self::keyFor('anthropic') ?? throw new RuntimeException('ANTHROPIC_API_KEY غير مضبوط.');

        // **لا `temperature`** — مهجورٌ على الجيل الحالي ويُعيد 400 (T-34).
        $response = self::post(
            'https://api.anthropic.com/v1/messages',
            [
                'x-api-key: '.$key,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            [
                'model' => $model['model'],
                'max_tokens' => $maxTokens,
                'output_config' => ['effort' => $model['effort']],
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ],
            $timeout,
        );

        if ($response['error'] !== null) {
            return $response + ['text' => '', 'in' => 0, 'out' => 0];
        }

        $body = $response['json'];
        $text = '';

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return [
            'text' => $text,
            // وتوكنز التفكير داخلةٌ في `output_tokens` عند أنثروبيك.
            'in' => (int) ($body['usage']['input_tokens'] ?? 0),
            'out' => (int) ($body['usage']['output_tokens'] ?? 0),
            'ms' => $response['ms'],
            'http' => $response['http'],
            'error' => self::stopError('anthropic', $body['stop_reason'] ?? null),
        ];
    }

    /** @param array{model: string, effort: string} $model */
    private static function openai(array $model, string $system, string $user, int $maxTokens, int $timeout): array
    {
        $key = self::keyFor('openai') ?? throw new RuntimeException('OPENAI_API_KEY غير مضبوط.');

        $response = self::post(
            'https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer '.$key, 'content-type: application/json'],
            [
                'model' => $model['model'],
                'max_completion_tokens' => $maxTokens,
                'reasoning_effort' => $model['effort'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ],
            $timeout,
        );

        if ($response['error'] !== null) {
            return $response + ['text' => '', 'in' => 0, 'out' => 0];
        }

        $body = $response['json'];

        return [
            'text' => (string) ($body['choices'][0]['message']['content'] ?? ''),
            'in' => (int) ($body['usage']['prompt_tokens'] ?? 0),
            // و`completion_tokens` تشمل توكنز الاستدلال عند OpenAI.
            'out' => (int) ($body['usage']['completion_tokens'] ?? 0),
            'ms' => $response['ms'],
            'http' => $response['http'],
            'error' => self::stopError('openai', $body['choices'][0]['finish_reason'] ?? null),
        ];
    }

    /** @param array{model: string, effort: string} $model */
    private static function google(array $model, string $system, string $user, int $maxTokens, int $timeout): array
    {
        $key = self::keyFor('google') ?? throw new RuntimeException('GOOGLE_AI_API_KEY غير مضبوط.');

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode($model['model']).':generateContent?key='.rawurlencode($key);

        $response = self::post(
            $url,
            ['content-type: application/json'],
            [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig' => [
                    'maxOutputTokens' => $maxTokens,
                    'thinkingConfig' => ['thinkingLevel' => $model['effort']],
                ],
            ],
            $timeout,
        );

        if ($response['error'] !== null) {
            return $response + ['text' => '', 'in' => 0, 'out' => 0];
        }

        $body = $response['json'];
        $text = '';

        foreach ($body['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        $usage = $body['usageMetadata'] ?? [];

        return [
            'text' => $text,
            'in' => (int) ($usage['promptTokenCount'] ?? 0),
            // **وتُجمع توكنز التفكير هنا يدوياً**: جوجل تفصلها عن الخرج،
            // وهي مسعَّرة معه. فمن أهملها أنقص الكلفة الحقيقية.
            'out' => (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0),
            'ms' => $response['ms'],
            'http' => $response['http'],
            'error' => self::stopError('google', $body['candidates'][0]['finishReason'] ?? null),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $payload
     * @return array{json: array<string, mixed>, ms: int, http: int, error: ?string}
     */
    private static function post(string $url, array $headers, array $payload, int $timeout): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $startedAt = hrtime(true);
        $raw = curl_exec($handle);
        $ms = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);

        if (! is_string($raw) || $raw === '') {
            return ['json' => [], 'ms' => $ms, 'http' => $status, 'error' => $curlError ?: 'ردٌّ فارغ'];
        }

        $json = json_decode($raw, true);

        if (! is_array($json)) {
            return ['json' => [], 'ms' => $ms, 'http' => $status, 'error' => 'ردٌّ ليس JSON: '.mb_substr($raw, 0, 200)];
        }

        if ($status >= 400) {
            $message = $json['error']['message'] ?? mb_substr($raw, 0, 300);

            return ['json' => $json, 'ms' => $ms, 'http' => $status, 'error' => "HTTP {$status}: {$message}"];
        }

        return ['json' => $json, 'ms' => $ms, 'http' => $status, 'error' => null];
    }
}
