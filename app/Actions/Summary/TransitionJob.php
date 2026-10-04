<?php

declare(strict_types=1);

namespace App\Actions\Summary;

use App\Console\Commands\PruneUploads;
use App\Domain\Summary\JobState;
use App\Domain\Summary\ReviewIncomplete;
use App\Enums\TranscriptErrorCode;
use App\Models\SummaryJob;
use App\Models\SummaryJobTransition;
use App\Support\Transcript\UploadStore;
use Illuminate\Support\Facades\DB;

/**
 * The one door through which a job changes state — المواصفة §5.
 *
 * يفعل أربعة أشياء في معاملة واحدة:
 *   ١. يتحقّق من الخريطة — {@see JobState::transitionTo()}.
 *   ٢. يتحقّق من شروط المجال: لا خروج من المراجعة ولا نشر وشاهدٌ معلّق.
 *   ٣. يكتب الحالة الجديدة ويضمّ الكلفة الجزئية إلى `cost_breakdown`.
 *   ٤. يسجّل الانتقال بالزمن والكلفة في `summary_job_transitions`.
 *
 * والثالث والرابع في معاملة واحدة عمداً: حالةٌ تغيّرت بلا سجلّ تُفسد
 * لوحة المراقبة والفوترة معاً.
 */
final class TransitionJob
{
    public function handle(
        SummaryJob $job,
        JobState $to,
        float $costUsd = 0.0,
        ?string $errorCode = null,
        ?string $errorDetail = null,
    ): SummaryJob {
        $from = $job->state;

        // يرمي InvalidTransition إن لم تكن الحافّة في الخريطة.
        $from->transitionTo($to);

        $this->guardEvidenceSettled($job, $from, $to);

        $attempt = $job->attempt;
        $occurredAt = now();

        DB::transaction(function () use ($job, $from, $to, $costUsd, $errorCode, $errorDetail, $attempt, $occurredAt): void {
            $job->writeTransition(function (SummaryJob $job) use ($from, $to, $costUsd, $errorCode, $errorDetail, $occurredAt): void {
                $job->state = $to;

                // الكلفة الجزئية تُنسب إلى المرحلة **المنتهية**، فهي ثمن العمل
                // الذي أوصل إلى هذا الانتقال لا ثمن ما لم يبدأ بعد.
                if ($costUsd > 0.0) {
                    $breakdown = $job->cost_breakdown ?? [];
                    $breakdown[$from->value] = round((float) ($breakdown[$from->value] ?? 0) + $costUsd, 4);

                    $job->cost_breakdown = $breakdown;
                    $job->total_cost_usd = round((float) $job->total_cost_usd + $costUsd, 4);
                }

                // عدّاد المحاولات الآلية يخصّ الحالة الواحدة، فيُصفَّر عند مغادرتها.
                $job->attempt = 0;

                $job->error_code = $errorCode;
                $job->error_detail = $errorDetail;

                if ($from === JobState::Queued) {
                    $job->started_at = $occurredAt;
                }

                if ($to->isTerminal()) {
                    $job->finished_at = $occurredAt;
                }
            });

            SummaryJobTransition::create([
                'summary_job_id' => $job->id,
                'tenant_id' => $job->tenant_id,
                'from_state' => $from,
                'to_state' => $to,
                'attempt' => $attempt,
                'cost_usd' => round($costUsd, 4),
                'error_code' => $errorCode,
                'occurred_at' => $occurredAt,
            ]);
        });

        $this->releaseUpload($job, $to, $errorCode);

        return $job;
    }

    /**
     * Drop the uploaded recording once no retry can use it — §5-أ-4-ب.
     *
     * **والملفّ يُحذف عند الإلغاء، وعند إخفاقٍ لا يُصلحه إلّا رفعٌ جديد**
     * (درسٌ أطول من الحدّ، أو نصٌّ أقصر من أن يُلخَّص): إبقاؤه حينئذٍ نصفُ
     * غيغابايت لا يقرؤه أحد. **ويُبقى عند الإخفاق العارض** — عطلٌ عند المزوّد
     * أو مهلة — فـ«أعد المحاولة» تُفرّغه بلا رفعٍ ثانٍ، ثمّ يُكنس بعد
     * `UPLOAD_RETENTION_DAYS` ({@see PruneUploads}).
     *
     * وهنا لا في كلّ مُلغٍ ومُوقِف: الإلغاءُ من شاشة الجهة ومن لوحة المشرف،
     * والوقوفُ من الخطّ — كلّها تمرّ من هذا الانتقال.
     */
    private function releaseUpload(SummaryJob $job, JobState $to, ?string $errorCode): void
    {
        if ($job->upload_path === null) {
            return;
        }

        $transient = in_array($errorCode, [
            TranscriptErrorCode::TranscriptionFailed->value,
            TranscriptErrorCode::YtdlpTimeout->value,
        ], true);

        if ($to !== JobState::Cancelled && ($to !== JobState::Failed || $transient)) {
            return;
        }

        UploadStore::delete($job->upload_path);

        $job->forceFill(['upload_path' => null, 'upload_name' => null])->save();
    }

    /**
     * المواصفة §5: **لا نشر عند `needs_review`، ولا إعداد يتجاوزه.**
     *
     * يُفحص طرفا الطريق معاً — الخروج من المراجعة، والوصول إلى النشر — لأنّ
     * فحص أحدهما وحده يترك بابين: مهمّةٌ رُفعت للمراجعة فخرجت بلا حسم،
     * ومهمّةٌ أُضيف إليها شاهدٌ معلّق بعد الكتابة ثم نُشرت.
     *
     * @throws ReviewIncomplete
     */
    private function guardEvidenceSettled(SummaryJob $job, JobState $from, JobState $to): void
    {
        $guarded = ($from === JobState::NeedsReview && $to === JobState::Writing)
            || $to === JobState::Published;

        if (! $guarded) {
            return;
        }

        $pending = $job->pendingEvidenceCount();

        if ($pending > 0) {
            throw ReviewIncomplete::forJob((int) $job->id, $pending);
        }
    }
}
