<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Render\RenderOutput;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Services\Render\PageRenderer;
use App\Support\Publish\LocaleAdditions;
use App\Support\Render\BrandKit;
use App\Support\Render\CarouselDesign;
use App\Support\Render\ContentObject;
use App\Support\Render\ImageSet;
use App\Support\Render\SlideDeck;
use App\Support\Render\TenantCarouselDesigns;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * معاينة المخرجات قبل النشر وبعده — SCREENS.md §6، والمهمّة T-30.
 *
 * **وكانت الشاشة غائبةً كلَّها**: الخطّ ينشر آلياً في آخره، فلا موضع يجمع
 * المخرجات ولا زرَّ نشرٍ ولا إلغاءَ نشرٍ في الواجهة. ومن أراد إزالة صفحته
 * لم يجد إلّا أن يراسلنا.
 *
 * ★ **وكلّ ما هنا قراءةٌ لا تكتب صفّاً ولا ترفع ملفّاً.** فالمعاينة تُفتح
 * بـ`GET`، وتُعاد بكلّ تحديثِ صفحة وبكلّ رجوعٍ من التاريخ. ولو كتبت لصار
 * فتحُ الشاشة نشراً لم يطلبه أحد — وهو ما وقع فعلاً في معاينة الكاروسيل
 * (T-19) وأُصلح هناك بالعلّة نفسها.
 *
 * ★ **وبلغات النشر لا بالعربية وحدها** — T-84. كانت المعاينة ترسم العربية
 * ولو كانت المهمّة تنشر الإنجليزية والتركية، فيدفع صاحب الجهة ثمن ترجمةٍ
 * لا يراها قبل نشرها.
 */
class PreviewController extends Controller
{
    public function show(Request $request, SummaryJob $job): InertiaResponse
    {
        return Inertia::render('Jobs/Preview', $this->payload($request, $job));
    }

    /**
     * الصفحة بالقالب الحقيقي، تُعرض في إطار — كما في شاشة الهوية.
     *
     * **ولا تمرّ بـ`RenderOutput`**: ذاك يقيّد صفّاً في `outputs` ويكتب
     * `rendered_at`. والمعاينة ترسم في الذاكرة وتُرمى.
     */
    public function page(Request $request, SummaryJob $job, PageRenderer $renderer): Response
    {
        abort_unless($this->renderable($job), 404);

        return response($this->pageHtml($job, $renderer, $this->requestedLocale($request, $job)))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // تُعرض في إطارٍ داخل اللوحة، ولا تُفتح من موقعٍ آخر.
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * «نزّل» — SCREENS.md §6.
     *
     * **والملفّ قائمٌ بذاته** (§8): CSS داخليّ والشعار Base64، فمن نزّله
     * فتحه من قرصه بلا شبكة ورفعه إلى موقعه إن شاء. وهذا هو مخرجُ من لا
     * يريد استضافتنا أصلاً.
     *
     * **ولكلّ لغةٍ ملفُّها** — T-84: اللغةُ الأولى باسم الملخّص كما كانت،
     * وما بعدها بلاحقتها (`-en`)، فلا يكتب تنزيلٌ فوق آخر في مجلّدٍ واحد.
     */
    public function download(Request $request, SummaryJob $job, string $type, PageRenderer $renderer): StreamedResponse
    {
        $name = $job->slug ?? 'summary-'.$job->id;
        $locale = $this->requestedLocale($request, $job);
        $suffix = $locale === $job->primaryLocale() ? '' : '-'.$locale->value;

        [$contents, $mime, $filename] = match ($type) {
            OutputType::Page->value => [$this->pageHtml($job, $renderer, $locale), 'text/html; charset=UTF-8', "{$name}{$suffix}.html"],
            OutputType::Carousel->value => [$this->carouselText($job), 'text/plain; charset=UTF-8', "{$name}.txt"],
            OutputType::ImageSet->value => [$this->imageZip($job), 'application/zip', "{$name}-images.zip"],
            default => abort(404),
        };

        abort_if($contents === null, 404);

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            $filename,
            ['Content-Type' => $mime],
        );
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, SummaryJob $job): array
    {
        $job->loadMissing(['lecture', 'outputs', 'tenant']);

        $tenant = $job->tenant;
        $carousel = $this->output($job, OutputType::Carousel);
        $meta = (array) ($carousel?->meta ?? []);
        $pending = $job->pendingEvidenceCount();
        $page = $job->primaryPage();

        return [
            // تبويبُ الاختبار — T-195، بجوار الصفحة والشرائح.
            'quiz' => app(JobQuizController::class)->props($request, $job),
            'job' => [
                'id' => $job->id,
                'title' => $job->lecture?->title_ar,
                'speaker' => $job->lecture?->speaker_name,
                'state' => $job->state->value,
                'pending_evidence' => $pending,
                'renderable' => $this->renderable($job),
                'published' => $job->state === JobState::Published && $job->unpublished_at === null,
                'unpublished' => $job->unpublished_at !== null,
                'public_url' => $page?->public_url,
                // ★ اللغاتُ التالية والشرائحُ ما زالت تُبنى بعد النشر — T-228،
                // فتتحدّث الشاشةُ من نفسها حتى تنتهي.
                'finishing' => $job->isFinishing(),
            ],
            'outputs' => [
                'page' => [
                    'produced' => $job->body_html !== null,
                    'public_url' => $page?->public_url,
                ],
                'carousel' => [
                    'produced' => $carousel !== null,
                    // طُلبت عند الإنشاء ويبنيها الخطُّ الآن — «تُبنى» لا «لم تُنشأ» — T-228.
                    'pending' => $carousel === null
                        && $job->isFinishing()
                        && $job->lecture?->want_carousel === true
                        && ($tenant?->allowsRichOutputs() ?? false),
                    'slides' => $meta['slides'] ?? [],
                    // من الشرائح لا من المحفوظ — انظر `carouselText()`.
                    'plain_text' => SlideDeck::fromArray($meta['slides'] ?? [])->toPlainText(),
                ],
                // حزمة الصور — T-173. تُنزَّل ولا تُنشر، فلا رابطَ عامّاً لها.
                'images' => $this->images($job),
            ],
            /*
             * ★ **الفرق الذي يجب أن يظهر بوضوح** — SCREENS.md §6.
             *
             * «إضافة مخرَجٍ جديد لا تُحتسب من الحصة، لأنّها رسمٌ من محتوى
             * موجود. وأعد التوليد وحده يُحتسب، **ويبيّن المتبقّي قبل
             * التنفيذ**». فالعددان يُرسلان من الخادم: عدٌّ في المتصفّح
             * يقرأ ما في الصفحة لا ما في السجلّ.
             */
            'regenerations' => [
                'used' => (int) $job->regeneration_count,
                'limit' => (int) ($tenant?->regenerations_per_summary ?? 0),
            ],
            'can_publish' => $request->user()?->role->canPublish() ?? false,
            'rich_outputs' => $tenant?->allowsRichOutputs() ?? false,

            // لغات النشر للمبدّل — T-84.
            'primary_locale' => $job->primaryLocale()->value,
            'locales' => $this->localeOptions($job),

            // «أضف لغة» — T-166. ما يُضاف وما يُترجَم الآن وما سقط.
            'locale_additions' => LocaleAdditions::for($job),
        ];
    }

    /**
     * لغاتُ النشر كما يعرضها المبدّل — T-84.
     *
     * ★ **ولغةٌ لم تُترجَم بعدُ تُعلَّم لا تُخفى.** فالعارضُ يسقط فيها إلى
     * العربية ({@see ContentObject::fromJob()})، ومبدّلٌ يُري «الإنجليزية»
     * صفحةً عربيةً بلا تنبيهٍ يكذب كما كذبت المعاينةُ العربيةُ وحدها.
     *
     * @return list<array{key: string, label: string, native: string, direction: string, translated: bool, translating: bool, public_url: string|null}>
     */
    private function localeOptions(SummaryJob $job): array
    {
        $rows = $job->translations()->get()->keyBy(
            static fn (SummaryTranslation $row): string => $row->locale->value,
        );

        $pages = $job->outputs()->where('type', OutputType::Page->value)->get();

        return array_map(
            static fn (Locale $locale): array => [
                'key' => $locale->value,
                'label' => $locale->label(),
                'native' => $locale->nativeName(),
                'direction' => $locale->direction(),
                'translated' => $locale->isSource() || $rows->get($locale->value)?->isReady() === true,
                // ★ «جارٍ» لا «لم تُترجَم» لما طُلبت ترجمتُه الآن — T-166،
                // أو يترجمه الخطُّ بعد نشر الأولى — T-228.
                'translating' => ! $locale->isSource() && LocaleAdditions::isTranslatingFor($job, $rows->get($locale->value)),
                // رابطُ هذه اللغة إن نُشرت — فالنسخُ والمشاركة للّغة المعروضة.
                'public_url' => $pages->firstWhere('locale', $locale)?->public_url,
            ],
            $job->outputLocales(),
        );
    }

    /**
     * أيصلح هذا الملخّص للرسم أصلاً؟
     *
     * **والشاهد المعلَّق يمنع الرسم لا النشر وحده** — CLAUDE.md §2 القاعدة
     * الرابعة، و{@see RenderOutput}. فملفٌّ مرسوم من
     * شواهد غير محسومة جاهزٌ للنشر بضغطة، وهو أخطر من ألّا يُرسم.
     */
    private function renderable(SummaryJob $job): bool
    {
        return $job->body_html !== null && $job->pendingEvidenceCount() === 0;
    }

    private function pageHtml(SummaryJob $job, PageRenderer $renderer, Locale $locale): ?string
    {
        if (! $this->renderable($job) || $job->tenant === null) {
            return null;
        }

        /*
         * **بلا شاهدة عدّ** — T-31. فهذه معاينةُ صاحب الصفحة، وتنزيلُه
         * ملفّاً يُفتح من قرصه. وعدُّهما زيارةً يجعل العدّاد مرآةً له لا
         * لقرّائه.
         */
        return $renderer
            ->render(ContentObject::fromJob($job, $locale)->withoutBeacon(), BrandKit::forTenant($job->tenant, $job->lecture))
            ->contents;
    }

    /**
     * حالُ حزمة الصور كما تراها الشاشة — T-173.
     *
     * `state`: لا شيء، أو `rendering`، أو `ready`، أو `failed` بسببه. وروابطُ
     * الصور تحمل وقتَ إنشائها، فلا يعرض المتصفّحُ صورةً قديمةً من ذاكرته.
     *
     * @return array<string, mixed>
     */
    private function images(SummaryJob $job): array
    {
        $output = $this->output($job, OutputType::ImageSet);
        $meta = (array) ($output?->meta ?? []);
        $state = $meta['state'] ?? null;

        // «جاريةٌ» أطولَ من حدّها متعثّرة — T-197: طابورٌ بلا عاملٍ يتركها جاريةً
        // أبداً. والتقدّمُ يجدّد الصفّ بعد كلّ دفعة، فالعاملةُ لا تبلغ الحدّ.
        $at = isset($meta['at']) ? Carbon::parse((string) $meta['at']) : null;

        if ($state === 'rendering' && ($at === null || $at->lt(now()->subMinutes(ImageSet::STALL_MINUTES)))) {
            $state = 'failed';
            $meta['error'] = trans('jobs.images.stalled');
        }

        // **آخرُ صورٍ صالحة تبقى معروضة** أثناء الإنشاء وبعد تعثّره — T-197:
        // ملفّاتُها لا تُمسّ حتى تكتمل الجديدة. و`count` من آخر إنشاءٍ تمّ.
        $count = (int) ($meta['count'] ?? 0);
        $version = $output?->rendered_at?->timestamp ?? 0;

        return [
            'produced' => $count > 0,
            'public_url' => null,
            'state' => $state,
            'error' => $state === 'failed' ? ($meta['error'] ?? null) : null,
            // ما التُقط من كم — يُحدَّث بعد كلّ دفعة، فيُعرض عدداً لا دوّاراً.
            'progress' => $state === 'rendering' ? ($meta['progress'] ?? null) : null,
            'enabled' => ImageSet::enabled(),
            // قوالبُ الجهة المعتمدة يُختار منها عند الإنشاء، وأوّلُها افتراضيُّها — T-173.
            'designs' => array_map(
                static fn (CarouselDesign $design): array => ['id' => $design->id, 'name' => $design->name],
                $job->tenant === null ? [] : TenantCarouselDesigns::approved($job->tenant),
            ),
            // **قالبُ شرائح الملخّص** لا قالبُ آخر حزمة — T-204: به تُنشأ الصور
            // ما لم يُختر غيره.
            'design' => CarouselDesign::forJob($job)->id,
            /*
             * **صورٌ أقدمُ من شرائحها** — T-204: صياغةٌ جديدة أو قالبٌ آخر بعد
             * إنشائها، أو نُشر الملخّصُ بعدها فخلت شريحتُها الأخيرة من رابطه.
             * فلا تُعرض على أنّها الحالية، وإعادةُ إنشائها مجّانية.
             */
            'stale' => $count > 0 && $output?->rendered_at !== null && (
                ($this->output($job, OutputType::Carousel)?->rendered_at?->gt($output->rendered_at) ?? false)
                // وحزمةٌ قبل تقييد الرابط لا يُعرف رابطُها، فلا تُعدّ قديمةً به.
                // **والرابطُ من المصدر الذي كتبه** ({@see SummaryJob::primaryPage()})
                // — T-216: رابطٌ بلغةٍ أخرى كان يُخفي صوراً أُنشئت للتوّ.
                || (array_key_exists('page_url', $meta)
                    && $meta['page_url'] !== $job->primaryPage()?->public_url)
            ),
            'urls' => array_map(
                static fn (int $slide): string => route('jobs.images.show', ['job' => $job->id, 'slide' => $slide], false)."?v={$version}",
                $count > 0 ? range(1, $count) : [],
            ),
        ];
    }

    /** الحزمةُ كما حُفظت، إن كانت. */
    private function imageZip(SummaryJob $job): ?string
    {
        $disk = ImageSet::disk();
        $path = ImageSet::zipPath($job);

        return $disk->exists($path) ? (string) $disk->get($path) : null;
    }

    /**
     * نصّ الشرائح للنسخ والتنزيل — يُبنى من الشرائح المحفوظة لا من
     * `plain_text` المحفوظ معها (T-172): ما حُفظ قبل إسقاط «۝» من النصّ
     * المنسوخ يحملها، والشرائحُ هي الأصل.
     */
    private function carouselText(SummaryJob $job): ?string
    {
        $meta = (array) ($this->output($job, OutputType::Carousel)?->meta ?? []);
        $text = SlideDeck::fromArray($meta['slides'] ?? [])->toPlainText();

        return $text === '' ? null : $text;
    }

    /**
     * مخرَجُ هذا النوع **باللغة الأولى** — T-64.
     *
     * **وكان يأخذ أوّلَ صفٍّ بلا نظرٍ إلى اللغة**، فمهمّةٌ بلغتين قد تُظهر
     * رابطَ الثانية مكان الأولى. واللغةُ الأولى هي التي تُنشر على الجذر،
     * وهي التي يُشارَك رابطُها.
     *
     * ويسقط إلى أوّل صفٍّ إن غاب صفُّ الأولى، فرابطٌ قائم خيرٌ من فراغ.
     *
     * **والصفحةُ لا تُقرأ من هنا** — T-216: لها {@see SummaryJob::primaryPage()}،
     * يقرؤها الكاتبُ والمعاينةُ معاً.
     */
    private function output(SummaryJob $job, OutputType $type): ?Output
    {
        $rows = $job->outputs()->where('type', $type->value)->get();

        return $rows->firstWhere('locale', $job->primaryLocale()) ?? $rows->first();
    }

    /**
     * اللغةُ المطلوبة في `?locale=` — T-84.
     *
     * **ولا تُقبل إلّا لغةٌ من لغات النشر.** ومعاينةٌ بلغةٍ لم تُختر تُري
     * ما لن يُنشر أبداً — والمعاينة وعدٌ بما يُنشر. وبلا طلبٍ فالأولى:
     * **لا العربية دائماً** — ملخّصٌ إنجليزيٌّ وحده يُعاين إنجليزياً (T-51).
     */
    private function requestedLocale(Request $request, SummaryJob $job): Locale
    {
        $allowed = $job->outputLocales();

        if (! $request->filled('locale')) {
            return Locale::primaryOf($allowed);
        }

        $locale = Locale::tryFrom($request->string('locale')->toString());

        abort_if($locale === null || ! in_array($locale, $allowed, true), 404);

        return $locale;
    }
}
