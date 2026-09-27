<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UsageEvent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row of `usage_ledger` — **مصدر الحقيقة الوحيد للحصص** (المواصفة §4 و§11).
 *
 * لا تُحسب الحصص بعدّ صفوف `summary_jobs` في أيّ مكان: المهمّة الفاشلة
 * والملغاة صفّان لا يُحتسبان، وإعادة التوليد تُحتسب ولا صفّ لها.
 *
 * الحدود نفسها — الشهرية واليومية وسقف الإنفاق — تُطبَّق في T-13.
 */
class UsageRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'usage_ledger';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'event' => UsageEvent::class,
            'units' => 'integer',
            'cost_usd' => 'decimal:4',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @param  array<int, UsageEvent>  $events
     */
    public function scopeOfEvent(Builder $query, array $events): void
    {
        $query->whereIn('event', array_map(fn (UsageEvent $event): string => $event->value, $events));
    }

    /** @param  Builder<$this>  $query */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $until): void
    {
        $query->whereBetween('occurred_at', [$from, $until]);
    }
}
