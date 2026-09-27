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
 * OpenAI's Chat Completions API — المواصفة §6-أ.
 *
 * وهو **المحوّل الثاني، ومن مزوّد مختلف**: «البديل من مزوّد مختلف لا من
 * العائلة نفسها — انقطاع المزوّد يصيب نماذجه كلّها» (§6-أ). فوجودُ محوّلين
 * من عائلتين هو شرط صحّة السلسلة البديلة التي تُبنى في T-10ب.
 */
class OpenAiDriver implements ModelDriver
{
    use ClassifiesHttpFailures;

    public function name(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('khulasah.model.openai.api_key')) !== '';
    }

    /** @param  list<array{role: string, content: string}>  $messages */
    /**
     * ترجمة كتل الصور إلى شكل OpenAI — T-09ب.
     *
     * وهو `{type:image_url, image_url:{url:"data:..."}}`، ويخالف شكل
     * أنثروبيك. **والصورة تُحمل في `images` محايدةً** ويترجمها كلّ سائق،
     * بدل أن يُكتب شكلُ مزوّدٍ بعينه في الطبقة العليا.
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
                'type' => 'image_url',
                'image_url' => ['url' => "data:{$image['mime']};base64,{$image['base64']}"],
            ];
        }

        return ['role' => $message['role'], 'content' => $blocks];
    }

    public function send(ModelConfig $config, array $messages): DriverResult
    {
        if (! $this->isConfigured()) {
            throw ModelCallFailed::permanent(
                'provider_not_configured',
                'مفتاح openai غير مضبوط.',
            );
        }

        try {
            $response = Http::withToken((string) config('khulasah.model.openai.api_key'))
                ->timeout($config->timeout_seconds)
                ->post((string) config('khulasah.model.openai.endpoint'), [
                    'model' => $config->model_id,
                    'max_completion_tokens' => $config->max_tokens,
                    // **لا `temperature`** — T-34: نماذج الاستدلال الحالية لا
                    // توثّق قبوله، و`reasoning_effort` هو ضابط العمق عندها.
                    'reasoning_effort' => Effort::forOpenAi((string) $config->thinking_level),
                    // `system` رسالةٌ في المصفوفة هنا، بخلاف أنثروبيك.
                    'messages' => array_map(self::withImages(...), $messages),
                ]);
        } catch (ConnectionException $exception) {
            throw ModelCallFailed::retryable('connection_failed', 'تعذّر الوصول إلى openai.', null, $exception);
        } catch (Throwable $exception) {
            throw ModelCallFailed::retryable('connection_failed', $exception->getMessage(), null, $exception);
        }

        if ($response->failed()) {
            throw $this->classify($response->status(), $this->name(), (string) $response->body());
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content)) {
            throw ModelCallFailed::retryable('malformed_response', 'openai ردّ بشكل غير متوقّع.');
        }

        return new DriverResult(
            content: $content,
            inputTokens: (int) $response->json('usage.prompt_tokens', 0),
            outputTokens: (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
