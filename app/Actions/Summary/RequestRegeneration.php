<?php

declare(strict_types=1);

namespace App\Actions\Summary;

use App\Actions\Usage\RecordUsage;
use App\Domain\Summary\RegenerationRefused;
use App\Enums\UsageEvent;
use App\Models\SummaryJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a human's decision to regenerate a summary — المواصفة §5 و§11.
 *
 * **الفاعل `User` إلزاميّ وغير قابل لأن يكون null.** وهذا هو الفحص: لا
 * مسار آليّ يستطيع استدعاء هذا الفعل، فمراحل النماذج لا تُعاد آلياً عند
 * نجاح الاستدعاء ورداءة النتيجة. والإعادة تُحتسب من `regenerations_per_summary`
 * ومن الحصّة الشهرية في `usage_ledger`.
 *
 * إنشاء المهمّة البديلة ووضعها في الطابور من نصيب T-11. هنا الحدّ والقيد وحدهما.
 */
final class RequestRegeneration
{
    public function __construct(private readonly RecordUsage $recordUsage) {}

    public function handle(SummaryJob $job, User $actor): SummaryJob
    {
        if ((int) $actor->tenant_id !== (int) $job->tenant_id) {
            throw RegenerationRefused::foreignActor((int) $job->id);
        }

        if (! $actor->role->canPublish()) {
            throw RegenerationRefused::insufficientRole($actor->role->value);
        }

        $allowed = (int) $job->tenant->regenerations_per_summary;

        if ($job->regeneration_count >= $allowed) {
            throw RegenerationRefused::limitReached($job->regeneration_count, $allowed);
        }

        return DB::transaction(function () use ($job): SummaryJob {
            $job->increment('regeneration_count');

            $this->recordUsage->handle(
                tenant: $job->tenant,
                event: UsageEvent::Regenerate,
                units: 1,
                job: $job,
            );

            return $job;
        });
    }
}
