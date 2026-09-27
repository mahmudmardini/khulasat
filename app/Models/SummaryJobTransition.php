<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Summary\JobState;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded move of a job from one state to another — المواصفة §5.
 *
 * **سجلّ لا يُعدَّل ولا يُحذف.** لا timestamps ولا تحديث: الصفّ يُكتب مرّة
 * عند وقوع الانتقال، وقيمته كلّها في أنّه لم يُمسّ بعد كتابته.
 */
class SummaryJobTransition extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_state' => JobState::class,
            'to_state' => JobState::class,
            'attempt' => 'integer',
            'cost_usd' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }
}
