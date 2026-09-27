<?php

declare(strict_types=1);

namespace App\Services\Model;

use App\Enums\UsageEvent;
use App\Models\ModelCall;
use App\Models\SummaryJob;
use App\Models\UsageRecord;
use App\Support\Model\ModelResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Books what a model call cost — المواصفة §6-أ «التسجيل» و§11.
 *
 * يكتب في أربعة مواضع، ولكلٍّ غرضه:
 *
 *   ١. **`summary_jobs.cost_breakdown`** موزَّعةً على المرحلة، و
 *      `total_cost_usd` مجموعةً. وهذا ما يقرؤه سقفُ الإنفاق العامّ (§11).
 *   ٢. **`usage_ledger`** — «مصدر الحقيقة للحصص والفوترة» (§4).
 *   ٣. **`model_calls`** — تفصيلُ الاستدعاء نفسِه: مزوّدُه ونموذجُه
 *      وتوكنزه، لا مجموعُه فقط. تحليلٌ لشاشة الكلفة (T-22) لا فوترة.
 *   ٤. **السجلّ** بتفصيل الاستدعاء، لتشخيصٍ لا يحتاج قاعدة بيانات.
 *
 * ### وحدةُ الصفّ صفرٌ، وهذا مقصود
 *
 * المواصفة §4 تحصر `usage_ledger.event` في ثلاث: `generate` و`regenerate`
 * و`transcribe`. فليس لاستدعاء النموذج حدثٌ خاصّ به، وهو جزءٌ من `generate`
 * لا حدثٌ مستقلّ.
 *
 * ولو كُتب صفٌّ بـ `units: 1` لكلّ استدعاء **لاستهلك الملخّصُ الواحد ستّ
 * وحدات من حصّة شهرية عدَّتها بالملخّصات**، فنفدت حصّةُ الجهة في سُدس
 * وقتها. فتُكتب الكلفة والوحدةُ صفر: الفوترة تُقرأ من `cost_usd`،
 * والحصّةُ من `units`، ولا يفسد أحدهما الآخر.
 *
 * ووحدةُ الحصّة تُقيَّد مرّةً واحدة عند إنشاء المهمّة — {@see UsageEvent}.
 */
class ModelCallRecorder
{
    public function record(ModelResponse $response, ?SummaryJob $job = null): void
    {
        Log::info('model.call', $response->toLog() + ['summary_job_id' => $job?->id]);

        if ($job === null || $response->costUsd <= 0.0) {
            return;
        }

        DB::transaction(function () use ($response, $job): void {
            $this->bookOnJob($response, $job);

            UsageRecord::create([
                'tenant_id' => $job->tenant_id,
                'summary_job_id' => $job->id,
                'event' => UsageEvent::Generate,

                // صفرٌ عمداً — انظر شرح الصنف. الكلفةُ تُقيَّد، والحصّةُ لا.
                'units' => 0,

                'cost_usd' => $response->costUsd,
                'occurred_at' => now(),
            ]);

            ModelCall::create([
                'tenant_id' => $job->tenant_id,
                'summary_job_id' => $job->id,
                'stage' => $response->stage,
                'provider' => $response->provider,
                'model_id' => $response->modelId,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'cost_usd' => $response->costUsd,
                'duration_ms' => $response->durationMs,
                'attempt' => $response->attempts,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * الكلفة تُنسب إلى مرحلتها لا إلى حالة المهمّة الجارية.
     *
     * والفرق يظهر في المرحلتين السادسة والسابعة: كلتاهما تجري داخل
     * `rendering`، ولو نُسبت الكلفة إلى الحالة لاختلطتا في مفتاح واحد
     * ولم يُعرف ثمنُ الكاروسيل من ثمن بيانات الإخراج.
     */
    private function bookOnJob(ModelResponse $response, SummaryJob $job): void
    {
        $key = $response->stage->costKey();

        $breakdown = $job->cost_breakdown ?? [];
        $breakdown[$key] = round((float) ($breakdown[$key] ?? 0) + $response->costUsd, 4);

        // ‏`save` لا `saveQuietly`: حارسُ آلة الحالات في {@see SummaryJob}
        // يقوم على حدث `updating`، وإسكاتُه يُعطّله. ولا نلمس `state` هنا
        // فلا يُثار الحارس، ويبقى قائماً لو مسّها تعديلٌ لاحق.
        $job->forceFill([
            'cost_breakdown' => $breakdown,
            'total_cost_usd' => round((float) $job->total_cost_usd + $response->costUsd, 4),
        ])->save();
    }
}
