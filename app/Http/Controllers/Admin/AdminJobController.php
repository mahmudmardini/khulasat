<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Actions\Summary\ResumeFailedJob;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Jobs\RunSummaryPipeline;
use App\Models\ModelCall;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Support\Analytics\ViewsByLocale;
use App\Support\Ui\JobProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * المهامّ عبر الجهات كلِّها — SCREENS.md §ب من لوحة المشرف، والمهمّة T-21.
 *
 * **والمشرف بلا سياق جهة**، فحاجزُ `BelongsToTenant` لا يحصره — وهو المقصود:
 * لوحةٌ ترى مهامّ جهةٍ واحدة ليست لوحةَ مشرف.
 */
class AdminJobController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function index(Request $request): Response
    {
        // `views` أو `id` — وما سواهما يسقط إلى الافتراضي، فلا يُفرز بعمودٍ
        // يأتي من الرابط: مدخلُ مستخدمٍ في `order by` حقنٌ لا تصفية.
        $sort = $request->string('sort')->toString() === 'views' ? 'views' : 'id';

        $jobs = SummaryJob::query()
            ->with(['lecture:id,title_ar,speaker_name', 'tenant:id,name_ar'])
            /*
             * المشاهدات — T-136. **بضمٍّ فرعيّ واحد لا استعلامٍ لكلّ صفّ**:
             * `withSum` تبني جمعاً في `select`، فيبقى عددُ الاستعلامات ثابتاً
             * مهما طال الجدول. ويحرسُ ذلك عدُّ استعلاماتٍ في الاختبار.
             */
            ->withSum('pageViews as views_total', 'views')
            ->when($request->string('state')->toString(), fn ($query, string $state) => $query
                ->where('state', $state))
            ->when($request->integer('tenant'), fn ($query, int $tenant) => $query
                ->where('tenant_id', $tenant))
            // **و`nulls last`**: مهمّةٌ لم تُفتح صفحتُها `null` لا صفراً في
            // الجمع، وبلا هذا تتصدّر الأكثرَ قراءةً في الفرز التنازلي.
            ->when($sort === 'views', fn ($query) => $query->orderByRaw('views_total desc nulls last'))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Jobs/Index', [
            'jobs' => [
                'data' => array_map($this->row(...), $jobs->items()),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ],
            'filters' => [
                'state' => $request->string('state')->toString() ?: null,
                'tenant' => $request->integer('tenant') ?: null,
                'sort' => $sort,
            ],
            'states' => array_map(static fn (JobState $s): string => $s->value, JobState::cases()),
            'tenants' => Tenant::query()->orderBy('name_ar')->get(['id', 'name_ar'])->all(),
        ]);
    }

    public function show(SummaryJob $job): Response
    {
        $job->loadMissing(['lecture', 'tenant']);

        /*
         * **و`row()` مشترَكةٌ بين الفهرس وهذه** — T-136. فالفهرسُ يجمع
         * بـ`withSum` والرابطُ هنا يأتي بالصفّ وحده، فبلا هذا السطر يقرأ
         * `views_total` غيرَ موجودةٍ فيعرض صفراً لصفحةٍ تُقرأ.
         */
        $job->loadSum('pageViews as views_total', 'views');

        return Inertia::render('Admin/Jobs/Show', [
            'job' => [
                ...$this->row($job),
                'error_code' => $job->error_code,
                'error_detail' => $job->error_detail,
                'attempt' => (int) $job->attempt,
                'steps' => JobProgress::steps($job),
                'settled' => $job->state->isTerminal(),
                // **الكلفة معروضة هنا وحدها**: هذه لوحة المشغّل، وSCREENS.md
                // تمنع كلمة «توكن» في شاشة العميل لا في شاشته.
                'total_cost_usd' => (float) $job->total_cost_usd,
                'cost_breakdown' => $job->cost_breakdown,
            ],
            /*
             * توزيعُ القراءات على الألسنة — T-140. **وهنا لا في الفهرس**:
             * الفهرسُ جدولٌ بصفٍّ لكلّ مهمّة، وثلاثةُ أرقامٍ في خليّةٍ
             * واحدةٍ تُقرأ زحاماً. والمجموعُ في عموده، والتوزيعُ لمن فتح.
             */
            'views_by_locale' => ViewsByLocale::for((int) $job->id),
            // كلفةُ كلّ مرحلة مرسومةً — T-103. وكانت تُمرَّر خاصّيةً ولا تُقرأ.
            'stage_costs' => $this->stageCosts($job),
            'transitions' => $job->transitions()->orderBy('id')->get()
                ->map(static fn ($t): array => [
                    'id' => $t->id,
                    'from_state' => $t->from_state?->value,
                    'to_state' => $t->to_state->value,
                    'attempt' => (int) $t->attempt,
                    'cost_usd' => (float) $t->cost_usd,
                    'error_code' => $t->error_code,
                    'occurred_at' => $t->occurred_at?->toDateTimeString(),
                ])->all(),
        ]);
    }

    /**
     * «إعادة تشغيل المخفق **من مرحلته لا من أوّلها**» — SCREENS.md §ب.
     *
     * و`failed` حالةٌ نهائية لا انتقال منها (§5)، فلا تُحيا المهمّة نفسُها.
     * وإنّما تُنشأ خَلَفٌ **تبدأ من المرحلة التي أخفقت** — تحمل معها ما
     * أُنتج قبلها: التفريغ والبنية والشواهد. فإعادةُ تفريغ درسٍ أخفق في
     * الكتابة إهدارُ مالٍ ووقت، وهو عين ما يحذّر منه بند المهمّة.
     *
     * وتبقى المهمّة المخفقة كما هي: ما وقع قد وقع، والسجلّ يبقى صادقاً.
     *
     * ★ **والمنطق في {@see ResumeFailedJob}** — T-93، يستعمله هذا المسار
     * ومسار المستخدم معاً، فلا يتباعدان.
     */
    public function retry(SummaryJob $job, ResumeFailedJob $resume): RedirectResponse
    {
        if ($job->state !== JobState::Failed) {
            return back()->withErrors(['retry' => trans('admin.errors.not_failed')]);
        }

        $replacement = $resume->handle($job);

        RunSummaryPipeline::dispatch((int) $replacement->id);

        $this->audit->handle(
            AuditAction::JobRetried,
            $job->tenant,
            [
                'job' => ['from' => $job->id, 'to' => $replacement->id],
                'resumed_at' => ['from' => $job->error_code, 'to' => $replacement->state->value],
            ],
        );

        return to_route('admin.jobs.show', $replacement);
    }

    /** إلغاء العالق — و`cancelled` مسموحة من كلّ حالةٍ غير نهائية (§5). */
    public function cancel(SummaryJob $job, TransitionJob $transition): RedirectResponse
    {
        if ($job->state->isTerminal()) {
            return back()->withErrors(['cancel' => trans('admin.errors.already_settled')]);
        }

        $from = $job->state;

        $transition->handle($job, JobState::Cancelled);

        $this->audit->handle(
            AuditAction::JobCancelled,
            $job->tenant,
            ['state' => ['from' => $from->value, 'to' => JobState::Cancelled->value]],
        );

        return back();
    }

    /**
     * كلفةُ كلّ مرحلة، ومعها نموذجُها إن سُجّل — T-103.
     *
     * **والأساسُ `cost_breakdown`** لأنّه يحمل كلّ ملخّصٍ منذ أوّل يوم،
     * ويُضاف إليه من `model_calls` ما لا يحمله: المزوّد والنموذج والتوكنز.
     * فمهمّةٌ قديمة تُعرض بكلفة مراحلها بلا نموذج، وحديثةٌ تُعرض بهما —
     * ولا تُخفى القديمة لأنّ تفصيلها ناقص.
     *
     * @return list<array<string, mixed>>
     */
    private function stageCosts(SummaryJob $job): array
    {
        $calls = ModelCall::query()
            ->where('summary_job_id', $job->id)
            ->orderBy('id')
            ->get()
            ->keyBy(static fn (ModelCall $call): string => $call->stage->costKey());

        $rows = [];

        foreach ((array) $job->cost_breakdown as $stage => $cost) {
            $call = $calls->get((string) $stage);

            $rows[] = [
                'stage' => (string) $stage,
                'cost_usd' => round((float) $cost, 4),
                'provider' => $call?->provider,
                'model_id' => $call?->model_id,
                'input_tokens' => $call?->input_tokens,
                'output_tokens' => $call?->output_tokens,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['cost_usd'] <=> $a['cost_usd']);

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(SummaryJob $job): array
    {
        return [
            'id' => $job->id,
            'title' => $job->lecture?->title_ar,
            'speaker' => $job->lecture?->speaker_name,
            'tenant' => $job->tenant?->name_ar,
            'tenant_id' => $job->tenant_id,
            'state' => $job->state->value,
            'status' => JobProgress::badgeFor($job),
            'cost_usd' => (float) $job->total_cost_usd,

            /*
             * المشاهدات — T-136. **و`null` تعني «لا تُقاس» لا «صفر»**: مهمّةٌ
             * لم تُنشر بعد لا صفحةَ لها تُفتح، وصفرٌ عندها يُقرأ «نُشرت ولم
             * يقرأها أحد» — وهو حكمٌ على عملٍ لم يُعرض بعد.
             *
             * ويُقرأ `views_total` من `withSum`، وهو `null` حين لا صفَّ له.
             */
            'views' => $job->state === JobState::Published
                ? (int) ($job->views_total ?? 0)
                : null,
            'started_at' => $job->started_at?->toDateTimeString(),
            'finished_at' => $job->finished_at?->toDateTimeString(),
        ];
    }
}
