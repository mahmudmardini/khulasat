<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * محاولةٌ في اختبار — T-195. **بلا صاحب**: لا اسمَ ولا IP (قرار @HasanSiwi،
 * ٤ أكتوبر ٢٠٢٦). إجاباتٌ ودرجةٌ ووقت، تدخل التقارير مجموعةً (T-201).
 */
class QuizAttempt extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'score' => 'integer',
            'duration_seconds' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /** نسبةُ الدرجة من مئة، بلا كسور. */
    public function percent(): int
    {
        return $this->total === 0 ? 0 : (int) round(100 * (int) $this->score / $this->total);
    }
}
