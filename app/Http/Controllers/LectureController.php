<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Usage\RecordUsage;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Enums\SummaryTemplate;
use App\Enums\UsageEvent;
use App\Enums\VenueMode;
use App\Http\Requests\StoreLectureRequest;
use App\Jobs\RunSummaryPipeline;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Support\I18n\PageStrings;
use App\Support\Render\Palette;
use App\Support\Transcript\SourceKey;
use App\Support\Transcript\UploadStore;
use App\Support\Ui\JobProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * The tenant's index and creation screens — SCREENS.md §2 و§3، والمهمّة T-16.
 */
class LectureController extends Controller
{
    /** «التاريخ» في الفهرس: تاريخ الدرس، وتاريخُ إنشائه حين لا يُذكر. */
    private const DATE_EXPRESSION = 'coalesce(lectures.gregorian_date, lectures.created_at::date)';

    public function __construct(
        private readonly RecordUsage $recordUsage,
    ) {}

    /**
     * الفهرس — SCREENS.md §2.
     *
     * **والترتيب `needs_review` أوّلاً ثم الأحدث.** وهي الحالة الوحيدة التي
     * تطلب فعلاً من المستخدم، فوضعُها في مكانها الزمني يدفنها تحت ما لا
     * يحتاج منه شيئاً.
     */
    public function index(Request $request): Response
    {
        /*
         * الفرز — T-86. **قائمةٌ بيضاء**: ما وصل من الرابط لا يبلغ `orderBy`
         * بحال، وما لم يُعرف يسقط إلى الذكيّ — وهو افتراضُ SCREENS.md §2.
         */
        $sort = $request->string('sort')->toString();

        if (! in_array($sort, ['smart', 'newest', 'oldest', 'title'], true)) {
            $sort = 'smart';
        }

        $query = $this->filtered($request);

        match ($sort) {
            'newest' => $query->orderByDesc('lectures.created_at'),
            'oldest' => $query->orderBy('lectures.created_at'),
            'title' => $query->orderBy('lectures.title_ar'),
            default => $query
                ->orderByRaw('case when summary_jobs.state = ? then 0 else 1 end', [JobState::NeedsReview->value])
                ->orderByDesc('lectures.created_at'),
        };

        $jobs = $query
            // فاصلٌ حاسم عند تساوي الطوابع: درسان أُنشئا في الثانية نفسها
            // يتبدّل ترتيبهما بين صفحةٍ وأخرى، فيظهر أحدهما مرّتين ويغيب
            // الآخر. والمعرّف يتزايد فيقطع التساوي — وباتّجاه الفرز نفسه.
            ->orderBy('summary_jobs.id', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Lectures/Index', [
            'jobs' => [
                'data' => array_map($this->row(...), $jobs->items()),
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'total' => $jobs->total(),
            ],
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'status' => $request->string('status')->toString() ?: null,
                'month' => $request->string('month')->toString() ?: null,
                'speaker' => $request->string('speaker')->toString() ?: null,
                // المطبَّق فعلاً لا المطلوب: فرزٌ غريب يصل «smart» — T-86.
                'sort' => $sort,
            ],
            'speakers' => $this->speakers(),
            'months' => $this->months(),
            // فارغٌ لأنّ التصفية لم تُطابق شيئاً، أم فارغٌ لأنّ الجهة جديدة؟
            // ورسالةٌ واحدة للحالين تُضلّل إحداهما — SCREENS §القواعد العامّة.
            'has_any' => SummaryJob::query()->exists(),
            /*
             * ما ينتظر قرار المستخدم — T-27.
             *
             * **ويُعدّ في الخادم لا في المتصفّح**: الصفحة الظاهرة تعرف ما
             * فيها وحدها، فعدٌّ منها يقول «شاهدان» والحقيقة عشرون في صفحاتٍ
             * بعدها. ولا يخضع للتصفية عمداً: النداءُ عن حال الجهة كلّها،
             * لا عن حال ما رشّحه المستخدم.
             */
            'needs_review_count' => SummaryJob::query()
                ->where('state', JobState::NeedsReview->value)
                ->count(),
        ]);
    }

    /** شاشة الإنشاء — SCREENS.md §3. */
    public function create(Request $request): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('Lectures/Create', [
            'limits' => [
                'max_lecture_minutes' => (int) $tenant->max_lecture_minutes,
                'upload_max_bytes' => (int) config('khulasah.transcript.upload.max_bytes'),
                'text_extensions' => config('khulasah.transcript.upload.text_extensions'),
                'media_extensions' => config('khulasah.transcript.upload.media_extensions'),
            ],
            // «تظهران معطَّلتين مع سطر ترقية، لا مخفيّتين» — §3-ب.
            'rich_outputs' => $tenant->allowsRichOutputs(),
            'venue_modes' => array_map(
                static fn (VenueMode $mode): string => $mode->value,
                VenueMode::cases(),
            ),

            /*
             * القالبُ واللغاتُ في «إعدادات متقدّمة» بشاشة الإنشاء — طلبُ
             * مالك المنتج، ٩ أيلول ٢٠٢٦. وكانا في إعدادات الجهة وحدها، فمن
             * أراد درساً بقالبٍ غير قالبه اضطُرّ إلى تبديل إعدادات الجهة
             * كلِّها ثمّ ردِّها.
             *
             * **والافتراضُ ما اختارته الجهة**، فمن لم يفتح القسم أصلاً خرج
             * ملخّصُه كما كان قبل هذه الشاشة.
             */
            'templates' => array_values(array_map(
                static fn (SummaryTemplate $template): array => [
                    'key' => $template->value,
                    'name' => $template->label(),
                    'description' => $template->description(),
                ],
                SummaryTemplate::all(),
            )),
            'locales' => array_values(array_map(
                static fn (Locale $locale): array => [
                    'key' => $locale->value,
                    'name' => $locale->label(),
                    'native' => $locale->nativeName(),
                    'is_source' => $locale->isSource(),
                    // ترجمةُ الآيات المعتمدة لكلّ لغة — T-95. تُبذر ولا يترجمها نموذج (T-38).
                    'quran_translation' => $locale->quranTranslationName(),
                ],
                Locale::all(),
            )),

            /*
             * سطرُ النسبة **بألفاظ الصفحة نفسها** — T-95. فالجملةُ الحيّة تحت
             * «نمط النسبة» تُبنى منها، وما يُرى هناك هو ما يُطبع هنا —
             * `summary/partials/attrib`.
             */
            'attribution' => [
                'lecture_by' => PageStrings::of('lecture_by', Locale::Ar),
                'lecture_at' => PageStrings::of('lecture_at', Locale::Ar),
                'venue' => (string) $tenant->name_ar,
            ],
            /*
             * لوحاتُ الألوان — T-60. كانت في إعدادات الجهة وحدها كالقالب
             * واللغات قبل T-50، والعلّةُ نفسُها: جهةٌ واحدة قد ترغب بلوحةٍ
             * مختلفة لمناسبةٍ أو نوع محتوًى دون تبديل هوية الجهة كلِّها.
             */
            'palettes' => array_values(array_map(
                static fn (Palette $palette): array => [
                    'key' => $palette->key,
                    'name' => $palette->nameAr,
                    'swatches' => [
                        $palette->vars['emerald'],
                        $palette->vars['gold'],
                        $palette->vars['paper-2'],
                    ],
                ],
                Palette::all(),
            )),

            'defaults' => [
                'template' => SummaryTemplate::parse(
                    ((array) ($tenant->brand_kit ?? []))['template'] ?? null,
                )->value,
                'locales' => array_map(
                    static fn (Locale $locale): string => $locale->value,
                    $tenant->outputLocales(),
                ),
                'palette' => Palette::find(((array) ($tenant->brand_kit ?? []))['palette'] ?? null)->key,
            ],
        ]);
    }

    /**
     * إنشاء الدرس ومهمّته — والحصّة تُفحص **قبل** الإنشاء لا بعده.
     *
     * فالمهمّة الموضوعة في الطابور تصرف توكنز، ورفضُها بعد وضعها يعني أنّ
     * المال صُرف — المواصفة §11 وCLAUDE.md §2 القاعدة الخامسة.
     */
    public function store(StoreLectureRequest $request): RedirectResponse
    {
        /*
         * **ولا فحصَ للحصّة هنا** — T-29. صار في `EnforceQuota` المعلَّق على
         * هذا المسار، كما تنصّ المواصفة §11 وCLAUDE.md §2 القاعدة الخامسة.
         *
         * وكان هنا ومكرَّراً: حارسٌ في المتحكّم يعمل لهذا المسار وحده،
         * **ويُنسى عند إضافة مسارٍ ثانٍ يضع في الطابور** — وهو ما وقع فعلاً
         * في «أعد المحاولة»، فمرّ الطلبُ على جهةٍ معلَّقة وحصّةٍ نفدت.
         */
        $tenant = $this->tenant($request);

        /*
         * الملفّ المرفوع — §5-أ-4-ب. **يُكتب قبل المعاملة لا فيها**: كتابةُ
         * نصف غيغابايت داخل معاملةٍ تُبقيها مفتوحةً طولَ الكتابة. وإن سقطت
         * المعاملة حُذف، فلا يبقى على القرص ملفٌّ بلا مهمّة.
         */
        $file = $request->string('source_kind')->toString() === 'upload' ? $request->file('source_file') : null;
        $file = $file instanceof UploadedFile ? $file : null;
        $upload = $file === null ? null : UploadStore::store($file, (int) $tenant->id);

        try {
            $job = $this->createLecture($request, $tenant, $upload, $file?->getClientOriginalName());
        } catch (Throwable $exception) {
            UploadStore::delete($upload);

            throw $exception;
        }

        // **وهنا يبدأ الخطّ فعلاً** — T-11ب. وقبلها كانت المهمّة تُنشأ
        // وتبقى `queued` أبداً، فتُرى في الشاشة ولا يجري لها شيء.
        RunSummaryPipeline::dispatch((int) $job->id);

        return to_route('jobs.show', $job)->with('message', trans('jobs.follow.title'));
    }

    private function createLecture(StoreLectureRequest $request, Tenant $tenant, ?string $upload, ?string $uploadName): SummaryJob
    {
        return DB::transaction(function () use ($request, $tenant, $upload, $uploadName): SummaryJob {
            $lecture = Lecture::query()->create([
                'tenant_id' => $tenant->id,
                'title_ar' => $request->string('title_ar')->toString(),
                'subtitle_ar' => $request->input('subtitle_ar'),
                'speaker_name' => $request->string('speaker_name')->toString(),
                'speaker_title' => $request->input('speaker_title'),
                // المصدرُ واحدٌ من ثلاثة، فرابطٌ بقي في الحقل من تبويبٍ آخر لا يُحفظ.
                'source_url' => $upload === null ? $request->input('source_url') : null,
                'source_platform' => match (true) {
                    $upload !== null => 'upload',
                    $request->input('source_url') !== null => 'youtube',
                    default => null,
                },
                // مفتاحُ المقارنة — T-65. مشتقٌّ لا مُدخَل، وعليه يقع كشفُ التكرار.
                'source_key' => $upload === null ? SourceKey::for($request->input('source_url')) : null,
                'hijri_date' => $request->input('hijri_date'),
                'gregorian_date' => $request->input('gregorian_date'),
                'weekday' => $request->input('weekday'),
                'time_note' => $request->input('time_note'),
                'venue_mode' => $request->string('venue_mode')->toString(),
                // مدّةُ الملفّ المرفوع من ffprobe، لا ما يقوله المتصفّح.
                'duration_seconds' => $request->uploadedDuration() ?? ($request->integer('duration_seconds') ?: null),
                /*
                 * **والشريحة تحرسه هنا لا في الواجهة وحدها** — SCREENS.md
                 * §3-ب. فالخانة معطَّلةٌ في الشاشة، والحقلُ يصل من طلبٍ
                 * مصنوع باليد كذلك.
                 */
                'want_carousel' => $tenant->allowsRichOutputs() && $request->boolean('want_carousel'),

                /*
                 * **و`null` تعني «كما في إعدادات الجهة»** لا قيمةً منسوخة:
                 * من بدّل افتراضَ الجهة تبدّل معه كلُّ ملخّصٍ لم يختر لنفسه.
                 * فما طابق الافتراضَ لا يُكتب — الافتراضُ افتراضٌ حيّ لا
                 * لحظةُ نسخ.
                 */
                'template' => $this->overrideTemplate($request, $tenant),
                'locales' => $this->overrideLocales($request, $tenant),
                'palette' => $this->overridePalette($request, $tenant),
                'created_by' => $request->user()?->id,
                'created_at' => now(),
            ]);

            $job = SummaryJob::query()->create([
                'lecture_id' => $lecture->id,
                'tenant_id' => $tenant->id,
                'state' => JobState::Queued->value,
                'transcript_text' => $upload === null ? $request->input('transcript_text') : null,
                'upload_path' => $upload,
                'upload_name' => $uploadName,
            ]);

            /*
             * ★ **وهنا تُستهلك وحدةُ الحصّة** — المواصفة §4 و§11، وT-23.
             *
             * و`usage_ledger` «مصدر الحقيقة للحصص»، و`QuotaGuard` يجمع منه
             * `units`. وكان لا يُكتب فيه إلّا صفوفُ كلفةِ استدعاء النموذج
             * بـ`units: 0` ({@see \App\Services\Model\ModelCallRecorder}) —
             * **فكان المجموع صفراً أبداً، والحصّة الشهرية لا تنفد مهما
             * أُنشئ**. وشاشةُ الاستهلاك تقول «استعملت ٠» بعد عشرة ملخّصات.
             *
             * وموضعُه هنا داخل المعاملة مع إنشاء المهمّة: صفٌّ يُكتب والمهمّة
             * لم تُنشأ يخصم حصّةً بلا ملخّص، والعكسُ يُعطي ملخّصاً بلا خصم.
             *
             * وإعادةُ التوليد تُقيَّد في موضعها ({@see RequestRegeneration})
             * ولا تمرّ من هنا، فلا يُحتسب حدثٌ مرّتين.
             */
            $this->recordUsage->handle($tenant, UsageEvent::Generate, units: 1, job: $job);

            return $job;
        });
    }

    /** @return Builder<SummaryJob> */
    private function filtered(Request $request): Builder
    {
        return SummaryJob::query()
            ->join('lectures', 'lectures.id', '=', 'summary_jobs.lecture_id')
            ->select('summary_jobs.*')
            // و`outputs` معه، فعمود المخرجات لا يستعلم صفّاً لكل سطر.
            ->with(['lecture', 'outputs'])
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('lectures.title_ar', 'ilike', $term)
                        ->orWhere('lectures.speaker_name', 'ilike', $term);
                });
            })
            ->when($request->filled('speaker'), fn (Builder $q) => $q->where('lectures.speaker_name', $request->string('speaker')->toString()))
            ->when($request->filled('month'), fn (Builder $q) => $q->whereRaw('to_char('.self::DATE_EXPRESSION.", 'YYYY-MM') = ?", [$request->string('month')->toString()]))
            ->when($request->filled('status'), fn (Builder $q) => $q->whereIn('summary_jobs.state', $this->statesFor($request->string('status')->toString())));
    }

    /**
     * حالات الشارة الواحدة — «قيد الإعداد» تجمع ثماني حالات.
     *
     * @return list<string>
     */
    private function statesFor(string $badge): array
    {
        return array_values(array_map(
            static fn (JobState $state): string => $state->value,
            array_filter(JobState::cases(), static fn (JobState $state): bool => JobProgress::badge($state) === $badge
                || ($badge === 'in_progress' && ! $state->isTerminal() && $state !== JobState::NeedsReview)),
        ));
    }

    /** @return array<string, mixed> */
    private function row(SummaryJob $job): array
    {
        $lecture = $job->lecture;

        return [
            'id' => $job->id,
            'title' => $lecture->title_ar,
            'speaker' => $lecture->speaker_name,
            'date' => ($lecture->gregorian_date ?? $lecture->created_at)?->toDateString(),
            'status' => JobProgress::badgeFor($job),
            'needs_review' => $job->state === JobState::NeedsReview,
            /*
             * ما أُنتج فعلاً من `outputs` — §2: «الرمادي يعني غير مُنتَج».
             *
             * وكان الكاروسيل مثبَّتاً على `false` ريثما يوجد عارضه (T-19).
             * **وقد وُجد**، فصار الرمادي كذباً على من بناه: يفتح الفهرس
             * فيرى شرائحه «غير منتَجة» وهي منشورة.
             */
            'outputs' => [
                'page' => $job->body_html !== null,
                'carousel' => $job->outputs->contains('type', OutputType::Carousel),
                'images' => $job->outputs->contains('type', OutputType::ImageSet),
            ],
        ];
    }

    /** @return list<string> */
    private function speakers(): array
    {
        return SummaryJob::query()
            ->join('lectures', 'lectures.id', '=', 'summary_jobs.lecture_id')
            ->distinct()
            ->orderBy('lectures.speaker_name')
            ->pluck('lectures.speaker_name')
            ->all();
    }

    /** @return list<string> */
    private function months(): array
    {
        return SummaryJob::query()
            ->join('lectures', 'lectures.id', '=', 'summary_jobs.lecture_id')
            ->selectRaw('distinct to_char('.self::DATE_EXPRESSION.", 'YYYY-MM') as month")
            ->orderByDesc('month')
            ->pluck('month')
            ->all();
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, 403);

        return $tenant;
    }

    /** اللوحةُ المختارة، أو `null` إن طابقت افتراضَ الجهة — T-60. */
    private function overridePalette(Request $request, Tenant $tenant): ?string
    {
        $chosen = $request->input('palette');

        if (! is_string($chosen) || $chosen === '') {
            return null;
        }

        $default = Palette::find(((array) ($tenant->brand_kit ?? []))['palette'] ?? null);

        $palette = Palette::find($chosen);

        return $palette->key === $default->key ? null : $palette->key;
    }

    /** القالبُ المختار، أو `null` إن طابق افتراضَ الجهة. */
    private function overrideTemplate(Request $request, Tenant $tenant): ?string
    {
        $chosen = $request->input('template');

        if (! is_string($chosen) || $chosen === '') {
            return null;
        }

        $default = SummaryTemplate::parse(((array) ($tenant->brand_kit ?? []))['template'] ?? null);

        $template = SummaryTemplate::parse($chosen);

        return $template === $default ? null : $template->value;
    }

    /**
     * اللغاتُ المختارة، أو `null` إن طابقت افتراضَ الجهة.
     *
     * @return list<string>|null
     */
    private function overrideLocales(Request $request, Tenant $tenant): ?array
    {
        $chosen = $request->input('locales');

        if (! is_array($chosen)) {
            return null;
        }

        $normalized = Locale::normalizeSet($chosen);

        $default = array_map(static fn (Locale $l): string => $l->value, $tenant->outputLocales());

        return $normalized === $default ? null : $normalized;
    }
}
