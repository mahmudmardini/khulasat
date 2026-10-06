<?php

declare(strict_types=1);

namespace App\Actions\Usage;

use App\Actions\Summary\ResumeFailedJob;
use App\Enums\UsageEvent;
use App\Http\Controllers\LectureController;
use App\Models\SummaryJob;
use App\Models\UsageRecord;

/**
 * Gives back the monthly unit of a summary that never started — T-222.
 *
 * الوحدةُ تُقيَّد عند إنشاء المهمّة ({@see LectureController::store()}).
 * فإن وقفت المهمّة عند استخراج نصّ الدرس (ترجمةٌ أقصر من ٥٠٠ كلمة، فيديو
 * محجوب، مصدرٌ بلا صوت) **لم يُكتب ملخّصٌ ولم يُنادَ نموذجٌ لكتابته**، فلا
 * وجه لاحتسابها. والرسالة تقول للمستخدم «لم نصرف من حصّتكم شيئاً»، وهذا ما
 * يجعلها صادقة.
 *
 * ★ **صفٌّ سالبٌ لا حذفُ الصفّ.** `usage_ledger` سجلٌّ يُجمع، و`QuotaGuard`
 * يجمع `units`، فالوحدةُ والسالبةُ تتعادلان ويبقى في السجلّ ما جرى.
 *
 * **ولا يُردّ إلّا ما قُيِّد على المهمّة نفسها، ومرّةً واحدة.** فالمهمّة
 * الخَلَف من «أعد المحاولة» ({@see ResumeFailedJob})
 * لا وحدةَ عليها، وما دفعته قُيِّد على سلفها: لا يُردّ هنا شيءٌ لم يُدفع.
 * ودقائقُ التفريغ الصوتي حصّةٌ أخرى، صُرفت فعلاً، فلا تُردّ.
 */
final class RefundUnstartedSummary
{
    public function __construct(private readonly RecordUsage $recordUsage) {}

    public function handle(SummaryJob $job): ?UsageRecord
    {
        $charged = (int) UsageRecord::query()
            ->where('summary_job_id', $job->id)
            ->where('event', UsageEvent::Generate->value)
            ->sum('units');

        if ($charged <= 0) {
            return null;
        }

        return $this->recordUsage->handle(
            tenant: $job->tenant,
            event: UsageEvent::Generate,
            units: -$charged,
            job: $job,
        );
    }
}
