<?php

declare(strict_types=1);

namespace App\Actions\Usage;

use App\Enums\UsageEvent;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;

/**
 * Writes one row to the ledger — المواصفة §4 و§11.
 *
 * **هنا وحده تُقيَّد الحصص.** ومن عدّ صفوف `summary_jobs` ليعرف ما استُهلك
 * أخطأ في الاتجاهين: المهمّة الفاشلة صفٌّ لا يُحتسب، وإعادة التوليد
 * استهلاكٌ لا صفَّ له.
 */
final class RecordUsage
{
    public function handle(
        Tenant $tenant,
        UsageEvent $event,
        int $units = 1,
        float $costUsd = 0.0,
        ?SummaryJob $job = null,
    ): UsageRecord {
        return UsageRecord::create([
            'tenant_id' => $tenant->id,
            'summary_job_id' => $job?->id,
            'event' => $event,
            'units' => $units,
            'cost_usd' => round($costUsd, 4),
            'occurred_at' => now(),
        ]);
    }
}
