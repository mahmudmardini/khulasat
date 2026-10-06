<?php

declare(strict_types=1);

namespace App\Support\Model;

/**
 * What a provider returned, before the gateway prices it — المواصفة §6-أ.
 *
 * **بلا كلفة**: السعر في `model_config` لا عند المزوّد، فالمحوّل لا يحسبها
 * ولا يعرفها. هذا يُبقي المحوّلات بلا منطق يُختبر مرّتين.
 */
final readonly class DriverResult
{
    public function __construct(
        public string $content,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        /**
         * وقف المزوّدُ عند `max_tokens` قبل أن يتمّ الخرج — T-217.
         *
         * وبغيره يُقال للخرج المقطوع «ليس JSON صالحاً»، فلا يُعرف أنّ السقف
         * هو العلّة لا النموذج. وتوكنزُ التفكير تُخصم من السقف نفسه.
         */
        public bool $truncated = false,
    ) {}
}
