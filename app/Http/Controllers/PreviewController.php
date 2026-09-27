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
use App\Support\Render\ContentObject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $suffix = $locale === $this->primaryLocale($job) ? '' : '-'.$locale->value;

        [$contents, $mime, $filename] = match ($type) {
            OutputType::Page->value => [$this->pageHtml($job, $renderer, $locale), 'text/html; charset=UTF-8', "{$name}{$suffix}.html"],
            OutputType::Carousel->value => [$this->carouselText($job), 'text/plain; charset=UTF-8', "{$name}.txt"],
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

        return [
            'job' => [
                'id' => $job->id,
                'title' => $job->lecture?->title_ar,
                'speaker' => $job->lecture?->speaker_name,
                'state' => $job->state->value,
                'pending_evidence' => $pending,
                'renderable' => $this->renderable($job),
                'published' => $job->state === JobState::Published && $job->unpublished_at === null,
                'unpublished' => $job->unpublished_at !== null,
                'public_url' => $this->output($job, OutputType::Page)?->public_url,
            ],
            'outputs' => [
                'page' => [
                    'produced' => $job->body_html !== null,
                    'public_url' => $this->output($job, OutputType::Page)?->public_url,
                ],
                'carousel' => [
                    'produced' => $carousel !== null,
                    'public_url' => $carousel?->public_url,
                    'slides' => $meta['slides'] ?? [],
                    'plain_text' => $meta['plain_text'] ?? '',
                ],
                /*
                 * حزمة الصور عارضٌ مؤجَّل (T-20) — §8-أ. **وتُعرض معطَّلةً
                 * لا مخفيّة**: التبويب الغائب يُقرأ نقصاً في المنتج، والمعطَّل
                 * المعلَّل يُقرأ وعداً معلوماً موعدُه.
                 */
                'images' => ['produced' => false, 'public_url' => null],
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
            'primary_locale' => $this->primaryLocale($job)->value,
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
                // ★ «جارٍ» لا «لم تُترجَم» لما طُلبت ترجمتُه الآن — T-166.
                'translating' => LocaleAdditions::isTranslating($rows->get($locale->value)),
                // رابطُ هذه اللغة إن نُشرت — فالنسخُ والمشاركة للّغة المعروضة.
                'public_url' => $pages->firstWhere('locale', $locale)?->public_url,
            ],
            $this->outputLocales($job),
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

    private function carouselText(SummaryJob $job): ?string
    {
        $meta = (array) ($this->output($job, OutputType::Carousel)?->meta ?? []);
        $text = (string) ($meta['plain_text'] ?? '');

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
     */
    private function output(SummaryJob $job, OutputType $type): ?Output
    {
        $rows = $job->outputs()->where('type', $type->value)->get();

        return $rows->firstWhere('locale', $this->primaryLocale($job)) ?? $rows->first();
    }

    /** @return list<Locale> لغاتُ النشر: اختيارُ الدرس، وإلّا افتراضُ الجهة، وإلّا المصدر. */
    private function outputLocales(SummaryJob $job): array
    {
        return $job->lecture?->outputLocales() ?? $job->tenant?->outputLocales() ?? [Locale::source()];
    }

    private function primaryLocale(SummaryJob $job): Locale
    {
        return Locale::primaryOf($this->outputLocales($job));
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
        $allowed = $this->outputLocales($job);

        if (! $request->filled('locale')) {
            return Locale::primaryOf($allowed);
        }

        $locale = Locale::tryFrom($request->string('locale')->toString());

        abort_if($locale === null || ! in_array($locale, $allowed, true), 404);

        return $locale;
    }
}
