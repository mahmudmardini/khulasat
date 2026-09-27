<?php

declare(strict_types=1);

namespace App\Support\Model;

use App\Enums\Stage;

/**
 * One completed model call — المواصفة §6-أ «التسجيل».
 *
 * تحمل ما يُسجَّل عن الاستدعاء كاملاً: «المزوّد، والنموذج، وأهي أساسية أم
 * بديلة أم إعادة، وسبب الفشل، والكلفة».
 */
final readonly class ModelResponse
{
    public function __construct(
        public Stage $stage,
        public string $provider,
        public string $modelId,
        public string $content,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public float $costUsd = 0.0,
        public int $durationMs = 0,
        /** كم استدعاءً جرى فعلاً — الأولى واحد، ومع إعادةٍ اثنان. */
        public int $attempts = 1,
        /** الخرج بعد التحقّق من المخطّط، أو `null` لمرحلةٍ لا JSON لها. */
        public ?array $decoded = null,
    ) {}

    /** @return array<string, mixed> صورةٌ للسجلّ، بلا محتوى الرد. */
    public function toLog(): array
    {
        return [
            'stage' => $this->stage->value,
            'provider' => $this->provider,
            'model_id' => $this->modelId,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cost_usd' => $this->costUsd,
            'duration_ms' => $this->durationMs,
            'attempts' => $this->attempts,
        ];
    }
}
