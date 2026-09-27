<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Stage;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Transcript\WhisperAudio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One recorded model-gateway call — T-22.
 *
 * تحليلٌ لا فوترة: {@see UsageRecord} يبقى مصدر الحقيقة للحصص والكلفة
 * الإجمالية، وهذا الجدول يفصّل كل استدعاء بمرحلته ومزوّده ونموذجه وتوكنزه
 * — ما لا يحمله `cost_breakdown` المجموع بالمرحلة وحدها.
 *
 * ولا صفَّ فيه لدقائق التفريغ (Whisper): تلك ليست استدعاء بوّابة نماذج،
 * وتُقيَّد كلفتُها في `usage_ledger` وحده — {@see WhisperAudio}.
 */
class ModelCall extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'stage' => Stage::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cost_usd' => 'decimal:4',
            'duration_ms' => 'integer',
            'attempt' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }

    /** @param  Builder<$this>  $query */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $until): void
    {
        $query->whereBetween('occurred_at', [$from, $until]);
    }
}
