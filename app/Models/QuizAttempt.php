<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * محاولةُ مشاركٍ — T-195. يدخل باسمه وحده، ويُحفظ معه IP (قرار @HasanSiwi).
 *
 * **و`attempt_number` لـ(الاسم + IP)**: الأولى وحدها تدخل الإحصاءات (T-201)،
 * وما بعدها يُعرض في جدول المشاركين.
 */
class QuizAttempt extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
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
