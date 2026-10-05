<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Summary\RequestRegeneration;
use App\Actions\Summary\ResumeFailedJob;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Domain\Summary\RegenerationRefused;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Admin\AdminJobController;
use App\Jobs\RunSummaryPipeline;
use App\Models\Complaint;
use App\Models\SummaryJob;
use App\Support\Ui\JobProgress;
use App\Support\Ui\LiveFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Following one job to its end — SCREENS.md §4، والمهمّة T-16.
 *
 * **بلا نسب مئوية.** «النسبة المخترعة تكذب، والمستخدم يكتشف كذبها.» فالمعروض
 * مراحلُ مسمّاة وحالُ كلٍّ منها، وهي معلومة يقيناً من آلة الحالات.
 */
class SummaryJobController extends Controller
{
    public function show(SummaryJob $job): Response
    {
        return Inertia::render('Jobs/Show', $this->payload($job));
    }

    /**
     * حال المهمّة للاستطلاع كل ثلاث ثوانٍ — SCREENS.md §4.
     *
     * وردٌّ خفيف عمداً: الصفحة مرسومة، والمطلوب تبدّل المراحل وحده. وإعادةُ
     * الصفحة كاملةً كل ثلاث ثوانٍ تُثقل الخادم بلا فائدة.
     */
    public function status(SummaryJob $job): JsonResponse
    {
        return response()->json($this->payload($job));
    }

    /**
     * «أعد المحاولة» — SCREENS.md §4، الزرّ الأوّل عند الإخفاق.
     *
     * **وهي إعادةُ توليدٍ بقرار إنسان، لا إعادةُ محاولةٍ آلية.** فالحالة
     * `failed` نهائية ولا انتقال منها (§5)، والمهمّة المتوقّفة قد صرفت من
     * الحصّة ما صرفت. ولذلك تمرّ بـ{@see RequestRegeneration}: تُحسب من
     * `regenerations_per_summary` ومن الحصّة الشهرية، ويُرفض ما تجاوز.
     *
     * وتُنشأ مهمّةٌ جديدة للدرس نفسه بدل إحياء المتوقّفة، فيبقى سجلّ
     * الانتقالات صادقاً: ما وقع قد وقع، والمحاولة الثانية محاولةٌ ثانية.
     *
     * ★ **وتبدأ من آخر مرحلةٍ ناجحة، لا من الصفر** — T-93. كانت هذا
     * المسار يخلق مهمّةً فارغة فتُعاد المراحل الغالية كلُّها، بينما
     * `AdminJobController::retry()` يستأنف منذ T-21 — {@see ResumeFailedJob}
     * هو المنطق نفسه، هنا وهناك.
     */
    public function retry(Request $request, SummaryJob $job, RequestRegeneration $regeneration, ResumeFailedJob $resume): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        try {
            $regeneration->handle($job, $user);
        } catch (RegenerationRefused $refused) {
            return back()->withErrors(['retry' => $refused->getMessage()]);
        }

        $replacement = $resume->handle($job);

        // `RequestRegeneration` يقول إنّ الإرسال «من نصيب T-11»، وهذا موضعه.
        RunSummaryPipeline::dispatch((int) $replacement->id);

        return to_route('jobs.show', $replacement);
    }

    /**
     * إلغاء العالق — و`cancelled` مسموحة من كلّ حالةٍ غير نهائية (§5)،
     * كما في إلغاء المشرف {@see AdminJobController::cancel()}.
     */
    public function cancel(SummaryJob $job, TransitionJob $transition): RedirectResponse
    {
        if ($job->state->isTerminal()) {
            return back()->withErrors(['cancel' => trans('jobs.follow.cancel_failed')]);
        }

        $transition->handle($job, JobState::Cancelled);

        return back();
    }

    /** @return array<string, mixed> */
    private function payload(SummaryJob $job): array
    {
        $job->loadMissing(['lecture', 'tenant']);

        return [
            'job' => [
                'id' => $job->id,
                'title' => $job->lecture->title_ar,
                'speaker' => $job->lecture->speaker_name,
                'state' => $job->state->value,
                'status' => JobProgress::badgeFor($job),
                'steps' => JobProgress::steps($job),
                // وقتُ المراجعة يُطرح من «استغرق» — T-171.
                'review' => JobProgress::review($job),
                // **الحالة النهائية توقف الاستطلاع.** واستطلاعٌ لا يتوقّف
                // يبقى يضرب الخادم بعد أن انتهى كلّ شيء.
                'settled' => $job->state->isTerminal(),
                'needs_review' => $job->state === JobState::NeedsReview,
                'pending_evidence' => $job->pendingEvidenceCount(),
                /*
                 * الزمن — T-27. وشاشةُ انتظارٍ لا تقول منذ متى تنتظر تُقلق
                 * بلا سبب: دقيقتان تُحتملان، وعشرون تعني أنّ شيئاً وقف.
                 * ويُحسب الفرقُ في المتصفّح لا هنا، فالخادم لا يعرف ساعةَ
                 * من يقرأ.
                 */
                'started_at' => $job->started_at?->toIso8601String(),
                'finished_at' => $job->finished_at?->toIso8601String(),
                // طولُ المحاضرة، ليُقرأ بجانب زمن الإعداد. وللنصّ الملصوق `null`.
                'source_seconds' => $job->lecture->duration_seconds,
                // **رسالة عربية لا كود خطأ** — §القواعد العامّة.
                'error' => $this->error($job),
                /*
                 * إعاداتُ هذا الملخّص — T-203. «أعد المحاولة» بعد الفشل إعادةُ
                 * توليدٍ تُحتسب منها ومن الحصّة الشهرية، فتُقال قبل الضغط.
                 */
                'regenerations' => [
                    'used' => (int) $job->regeneration_count,
                    'limit' => (int) ($job->tenant?->regenerations_per_summary ?? 0),
                ],
                /*
                 * ★ **زرّ «غيّرْ المصدر» ليس فعلاً لكلّ خطأ** — T-91. وكان
                 * يظهر مع كلّ إخفاق: تعطّل مزوّد النماذج، أو عطلٌ داخليّ —
                 * ولا علاقة لواحدٍ منهما بمصدر الدرس، فتبديله لا يُصلح شيئاً.
                 */
                'error_offers_change_source' => $this->errorIsAboutTranscriptSource($job),

                // ما وقع على الصفحة المنشورة، إن وقع — T-28.
                'takedown' => $this->takedown($job),

                // ما أُنتج حتى الآن، حقيقياً لا مصوغاً — T-83.
                'live' => LiveFeed::of($job),
            ],
        ];
    }

    /**
     * إشعار صاحب الجهة بإزالة صفحته ولماذا — T-28.
     *
     * ★ **وهو نصفُ الفعل لا زيادةٌ عليه.** فإزالةُ صفحةٍ بلا إبلاغ تترك
     * صاحبَها يرى رابطاً كان يعمل فلا يعمل، فيظنّ العطلَ عندنا ويفتح
     * بلاغاً عن عطلٍ ليس عطلاً.
     *
     * **ولا يُعرض من الشكوى إلّا نصُّ الحسم.** فالمعترض غالباً ليس زبوناً —
     * شيخٌ أو قارئ — وبريدُه وتفصيلُ شكواه ليسا للجهة المعترَض عليها.
     * ونصُّ الحسم يكتبه المشرف **وهو يعلم أنّ صاحب الجهة يقرؤه**.
     *
     * @return array{reason: string, at: string}|null
     */
    private function takedown(SummaryJob $job): ?array
    {
        if ($job->unpublished_at === null) {
            return null;
        }

        $complaint = Complaint::query()
            ->where('summary_job_id', $job->id)
            ->where('status', ComplaintStatus::Unpublished->value)
            ->orderByDesc('resolved_at')
            ->first();

        return [
            // **وتُزال الصفحة أحياناً بغير اعتراض** — بطلب الجهة نفسها أو
            // بمراجعةٍ عندنا. فيُقال «أُزيلت» ولا يُخترع لها سبب.
            'reason' => (string) ($complaint?->resolution ?? ''),
            'at' => $job->unpublished_at->toIso8601String(),
        ];
    }

    /**
     * سبب التوقّف بالعربية، وإجراءٌ يليه.
     *
     * ورمز الخطأ يُترجَم من `lang/ar/errors.php`، **وما لم يُعرف رمزه تُعطى
     * له رسالةٌ عامّة مفهومة** — ولا يُعرض الرمز نفسه على المستخدم بحال.
     */
    private function error(SummaryJob $job): ?string
    {
        if ($job->state !== JobState::Failed) {
            return null;
        }

        /*
         * ★ **`errors.pipeline` تُفحص أيضاً، لا `errors.transcript` وحدها**
         * — T-91. فرمزٌ كـ`rate_limited` أو `pipeline_failed` لا صلة له
         * بالتفريغ، وسقوطه على `failed_fallback` وحدها كان يُخرج رسالة
         * «غيّرْ مصدر الدرس» على عطلٍ لا علاقة له بالمصدر.
         */
        foreach (['errors.transcript', 'errors.pipeline'] as $namespace) {
            $key = "{$namespace}.{$job->error_code}";

            if (trans()->has($key)) {
                return trans($key);
            }
        }

        return trans('jobs.follow.failed_fallback');
    }

    /**
     * أيقترح تبديلُ مصدر الدرس فعلاً؟ — T-91.
     *
     * **صادقٌ فقط لرموز `errors.transcript`.** وما سواها — تعطّل مزوّدٍ أو
     * عطلٌ داخليّ — تبديل المصدر فيه فعلٌ بلا أثر، فلا يُعرض زرّه.
     */
    private function errorIsAboutTranscriptSource(SummaryJob $job): bool
    {
        return $job->state === JobState::Failed
            && trans()->has("errors.transcript.{$job->error_code}");
    }
}
