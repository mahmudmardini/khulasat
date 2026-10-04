<?php

declare(strict_types=1);

namespace App\Services\Model;

use App\Enums\UsageEvent;
use App\Models\ModelCall;
use App\Models\SummaryJob;
use App\Models\UsageRecord;
use App\Models\VerifyCheck;
use App\Services\Quota\SpendCap;
use App\Support\Model\ModelResponse;
use App\Support\TenantContext;
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
    /** طلبُ أداة التحقّق الذي تُقيَّد عليه نداءاتٌ بلا مهمّة — T-181. */
    private ?VerifyCheck $check = null;

    /**
     * Book every job-less call made inside the callback on a verify check — T-181.
     *
     * ★ **نداءُ الأداة لا مهمّةَ له**، والبوّابة تقيّد الكلفة على المهمّة. فلو
     * مُرّر `null` كما هو لسُجّل في السجلّ وحده، **ولما رآه سقفُ الإنفاق**:
     * أداةٌ عامّة تصرف بلا أن يُحسب صرفها. فيُعلَن الطلبُ هنا، وتُقيَّد عليه
     * كلُّ كلفة — الناجحةُ والمخفقةُ معاً، كما في البوّابة.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function bookingTo(VerifyCheck $check, callable $callback): mixed
    {
        $this->check = $check;

        try {
            return $callback();
        } finally {
            // العاملُ طويلُ العمر: طلبٌ لا يرث حسابَ ما قبله.
            $this->check = null;
        }
    }

    public function record(ModelResponse $response, ?SummaryJob $job = null): void
    {
        Log::info('model.call', $response->toLog() + ['summary_job_id' => $job?->id, 'verify_check_id' => $this->check?->id]);

        if ($response->costUsd <= 0.0) {
            return;
        }

        if ($job === null) {
            if ($this->check !== null) {
                $this->bookOnCheck($response, $this->check);
            }

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
     * نداءُ أداة التحقّق: صفٌّ في `model_calls` بلا جهة ولا مهمّة، ومجموعُه
     * على الطلب. **ولا صفّ في `usage_ledger`**: ذاك دفترُ الجهات وحصصها
     * وفوترتها، ولا جهة هنا. وسقفُ الإنفاق يجمع هذه الصفوف إليه — {@see SpendCap}.
     */
    private function bookOnCheck(ModelResponse $response, VerifyCheck $check): void
    {
        DB::transaction(function () use ($response, $check): void {
            // **بلا جهةٍ صراحةً**: والحاجزُ يملأ الجهة من السياق إن وُجد،
            // وطلبُ الأداة لا جهة له ولو أرسله صاحبُ حسابٍ في لوحته.
            app(TenantContext::class)->withoutScope(fn (): ModelCall => ModelCall::query()->forceCreate([
                'tenant_id' => null,
                'summary_job_id' => null,
                'verify_check_id' => $check->id,
                'stage' => $response->stage,
                'provider' => $response->provider,
                'model_id' => $response->modelId,
                'input_tokens' => $response->inputTokens,
                'output_tokens' => $response->outputTokens,
                'cost_usd' => $response->costUsd,
                'duration_ms' => $response->durationMs,
                'attempt' => $response->attempts,
                'occurred_at' => now(),
            ]));

            $check->forceFill(['cost_usd' => round((float) $check->cost_usd + $response->costUsd, 4)])->save();
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
