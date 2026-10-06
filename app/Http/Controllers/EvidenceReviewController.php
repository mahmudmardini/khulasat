<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Review\DecideEvidence;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\ReviewDecision;
use App\Jobs\RunSummaryPipeline;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Arabic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * بوّابة المراجعة — SCREENS.md الشاشة 5، **أهمّ شاشة في المنتج**.
 *
 * ما يُباع هنا ليس التلخيص، بل أن يوقّع مدير المحتوى على صفحةٍ يعلم أنّ
 * شواهدها مرّت على تحقّق. ولذلك يُرسَل إلى الشاشة **كلُّ ما يلزم للقرار**:
 * اللفظان، والتخريج، والدرجة، والسياق الذي ورد فيه الشاهد — «من احتاج فتح
 * تبويب آخر ليقرّر، فالشاشة فشلت».
 */
class EvidenceReviewController extends Controller
{
    public function show(SummaryJob $job): Response
    {
        return Inertia::render('Jobs/Review', $this->payload($job));
    }

    public function decide(Request $request, SummaryJob $job, EvidenceItem $item, DecideEvidence $decide): RedirectResponse
    {
        abort_if((int) $item->summary_job_id !== (int) $job->id, 404);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(ReviewDecision::class)],
        ]);

        $user = $request->user();

        abort_if($user === null, 403);

        try {
            $decide->handle($item, ReviewDecision::from($validated['decision']), $user);
        } catch (RuntimeException $refused) {
            return back()->withErrors(['decision' => $refused->getMessage()]);
        }

        return back();
    }

    /**
     * يستأنف الخطّ بعد حسم الشواهد كلّها.
     *
     * **يفتح الباب بيده ثمّ يُرسل المشغّل** — وما بعد الباب للمشغّل وحده،
     * يقرأ الحالة ويقرّر — T-11ب. و{@see TransitionJob} يفحص الشواهد مرّةً
     * أخرى قبل أن يفتح الباب، فالحدّ الرابع محروسٌ في موضعين لا في الواجهة.
     *
     * ★ **ولماذا لا يترك الانتقالَ للمشغّل؟** كان يتركه، فتعود صفحة المتابعة
     * والمهمّة ما تزال في `needs_review` — المشغّل لم يلتقطها بعد — فتُرسَم
     * «بانتظار مراجعتك» ولا تستطلع، ولا يظهر ما بعد المراجعة إلّا بتحديث
     * الصفحة. والانتقالُ هنا يجعلها تعود على «الكتابة» وتتابع وحدها، ويُنهي
     * وقتَ المراجعة ساعةَ الضغط لا ساعةَ يلتقطها المشغّل.
     *
     * والقفلُ لضغطتين متتاليتين: الثانية تجد الباب مفتوحاً فلا تُرسل مشغّلاً
     * ثانياً يكتب الملخّص مرّتين.
     */
    public function resume(Request $request, SummaryJob $job, TransitionJob $transition): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null || ! $user->role->canPublish(), 403);

        if ($job->pendingEvidenceCount() > 0) {
            return back()->withErrors(['resume' => trans('review.gate.blocked')]);
        }

        $opened = DB::transaction(function () use ($job, $transition): bool {
            $locked = $job->newQueryWithoutScopes()->whereKey($job->getKey())->lockForUpdate()->first();

            if (! $locked instanceof SummaryJob || $locked->state !== JobState::NeedsReview) {
                return false;
            }

            $transition->handle($locked, JobState::Writing);

            return true;
        });

        if ($opened) {
            RunSummaryPipeline::dispatch((int) $job->id);
        }

        return to_route('jobs.show', $job);
    }

    /** @return array<string, mixed> */
    private function payload(SummaryJob $job): array
    {
        $items = $job->evidenceItems()->orderBy('id')->get();

        return [
            'job' => [
                'id' => (int) $job->id,
                'title' => $job->lecture?->title_ar ?? '',
                'needs_review' => $job->state === JobState::NeedsReview,
            ],
            'items' => $items->map(fn (EvidenceItem $item): array => $this->row($job, $item))->all(),
            'pending' => $items->filter(fn (EvidenceItem $i): bool => ! $i->isSettled())->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function row(SummaryJob $job, EvidenceItem $item): array
    {
        return [
            'id' => (int) $item->id,
            'kind' => $item->kind,
            'quoted' => (string) $item->raw_text,
            'source' => $item->matched_text,
            'match_status' => $item->match_status?->value,
            'review_status' => $item->review_status->value,
            'settled' => $item->isSettled(),
            'source_ref' => $item->source_ref,
            'narrator' => $item->meta('narrator'),
            'book' => $item->meta('book'),
            'takhrij' => $item->meta('takhrij'),
            'grade' => $item->meta('grade'),
            'grade_label' => $item->meta('grade_label'),
            'surah' => $item->meta('surah_name_ar'),
            'ayah_number' => $item->meta('ayah_number'),
            'context' => $this->context($job, (string) $item->raw_text),
        ];
    }

    /**
     * الفقرة التي ورد فيها الشاهد — SCREENS.md §5.
     *
     * تُطلب لأنّ لفظ الشاهد وحده لا يكفي للحكم: المراجع يحتاج أن يرى **في
     * أيّ سياقٍ ساقه المتكلّم**. والبحث على النصّ المطبَّع، فالتفريغ يخالف
     * لفظ الشاهد تشكيلاً وهمزاً.
     */
    private function context(SummaryJob $job, string $quoted): ?string
    {
        $transcript = (string) $job->transcript_text;

        if ($transcript === '' || $quoted === '') {
            return null;
        }

        $needle = Arabic::normalize($quoted);

        foreach (preg_split('/\n{2,}/u', $transcript) ?: [] as $paragraph) {
            if ($needle !== '' && str_contains(Arabic::normalize($paragraph), $needle)) {
                return trim($paragraph);
            }
        }

        return null;
    }
}
