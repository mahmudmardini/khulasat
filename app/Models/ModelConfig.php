<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Stage;
use Illuminate\Database\Eloquent\Model;

/**
 * Which model runs a stage, and what it costs — المواصفة §4 و§6-أ.
 *
 * **لا `tenant_id` هنا**: الإعداد عامّ للمنصّة لا للجهة. والجهات لا تختار
 * نماذجها، وإنّما يضبطها المشرف العام من لوحته.
 */
class ModelConfig extends Model
{
    protected $table = 'model_config';

    public const UPDATED_AT = 'updated_at';

    public const CREATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stage' => Stage::class,
            'max_tokens' => 'integer',
            'timeout_seconds' => 'integer',
            'max_retries' => 'integer',
            'input_price_per_m' => 'decimal:4',
            'output_price_per_m' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** الإعداد الفعّال لمرحلة، أو `null` إن لم يُضبط. */
    public static function forStage(Stage $stage): ?self
    {
        return static::query()
            ->where('stage', $stage->value)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Cost of one call, in dollars — المواصفة §4.
     *
     * والأسعار «لكلّ مليون توكن»، فتُقسَم عليه. ويُقرَّب إلى أربع خانات
     * كما في `summary_jobs.total_cost_usd`، فلا يختلف المجموع عن أجزائه.
     */
    public function costFor(int $inputTokens, int $outputTokens): float
    {
        $input = ($inputTokens / 1_000_000) * (float) $this->input_price_per_m;
        $output = ($outputTokens / 1_000_000) * (float) $this->output_price_per_m;

        return round($input + $output, 4);
    }
}
