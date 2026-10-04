<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * سؤالٌ من اختبار — T-195. **وخياراتُه مخلوطةٌ كما تُعرض**، والصحيحُ رقمُه فيها.
 *
 * وخيارُ الشاهد `{text: null, evidence_item_id}`: لفظُه يُقرأ عند العرض من
 * لفظ مصدره، ولا يُحفظ هنا نصٌّ يمكن أن يتباعد عنه.
 *
 * @property list<array{text: string|null, evidence_item_id: int|null}> $options
 */
class QuizQuestion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'options' => 'array',
            'correct_index' => 'integer',
            'axis_index' => 'integer',
            'evidence_item_ids' => 'array',
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
}
