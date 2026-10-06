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
 * Anthropic's Messages API — المواصفة §6-أ.
 *
 * وشكلُها يخالف OpenAI في ثلاثة تحتاج انتباهاً:
 *   ١. `system` **معاملٌ مستقلّ** لا رسالةٌ في `messages`.
 *   ٢. المصادقة بترويسة `x-api-key` لا `Authorization: Bearer`.
 *   ٣. التوكنز في `usage.input_tokens` و`output_tokens`.
 */
class AnthropicDriver implements ModelDriver
{
    use ClassifiesHttpFailures;

    public function name(): string
    {
        return 'anthropic';
    }

    /**
     * ترجمة كتل الصور إلى شكل أنثروبيك — T-09ب.
     *
     * وهو `{type:image, source:{type:base64, media_type, data}}`، ويخالف
     * شكل OpenAI. **ولذلك تُحمل الصورة في المفتاح `images` محايدةً** ويترجمها
     * كلّ سائق، بدل أن يُكتب شكلُ مزوّدٍ بعينه في الطبقة العليا.
     *
     * والنصّ يبقى أوّلاً: النموذج يقرأ التعليمة ثمّ ينظر.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private static function withImages(array $message): array
    {
        if (($message['images'] ?? []) === []) {
            unset($message['images']);

            return $message;
        }

        $blocks = [['type' => 'text', 'text' => (string) $message['content']]];

        foreach ($message['images'] as $image) {
            $blocks[] = [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $image['mime'],
                    'data' => $image['base64'],
                ],
            ];
        }

        return ['role' => $message['role'], 'content' => $blocks];
    }

    public function isConfigured(): bool
    {
        return trim((string) config('khulasah.model.anthropic.api_key')) !== '';
    }

    /** @param  list<array{role: string, content: string}>  $messages */
    public function send(ModelConfig $config, array $messages): DriverResult
    {
        if (! $this->isConfigured()) {
            throw ModelCallFailed::permanent(
                'provider_not_configured',
                'مفتاح anthropic غير مضبوط.',
            );
        }

        // `system` معاملٌ مستقلّ عند أنثروبيك. ويُجمع ما كان منه في الرسائل،
        // ويبقى محتوى المستخدم في `messages` وحده — §12.
        $system = trim(implode("\n\n", array_map(
            static fn (array $message): string => $message['content'],
            array_values(array_filter($messages, static fn (array $m): bool => $m['role'] === 'system')),
        )));

        $conversation = array_values(array_map(
            self::withImages(...),
            array_filter(
                $messages,
                static fn (array $message): bool => $message['role'] !== 'system',
            ),
        ));

        // **لا `temperature`** — T-34: مهجورٌ على Claude 4.7 فما بعد، وضبطُه
        // بغير الافتراضي يُعيد 400، و400 ممّا لا يُعاد عليه في §6-أ.
        // وعمقُ التفكير يُطلب بـ`output_config.effort` بدلاً منه.
        $payload = array_filter([
            'model' => $config->model_id,
            'max_tokens' => $config->max_tokens,
            'output_config' => ['effort' => Effort::forAnthropic((string) $config->thinking_level)],
            'system' => $system === '' ? null : $system,
            'messages' => $conversation,
        ], static fn (mixed $value): bool => $value !== null);

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('khulasah.model.anthropic.api_key'),
                'anthropic-version' => (string) config('khulasah.model.anthropic.version'),
            ])
                ->timeout($config->timeout_seconds)
                ->post((string) config('khulasah.model.anthropic.endpoint'), $payload);
        } catch (ConnectionException $exception) {
            throw ModelCallFailed::retryable('connection_failed', 'تعذّر الوصول إلى anthropic.', null, $exception);
        } catch (Throwable $exception) {
            throw ModelCallFailed::retryable('connection_failed', $exception->getMessage(), null, $exception);
        }

        if ($response->failed()) {
            throw $this->classify($response->status(), $this->name(), (string) $response->body());
        }

        $blocks = $response->json('content');

        if (! is_array($blocks)) {
            throw ModelCallFailed::retryable('malformed_response', 'anthropic ردّ بشكل غير متوقّع.');
        }

        // الردّ كتلٌ، ومنها ما ليس نصّاً (التفكير مثلاً)، فيُؤخذ النصّ وحده.
        $text = implode('', array_map(
            static fn (array $block): string => (string) ($block['text'] ?? ''),
            array_filter($blocks, static fn (mixed $block): bool => is_array($block) && ($block['type'] ?? null) === 'text'),
        ));

        return new DriverResult(
            content: $text,
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            truncated: $response->json('stop_reason') === 'max_tokens',
        );
    }
}
