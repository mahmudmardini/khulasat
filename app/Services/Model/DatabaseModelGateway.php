<?php

declare(strict_types=1);

namespace App\Services\Model;

use App\Actions\Summary\TransitionJob;
use App\Contracts\ModelDriver;
use App\Contracts\ModelGateway;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Support\Model\JsonSchema;
use App\Support\Model\ModelResponse;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * The gateway that reads `model_config` and prices every call — المواصفة §6.
 *
 * أربعة تُفرض هنا، وهي كلّ ما تطلبه T-10:
 *
 *   ١. **النموذج من قاعدة البيانات لا من الشيفرة** — §1: «الأسعار والنماذج
 *      تتبدّل كلّ أشهر»، فتبديلُها لا يكون نشراً.
 *   ٢. **كلّ استدعاء يُسجَّل** بمرحلته ومزوّده وتوكنزه وكلفته ومدّته — §6-أ.
 *   ٣. **المخطّط يُتحقّق منه**، وإخفاقُه إعادةٌ واحدة ثمّ وقوف — §6 و§12.
 *   ٤. الكلفة من أسعار `model_config` لا من ردّ المزوّد.
 *
 * **وما ليس هنا:** السلسلة البديلة وقاطع الدائرة (T-10ب)، والمراحل نفسها
 * وتعليماتها (T-11)، والبوّابة الوهمية (T-10ج).
 *
 * ⚠ **الكلفة تُقيَّد هنا، فلا تُمرَّر ثانيةً إلى
 * {@see TransitionJob}.** ذاك يجمعها في
 * `cost_breakdown` أيضاً، فتمريرُها مرّتين يُضاعف الفاتورة.
 *
 * @see khulasah-build-spec.md §6
 */
class DatabaseModelGateway implements ModelGateway
{
    /** @param  array<string, ModelDriver>  $drivers مفهرسةً باسم المزوّد. */
    public function __construct(
        private readonly array $drivers,
        private readonly ModelCallRecorder $recorder,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $schema
     *
     * @throws ModelCallFailed
     */
    public function call(
        Stage $stage,
        array $messages,
        ?array $schema = null,
        ?SummaryJob $job = null,
    ): ModelResponse {
        $config = ModelConfig::forStage($stage);

        if ($config === null) {
            throw ModelCallFailed::permanent(
                'model_not_configured',
                "لا إعداد فعّال للمرحلة {$stage->value}.",
                $stage,
            );
        }

        $driver = $this->driver((string) $config->provider, $stage);

        $startedAt = hrtime(true);
        $attempts = 0;
        $inputTokens = 0;
        $outputTokens = 0;
        $lastViolations = '';

        // **إعادةٌ واحدة على فشل المخطّط ثمّ وقوف** — §6. ولا تُعالَج هنا
        // إعادةُ الأعطال الشبكية: تلك سلسلةُ §6-أ وموضعها T-10ب.
        do {
            $attempts++;

            $result = $driver->send($config, $messages);

            // **المحاولات الفاشلة تُحتسب في الكلفة** — §6-أ: «الرصيد
            // يُستهلك حتى حين لا يصل رد».
            $inputTokens += $result->inputTokens;
            $outputTokens += $result->outputTokens;

            if ($result->truncated) {
                // **في السجلّ بسقفه وتوكنزه** — T-217: علّتُه الإعدادُ لا النموذج،
                // ويُصلَح من شاشة النماذج لا بضغطةٍ أخرى.
                Log::warning('model.output_truncated', [
                    'stage' => $stage->value,
                    'provider' => (string) $config->provider,
                    'model' => (string) $config->model_id,
                    'max_tokens' => (int) $config->max_tokens,
                    'output_tokens' => $result->outputTokens,
                    'attempt' => $attempts,
                    'summary_job_id' => $job?->id,
                ]);
            }

            if (! $stage->expectsJson()) {
                return $this->finish($stage, $config, $result->content, null, $inputTokens, $outputTokens, $startedAt, $attempts, $job);
            }

            $decoded = $this->decode($result->content);

            if ($decoded !== null) {
                $violations = $schema === null ? [] : JsonSchema::violations($decoded, $schema);

                if ($violations === []) {
                    return $this->finish($stage, $config, $result->content, $decoded, $inputTokens, $outputTokens, $startedAt, $attempts, $job);
                }

                $lastViolations = implode(' · ', array_slice($violations, 0, 5));
            } else {
                // **لا يُصلَح JSON معطوب بالتحليل النصّي** — T-10 صراحةً.
                // والمقطوعُ يُسمّى باسمه — T-217: «ليس JSON» يُلقي العلّة على النموذج.
                $lastViolations = $result->truncated
                    ? 'انقطع الخرج عند سقف التوكنز ('.(int) $config->max_tokens.') قبل أن يكتمل.'
                    : 'الخرج ليس JSON صالحاً.';
            }
        } while ($attempts < 2);

        // الكلفة تُقيَّد ولو وقفت المهمّة: النداءان جريا ودُفع ثمنهما.
        $this->recorder->record(
            $this->response($stage, $config, '', null, $inputTokens, $outputTokens, $startedAt, $attempts),
            $job,
        );

        throw ModelCallFailed::schemaValidation($lastViolations, $stage);
    }

    /** @throws ModelCallFailed */
    private function driver(string $provider, Stage $stage): ModelDriver
    {
        if (! isset($this->drivers[$provider])) {
            throw ModelCallFailed::permanent(
                'provider_not_configured',
                "لا محوّل للمزوّد {$provider}.",
                $stage,
            );
        }

        return $this->drivers[$provider];
    }

    /** @return array<string, mixed>|list<mixed>|null */
    private function decode(string $content): ?array
    {
        // بعض النماذج تلفّ JSON في سياج ```json رغم المنع، فيُنزع السياج
        // وحده. **وهذا ليس إصلاحاً للمعطوب**: ما بقي يُحلَّل أو يُرفض.
        $trimmed = trim($content);
        $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $trimmed) ?? $trimmed;

        try {
            $decoded = json_decode($trimmed, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @param  array<string, mixed>|list<mixed>|null  $decoded */
    private function finish(
        Stage $stage,
        ModelConfig $config,
        string $content,
        ?array $decoded,
        int $inputTokens,
        int $outputTokens,
        int|float $startedAt,
        int $attempts,
        ?SummaryJob $job,
    ): ModelResponse {
        $response = $this->response($stage, $config, $content, $decoded, $inputTokens, $outputTokens, $startedAt, $attempts);

        $this->recorder->record($response, $job);

        return $response;
    }

    /** @param  array<string, mixed>|list<mixed>|null  $decoded */
    private function response(
        Stage $stage,
        ModelConfig $config,
        string $content,
        ?array $decoded,
        int $inputTokens,
        int $outputTokens,
        int|float $startedAt,
        int $attempts,
    ): ModelResponse {
        return new ModelResponse(
            stage: $stage,
            provider: (string) $config->provider,
            modelId: (string) $config->model_id,
            content: $content,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            costUsd: $config->costFor($inputTokens, $outputTokens),
            durationMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            attempts: $attempts,
            decoded: $decoded,
        );
    }
}
