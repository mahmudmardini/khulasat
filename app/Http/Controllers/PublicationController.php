<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Publish\DeleteSummary;
use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\UnpublishSummary;
use App\Actions\Quiz\BuildQuizReport;
use App\Actions\Stages\RenderAndPublish;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\PageView;
use App\Models\QuizAttempt;
use App\Models\SummaryJob;
use App\Support\Analytics\ViewsByLocale;
use App\Support\Publish\LocaleAdditions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Throwable;

/**
 * الملخّص المنشور، ونشره وإزالته — SCREENS.md §7، والمهمّة T-30.
 *
 * ★ **وإلغاء النشر لم يكن له مدخلٌ من الواجهة قطّ.** الفعل قائمٌ منذ T-15،
 * ولا يناديه إلّا الشيفرة ولوحة المشرف (T-28). فجهةٌ نشرت ملخّصاً ثمّ أرادت
 * سحبه — لخطأ فيه، أو لطلب الملقي — لم تجد إلّا أن تراسلنا وتنتظر.
 *
 * **ولا يُحرَس هذا بحصّة**: النشر رفعُ ملفٍّ مرسوم، وإعادةُ الرسم مجّانية
 * (§8-أ). والمصروف صُرف يوم وُلّد المتن.
 */
class PublicationController extends Controller
{
    public function show(Request $request, SummaryJob $job): InertiaResponse
    {
        $job->loadMissing(['lecture', 'outputs']);

        return Inertia::render('Summaries/Show', [
            'job' => [
                'id' => $job->id,
                'title' => $job->lecture?->title_ar,
                'speaker' => $job->lecture?->speaker_name,
                'slug' => $job->slug,
                'state' => $job->state->value,
                'published' => $job->state === JobState::Published && $job->unpublished_at === null,
                'published_at' => $job->published_at?->toIso8601String(),
                'unpublished_at' => $job->unpublished_at?->toIso8601String(),
                'publishable' => $this->publishable($job),
            ],
            'outputs' => $this->outputRows($job),
            /*
             * عدّاد الفتحات — SCREENS.md §7 (T-31). **وعددٌ حقيقيّ لا مقدَّر**:
             * صفرُه يعني «لم يُفتح بعد»، وهو صادق. وكان قبل T-31 يُقال
             * «لا تُقاس بعد» لأنّ الصفحات تُخدَم من خارج اللوحة.
             */
            'views' => $this->views($job),
            'can_publish' => $request->user()?->role->canPublish() ?? false,

            // «أضف لغة» — T-166. ولا يُعرض لما لا يُنشر: ترجمةُ متنٍ معلّقٍ مالٌ على ما قد يتبدّل.
            'locale_additions' => $this->publishable($job) ? LocaleAdditions::for($job) : [],

            // اختبارُ الفهم — T-195: حالُه ومحاولاتُه، ومدخلُ إدارته.
            'quiz' => $this->quiz($job),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function quiz(SummaryJob $job): ?array
    {
        $quiz = $job->quiz()->first();

        if ($quiz === null) {
            return null;
        }

        // بطاقةُ التقرير — T-201: المحاولاتُ المنتهية ومتوسّطُ درجتها.
        $finished = app(BuildQuizReport::class)->finishedIds($quiz->id);
        $percents = QuizAttempt::query()->whereIn('id', $finished)->get(['score', 'total'])
            ->map(static fn (QuizAttempt $a): float => $a->total === 0 ? 0.0 : 100 * (int) $a->score / $a->total)
            ->all();
        $average = BuildQuizReport::mean($percents);

        return [
            'id' => $quiz->id,
            'state' => $quiz->state,
            'status' => $quiz->status,
            'attempts' => count($finished),
            'average' => $average === null ? null : (int) round($average),
        ];
    }

    /**
     * «انشر» و«تحديث» — وهما فعلٌ واحد: يرسم بالحال الراهنة ويرفع.
     *
     * **والرابط لا يتبدّل بينهما** — §9: `slug` يُشتقّ مرّةً ويثبت. فتحديثُ
     * ملخّصٍ بعد تصحيحِ هويةٍ أو بناءِ شرائح يكتب فوق الملفّ نفسه، ولا يكسر
     * إحالةً شاركها أحد.
     */
    public function store(Request $request, SummaryJob $job, RenderAndPublish $pipeline): RedirectResponse
    {
        $this->authorizePublishing($request);

        if (! $this->publishable($job)) {
            return back()->withErrors(['publish' => trans('jobs.published.not_publishable')]);
        }

        try {
            $pipeline->handle($job);
        } catch (Throwable $failure) {
            /*
             * **ولا يُعرض نصّ الاستثناء على مدير محتوى** — §القواعد العامّة:
             * «كل خطأ يذكر ما حدث ولماذا وما الإجراء. لا كود خطأ».
             */
            report($failure);

            return back()->withErrors(['publish' => trans('jobs.published.publish_failed')]);
        }

        return back()->with('message', trans('jobs.published.published_now'));
    }

    /**
     * «ألغِ النشر» — والرابط يردّ ٤١٠ بعدها لا ٤٠٤ (§9).
     *
     * والمتن والشواهد تبقى عندنا، فيُعاد النشر بضغطةٍ بلا كلفة.
     */
    public function destroy(Request $request, SummaryJob $job, UnpublishSummary $unpublish): RedirectResponse
    {
        $this->authorizePublishing($request);

        $unpublish->handle($job);

        return back()->with('message', trans('jobs.published.unpublished_now'));
    }

    /** «حذف نهائي» — يمحو المتن والشواهد، وتبقى الشاهدة مكان الصفحة. */
    public function purge(Request $request, SummaryJob $job, DeleteSummary $delete): RedirectResponse
    {
        $this->authorizePublishing($request);

        $delete->handle($job);

        return to_route('lectures.index')->with('message', trans('jobs.published.deleted_now'));
    }

    /**
     * أيبلغ هذا الملخّص حالَ النشر؟
     *
     * **والحارس الحقيقيّ في {@see PublishSummary}**،
     * وهذا يمنع الزرَّ من أن يُعرض على ما لا يُنشر أصلاً. ومنعُ الواجهة
     * وحدَه لا يكفي — والحارسان لا يتعارضان.
     */
    private function publishable(SummaryJob $job): bool
    {
        return $job->body_html !== null
            && $job->pendingEvidenceCount() === 0
            && ($job->state === JobState::Published || $job->state->canTransitionTo(JobState::Published));
    }

    /**
     * ما قُرئ من الملخّص — T-31.
     *
     * **ويُجمع في استعلامٍ واحد.** والمجموع الكلّي وآخرُ ثلاثين يوماً معاً:
     * الأوّل يقول كم بلغت، والثاني يقول أحيّةٌ هي اليوم — **ورقمٌ تراكميّ
     * وحده يُخفي صفحةً مات عنها القرّاء منذ شهور**.
     *
     * وكان معه عددُ ما قُرئ من الشرائح؛ **ولا شرائح تُنشر بعد T-204**، وما
     * عُدّ لها قبلُ يبقى في المجموع: زياراتٌ وقعت.
     *
     * @return array{total: int, recent: int, by_locale: list<array{locale: string|null, locale_label: string, total: int, recent: int}>}
     */
    private function views(SummaryJob $job): array
    {
        $row = PageView::query()
            ->where('summary_job_id', $job->id)
            ->selectRaw('coalesce(sum(views), 0) as total')
            ->selectRaw('coalesce(sum(case when day >= ? then views else 0 end), 0) as recent', [now()->subDays(30)->toDateString()])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'recent' => (int) ($row->recent ?? 0),

            /*
             * ★ **وتوزيعُها على الألسنة — T-140، بلاغُ مالك المنتج.**
             *
             * فصاحبُ المحتوى هو من اختار اللغات ودفع ثمنها، **وهو أوّلُ من
             * يحقّ له أن يعرف أنفعَها**. ورقمٌ جامعٌ يقول «تُقرأ» ولا يقول
             * بأيّ لسان، فلا يُبنى عليه قرارُ الملخّص القادم.
             */
            'by_locale' => ViewsByLocale::for((int) $job->id),
        ];
    }

    /**
     * صفٌّ لكلّ **نوعٍ ولغة** — T-64.
     *
     * **وكان صفّاً لكلّ نوعٍ وحده**، يُقرأ بـ`firstWhere('type', …)` بلا نظرٍ
     * إلى اللغة. فمهمّةٌ بلغتين تُظهر رابطاً واحداً — **وقد لا يكون رابطَ
     * اللغة الأولى أصلاً**، بل أوّلَ صفٍّ في القاعدة. فيدفع صاحبُ المحتوى
     * ثمن لغةٍ ولا يعرف أين هي، وهو بلاغُ مالك المنتج نصّاً.
     *
     * ★ **والنوعُ الذي لم يُنتَج يبقى صفّاً واحداً** بلغة الملخّص الأولى:
     * «المعطَّل المعلَّل يُقرأ وعداً معلوماً موعدُه، والغائبُ يُقرأ نقصاً».
     *
     * @return list<array{type: string, locale: string, locale_label: string, produced: bool, public_url: string|null, rendered_at: string|null}>
     */
    private function outputRows(SummaryJob $job): array
    {
        $rows = [];

        foreach (OutputType::cases() as $type) {
            // حزمة الصور **تُنزَّل ولا تُنشر** (§9)، فلا موضع لها هنا.
            if (! $type->isPublished()) {
                continue;
            }

            /** @var Collection<int, Output> $produced */
            $produced = $job->outputs
                ->where('type', $type->value)
                ->sortBy(fn (Output $o): int => array_search($o->locale, Locale::cases(), true) ?: 0);

            if ($produced->isEmpty()) {
                $rows[] = self::row($type, $this->primaryLocale($job), null);

                continue;
            }

            foreach ($produced as $output) {
                $rows[] = self::row($type, $output->locale, $output);
            }
        }

        return $rows;
    }

    /**
     * اللغة الأولى لهذا الملخّص — اختيارُ المحاضرة، وإلّا فافتراضُ الجهة.
     *
     * وهي التي تُنشر على الجذر ({@see RenderAndPublish}).
     */
    private function primaryLocale(SummaryJob $job): Locale
    {
        return Locale::primaryOf(
            $job->lecture?->outputLocales() ?? $job->tenant?->outputLocales() ?? [Locale::source()]
        );
    }

    /** @return array{type: string, locale: string, locale_label: string, produced: bool, public_url: string|null, rendered_at: string|null} */
    private static function row(OutputType $type, Locale $locale, ?Output $output): array
    {
        return [
            'type' => $type->value,
            'locale' => $locale->value,
            // بالعربية: اللوحةُ عربيةٌ كلُّها — CLAUDE.md §1.
            'locale_label' => $locale->label(),
            'produced' => $output !== null,
            'public_url' => $output?->public_url,
            'rendered_at' => $output?->rendered_at?->toIso8601String(),
        ];
    }

    /** النشر والإزالة من صلاحية `owner` و`editor` — المواصفة §10. */
    private function authorizePublishing(Request $request): void
    {
        abort_unless($request->user()?->role->canPublish() ?? false, 403);
    }
}
