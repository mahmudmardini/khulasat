<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Stages\CleanTranscript;
use App\Actions\Stages\ExtractEvidence;
use App\Actions\Stages\ExtractStructure;
use App\Actions\Stages\RenderAndPublish;
use App\Actions\Stages\ResolveTranscript;
use App\Actions\Stages\VerifyEvidence;
use App\Actions\Stages\WriteBody;
use App\Actions\Summary\ScheduleStageRetry;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\AutomaticRetryRefused;
use App\Domain\Summary\JobState;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\HadithCorpusUnavailable;
use App\Exceptions\ModelCallFailed;
use App\Exceptions\TranscriptFailed;
use App\Models\SummaryJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one thing that makes the pipeline run — المواصفة §5.
 *
 * أفعالُ المراحل مبنيّةٌ ومختبَرة، **وكلُّ فعلٍ ينقل الحالة بنفسه**. فليس
 * على هذه المهمّة إلا أن تسأل: أين المهمّة الآن؟ ثمّ تنادي صاحبَ تلك
 * الحالة، وتُعيد الكرّة حتى تقف. ولذلك هي موزّعةٌ على الحالات لا سلسلةٌ
 * مكتوبة بالترتيب: **السلسلة المكتوبة لا تُستأنف من وسطها**، وهذه تُستأنف
 * من حيث وقفت لأنّها لا تعرف إلا الحالة الراهنة.
 *
 * وثلاثة تُوقفها:
 *   - `needs_review` — تنتظر إنساناً، ولا مهلة لها (§5). ويُستأنف الخطّ
 *     بإرسالها ثانيةً بعد حسم الشواهد.
 *   - حالة نهائية — `published` أو `failed` أو `cancelled`.
 *   - إخفاق — يُعاد آلياً حيث تحتمل المرحلة، وإلّا وقفت عند `failed`.
 */
class RunSummaryPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * **الحدّ الحقيقي في المجال لا هنا.** {@see ScheduleStageRetry} يرفض
     * الرابعة في المرحلة الواحدة، وثلاثُ مراحل تحتمل الإعادة (§5)، فثلاثٌ
     * في ثلاث وواحدةٌ للتشغيل الأوّل. وهذا سقفُ بنيةٍ يمنع الدوران، لا
     * سياسةَ إعادة — تلك في آلة الحالات.
     */
    public int $tries = 10;

    /** ٣٠ ثانية، ثمّ ٦٠، ثمّ ١٢٠ — {@see JobState::retryDelaySeconds()}. */
    public function backoff(): array
    {
        return [
            JobState::retryDelaySeconds(1),
            JobState::retryDelaySeconds(2),
            JobState::retryDelaySeconds(3),
        ];
    }

    public function __construct(public readonly int $summaryJobId) {}

    public function handle(): void
    {
        $job = SummaryJob::query()->withoutGlobalScopes()->find($this->summaryJobId);

        if ($job === null) {
            return;
        }

        while (true) {
            $job->refresh();

            $state = $job->state;

            if ($state->isTerminal()) {
                return;
            }

            /*
             * **الاستئناف من حيث وقف، لا من أوّل الخطّ.** المهمّة في
             * `needs_review` تنتظر إنساناً، فإن بقي شاهدٌ معلّق وقفت. وإن
             * حُسمت الشواهد كلّها مضت إلى الكتابة — وإرسالُ هذه المهمّة
             * ثانيةً هو **آليةُ الاستئناف** كلُّها، فلا يحتاج حاسمُ الشواهد
             * أن يعرف أين وقف الخطّ.
             *
             * والحدّ الرابع سليم: من يعبر هنا لا شاهد معلّق عنده،
             * و{@see TransitionJob} يفحصها ثانيةً قبل أن يفتح الباب.
             */
            if ($state === JobState::NeedsReview) {
                if ($job->pendingEvidenceCount() > 0) {
                    return;
                }

                app(TransitionJob::class)->handle($job, JobState::Writing);

                continue;
            }

            try {
                $this->advance($job, $state);
            } catch (Throwable $failure) {
                $this->stumble($job, $state, $failure);

                return;
            }

            // حارسٌ ضدّ فعلٍ لا ينقل الحالة: لولاه لدار المشغّل أبداً.
            if ($job->refresh()->state === $state) {
                $this->halt($job, 'stage_did_not_advance', "المرحلة {$state->value} لم تنقل الحالة.");

                return;
            }
        }
    }

    /** ينادي صاحبَ الحالة الراهنة. وكلٌّ منهم ينقل الحالة بنفسه. */
    private function advance(SummaryJob $job, JobState $state): void
    {
        match ($state) {
            // لا عمل في `queued` إلا فتحُ الطريق: التفريغ يقع في حالته.
            JobState::Queued => app(TransitionJob::class)->handle($job, JobState::Transcribing),

            JobState::Transcribing => app(ResolveTranscript::class)->handle($job),
            JobState::Cleaning => app(CleanTranscript::class)->handle($job),
            JobState::ExtractingStructure => app(ExtractStructure::class)->handle($job),
            JobState::ExtractingEvidence => app(ExtractEvidence::class)->handle($job),
            JobState::Verifying => app(VerifyEvidence::class)->handle($job),
            JobState::Writing => app(WriteBody::class)->handle($job),
            JobState::Rendering => app(RenderAndPublish::class)->handle($job),

            default => $this->halt($job, 'unhandled_state', "لا مرحلة تُنادى في {$state->value}."),
        };
    }

    /**
     * الإخفاق: يُعاد حيث تحتمل المرحلة، وإلّا وقف.
     *
     * **والرمز يُكتب مع الانتقال لا قبله** — معيار القبول الرابع: كتابتُه
     * أوّلاً ثمّ الانتقال يمسحه، إذ يُصفّر {@see TransitionJob} الرمزَ في
     * كلّ انتقال. فتُقرأ في شاشة المتابعة رسالةٌ فارغة تحت حالةٍ فاشلة.
     */
    private function stumble(SummaryJob $job, JobState $state, Throwable $failure): void
    {
        [$code, $detail] = $this->describe($failure);

        if ($state->allowsAutomaticRetry() && $this->isRetryable($failure)) {
            try {
                $delay = app(ScheduleStageRetry::class)->handle($job);

                /*
                 * **تُرسَل من جديد ولا تُطلَق بـ`release()`.** فالإطلاق لا
                 * أثر له إلا داخل عاملٍ حقيقي — يعود صامتاً إن نُوديت
                 * `handle()` مباشرةً، فتقف المهمّة في حالتها بلا رمزٍ ولا
                 * إعادة، وهو أسوأ من الوقوف الصريح. والعدّاد في قاعدة
                 * البيانات هو الحدّ، فالإرسال ينتهي عند الثالثة.
                 */
                self::dispatch($this->summaryJobId)->delay($delay);

                return;
            } catch (AutomaticRetryRefused) {
                // استُنفدت الثلاث. يسقط إلى الوقوف أدناه بالرمز نفسه.
            }
        }

        Log::warning('وقف خطّ التوليد', [
            'summary_job_id' => $job->id,
            'state' => $state->value,
            'error_code' => $code,
        ]);

        $this->halt($job, $code, $detail);
    }

    private function halt(SummaryJob $job, string $code, string $detail): void
    {
        // `needs_review` لا تنتقل إلى `failed` (§5)، فلا يُحاوَل.
        if (! $job->state->canTransitionTo(JobState::Failed)) {
            return;
        }

        app(TransitionJob::class)->handle(
            job: $job,
            to: JobState::Failed,
            errorCode: $code,
            errorDetail: $detail,
        );
    }

    /**
     * **إخفاقُ استدعاءٍ سقط يُعاد، وإخفاقُ نتيجةٍ رديئة لا** — §5 و§6-أ.
     * وما لم يُصنَّف يُعامَل غيرَ قابلٍ للإعادة: الإخفاق في الاتّجاه الآمن.
     */
    private function isRetryable(Throwable $failure): bool
    {
        return match (true) {
            $failure instanceof ModelCallFailed => $failure->retryable,
            // **قائمةُ سماحٍ لا قائمةَ منع.** المهلة وإخفاق المفرّغ عارضان
            // يُصلحهما التكرار. وما عداهما — رابطٌ مرفوض، ومدّةٌ زائدة،
            // وفيديو محجوب أو محذوف، ونصٌّ أقصر من أن يُلخَّص — لا يُصلحه
            // تكرارٌ، وإعادتُه تحرق دقائق الجهة على ما عُلم عطبُه.
            $failure instanceof TranscriptFailed => in_array(
                $failure->errorCode,
                [TranscriptErrorCode::YtdlpTimeout, TranscriptErrorCode::TranscriptionFailed],
                strict: true,
            ),
            default => false,
        };
    }

    /** @return array{string, string} */
    private function describe(Throwable $failure): array
    {
        return match (true) {
            $failure instanceof ModelCallFailed => [$failure->errorCode, $failure->getMessage()],
            $failure instanceof TranscriptFailed => [$failure->errorCode->value, $failure->getMessage()],
            // T-213: عطلٌ عندنا لا في الدرس. ولا إعادةَ آلية في `verifying`، فيقف و«أعد المحاولة» يستأنف منها.
            $failure instanceof HadithCorpusUnavailable => [HadithCorpusUnavailable::CODE, $failure->getMessage()],
            default => ['pipeline_failed', $failure->getMessage()],
        };
    }
}
