<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Actions\Stages\BuildQuiz;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\Quiz;
use App\Models\SummaryJob;
use App\Services\Quota\SpendCap;
use App\Support\Quiz\QuizQuestionDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * يبني اختبار الملخّص ويحفظه — T-195. **من الخطّ ومن اللوحة كليهما.**
 *
 * ★ **وإخفاقُه لا يُسقط شيئاً.** في الخطّ يُحفظ اختبارٌ «تعذّر» بسببه ويُنشر
 * الملخّص بلا زرّ، وفي اللوحة يُعاد بزرّ. فلا يقف ملخّصٌ تمّ عملُه كلُّه
 * **من أجل مخرَجٍ ثانٍ** — نظير الكاروسيل والترجمة.
 *
 * ★★ **وبعد أوّل محاولةٍ لا يُعاد التوليد.** الأسئلةُ الجديدة بمعرّفاتٍ
 * جديدة، فتنقطع إجاباتُ من سبق عن أسئلتها وتختلط الإحصاءات (T-201).
 */
final class GenerateQuiz
{
    public function __construct(
        private readonly BuildQuiz $build,
        private readonly SpendCap $spendCap,
    ) {}

    /**
     * @param  bool  $fromPanel  نداءٌ من اللوحة لا من الخطّ: يُفحص سقفُ الإنفاق
     *                           قبله (§2، القاعدة الخامسة)، وإخفاقُ إعادةِ توليدٍ
     *                           لا يمحو الاختبار القائم.
     *
     * @throws RuntimeException حين يُرفض البناء: اختبارٌ له محاولات، أو سقفٌ مبلوغ،
     *                          أو إعادةُ توليدٍ أخفقت والقائمُ باقٍ.
     */
    public function handle(SummaryJob $job, bool $fromPanel = false): Quiz
    {
        $existing = Quiz::query()->where('summary_job_id', $job->id)->first();

        if ($existing?->hasAttempts()) {
            throw new RuntimeException(trans('quiz.panel.locked_regenerate'));
        }

        // **والسقفُ يُفحص في الطريقين** (§2، القاعدة الخامسة): الخطُّ فحصه عند
        // بدء المهمّة، والاختبارُ نداءٌ بعد ذلك بساعات قد يكون السقفُ بُلغ فيها.
        if ($this->spendCap->isHalted() || $this->spendCap->breachedReason() !== null) {
            if ($fromPanel || $existing?->isReady()) {
                throw new RuntimeException(trans('quiz.panel.capped'));
            }

            return $this->failed($job, $existing, ModelCallFailed::permanent('spend_cap', 'سقفُ الإنفاق مبلوغ.', Stage::Quiz));
        }

        try {
            $built = $this->build->handle($job);
        } catch (ModelCallFailed $failed) {
            Log::warning('quiz.build_failed', [
                'summary_job_id' => $job->id,
                'code' => $failed->errorCode,
                'reason' => $failed->getMessage(),
            ]);

            // **اختبارٌ قائمٌ لا يُمحى بإخفاق إعادة توليده**: صاحبُه يبقى بما راجعه.
            if ($existing?->isReady()) {
                throw new RuntimeException(trans('quiz.panel.failed'));
            }

            return $this->failed($job, $existing, $failed);
        }

        return DB::transaction(function () use ($job, $existing, $built): Quiz {
            $quiz = $existing ?? $this->fresh($job);

            $quiz->forceFill([
                'state' => Quiz::STATE_READY,
                'failure_reason' => null,
                'generated_at' => now(),
            ])->save();

            $quiz->questions()->delete();

            foreach ($built['questions'] as $index => $draft) {
                /** @var QuizQuestionDraft $draft */
                $quiz->questions()->create([
                    'position' => $index + 1,
                    'kind' => $draft->kind,
                    'level' => $draft->level,
                    'prompt' => $draft->prompt,
                    'options' => $draft->options,
                    'correct_index' => $draft->correctIndex,
                    'explanation' => $draft->explanation,
                    'axis_index' => $draft->axisIndex,
                    'evidence_item_ids' => $draft->evidenceItemIds,
                ]);
            }

            return $quiz->refresh();
        });
    }

    private function failed(SummaryJob $job, ?Quiz $existing, ModelCallFailed $failed): Quiz
    {
        $quiz = $existing ?? $this->fresh($job);

        $quiz->forceFill([
            'state' => Quiz::STATE_FAILED,
            // **رسالةُ المخطّط تُحفظ كما هي** — هي التي تقول «بقي سؤالان بعد
            // الحراسة». وما عداها رمزُه، ولا يُعرض رمزُ مزوّدٍ على مدير محتوى.
            'failure_reason' => mb_substr(
                $failed->errorCode === 'schema_validation_failed' ? $failed->getMessage() : $failed->errorCode,
                0,
                250,
            ),
        ])->save();

        return $quiz;
    }

    private function fresh(SummaryJob $job): Quiz
    {
        return new Quiz([
            'tenant_id' => $job->tenant_id,
            'summary_job_id' => $job->id,
            'token' => Quiz::newToken(),
            'status' => Quiz::STATUS_OPEN,
            'feedback' => Quiz::FEEDBACK_END,
        ]);
    }
}
