<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Actions\Publish\GenerateShareCard;
use App\Contracts\Renderer;
use App\Enums\OutputType;
use App\Enums\VenueMode;
use App\Models\Output;
use App\Support\I18n\PageStrings;
use App\Support\Publish\Beacon;
use App\Support\Publish\PublishedLocales;
use App\Support\Publish\ShareCard;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\Ornaments;
use App\Support\Render\RenderedEvidence;
use App\Support\Render\RenderedOutput;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * الصفحة الكاملة — المواصفة §8 و§8-أ. **المخرَج الافتراضي لكل الشرائح.**
 *
 * **حدٌّ لا يُتجاوز:** لا يُعدَّل حرفٌ من CSS القالب ولا من JavaScriptه —
 * CLAUDE.md §2 القاعدة الأولى. القالب مضبوطٌ للعربية على الجوال وللطباعة،
 * وكلّ تفصيلٍ فيه حُلّت به مشكلةٌ فعلية: أرقام الآيات داخل رمز نهايتها،
 * وبطاقات المقارنة التي لا تنكسر على الجوال، وتنسيقُ طباعةٍ يحفظ الألوان.
 *
 * **والمخرَج ملفّ واحد قائم بذاته** (§8): CSS داخلي، والشعار Base64، ولا
 * اعتماد خارجي إلّا خطوط Google.
 */
class PageRenderer implements Renderer
{
    /**
     * تُرفع عند كل تغيير يُبدّل شكل المخرَج، وتُحفظ في `outputs`.
     * فصفحةٌ رُسمت بنسخةٍ قديمة تُعرف بلا مقارنةِ ملفّات.
     */
    private const VERSION = '1.3.0'; // T-99: المواضع آخراً، و«خُلاصات» بضمّتها، والقديم بلا شعار.

    public function __construct(
        private readonly ViewFactory $views,
        private readonly BodyPurifier $purifier,
    ) {}

    public function type(): OutputType
    {
        return OutputType::Page;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function render(ContentObject $content, BrandKit $brand): RenderedOutput
    {
        $majlis = $content->majlis;

        /** @var VenueMode $venueMode */
        $venueMode = $majlis['venue_mode'] ?? VenueMode::default();

        // صفحةُ القالب — T-49. والثلاثةُ الأولى تُعيد `summary.layout` نفسه.
        $html = $this->views->make($brand->template->layoutView(), [
            'brand' => $brand,
            'palette' => $brand->palette,

            // لغة الصفحة واتّجاهها — T-38. سمتان على `<html>` لا حرفَ CSS.
            'locale' => $content->locale->value,
            'direction' => $content->locale->direction(),

            // كلماتُ الإطار بلسان الصفحة — T-69. **ثوابتُ واجهةٍ لا محتوًى**،
            // فلا نداءَ نموذجٍ لها ولا كلفة.
            'strings' => PageStrings::all($content->locale),
            'pageLocale' => $content->locale,
            'venueMode' => $venueMode,

            // القالب ورقةُ أنماطٍ لا بنية — T-45. والبنية واحدة للجميع، فلا
            // تسقط كتلةٌ من كتل المتن في قالبٍ لم يُنسّقها.
            'styleView' => $brand->template->styleView(),

            // ورقةُ مفردات الكتل — T-46، وعلّةُ انفصالها في `blocksView()`.
            'blocksView' => $brand->template->blocksView(),

            /*
             * وصفُ الصفحة ووسومُ المشاركة — المرحلة ٦، T-57.
             *
             * **ولا احتياطَ من `closing_line`.** جُرّب فأسقط حارسَ T-47:
             * تلك سقالةٌ للمرحلة ٥ **لا تصل القالب**، وإخراجُها في وسمٍ
             * يُعيدها من الباب الذي أُغلق. فالمرحلةُ إن سقطت غابت الوسوم.
             */
            'metaTitle' => trim((string) ($content->outputMeta['meta_title'] ?? '')) ?: null,
            'metaDescription' => trim((string) ($content->outputMeta['meta_description'] ?? '')) ?: null,

            // بطاقةُ المشاركة واسمُ المنصّة ورابطُ الصفحة — T-144.
            'shareImage' => ShareCard::url(
                $content->summaryJobId,
                $content->locale,
                GenerateShareCard::fingerprint($content, $brand),
            ),
            'siteName' => PageStrings::of('platform_name', $content->locale),
            'pageUrl' => $this->pageUrl($content),

            'title' => (string) ($majlis['title'] ?? ''),
            'subtitle' => $majlis['subtitle'] ?? null,
            'sheikh' => $majlis['sheikh'] ?? null,
            'sheikhFull' => $majlis['sheikh_full'] ?? null,
            'weekday' => $majlis['weekday'] ?? '',
            'dateGregorian' => $majlis['date_gregorian'] ?? null,
            'dateHijri' => $majlis['date_hijri'] ?? null,
            'timeNote' => $majlis['time_note'] ?? '',

            'heroAyah' => $this->heroAyah($content),
            'evidence' => $content->evidence,
            'sourcesNote' => $this->sourcesNote($content),
            'translationCredit' => $this->translationCredit($content),

            /*
             * **هنا وحده يُنقّى المتن**، قبل أن يبلغ `{!! !!}` في القالب.
             *
             * ثمّ تُملأ مواضعُ الزخرفة — T-54، **وبعد التنقية لا قبلها**:
             * قائمةُ المنقّي مغلقة و`<svg>` ليس فيها، فيُحذف الرسمُ وما فيه
             * بلا خطأ ولا سجلّ. والرسمُ ثابتٌ في الكود لا يأتي من نموذجٍ
             * ولا من مستخدم، فحقنُه بعد الحارس لا يُضعفه.
             */
            'bodyHtml' => Ornaments::apply($this->purifier->purify($content->bodyHtml)),

            /*
             * التنويه — **وافتراضُه بلسان الصفحة** (T-69). وما كتبته الجهةُ
             * بنفسها يبقى كما كتبته: هو نصُّها لا نصُّنا، ولا يُترجَم عنها.
             */
            'disclaimer' => $brand->disclaimer ?? PageStrings::of('disclaimer', $content->locale),

            /*
             * رابط الاعتراض مطلقٌ لا نسبيّ: الصفحة تُخدَم من نطاق الجهة على
             * CDN، فرابطٌ نسبيّ يُشير إلى نطاقها هي لا إلى لوحتنا.
             */
            'complaintUrl' => rtrim((string) config('app.url'), '/').'/complaint',

            // رابطُ المنصّة في سطر الاعتماد — T-98.
            'platformUrl' => (string) config('khulasah.platform_url'),

            /*
             * شاهدة العدّ — T-31. وتغيب في المعاينة وفي الملفّ المنزَّل.
             *
             * **واللسانُ فيها منذ T-140**: بلا هذا تطلب صفحاتُ اللغات
             * الثلاث شاهدةً واحدة، فيُجمع الثلاثةُ في صفٍّ ولا يُعرف أيُّ
             * لسانٍ قُرئ.
             */
            'beacon' => Beacon::for($content->summaryJobId, OutputType::Page, $content->locale),

            /*
             * لغاتُ هذا الملخّص المنشورة — T-134. **وتغيب كالشاهدة** في
             * المعاينة والملفّ المنزَّل: كلتاهما تُفرَّغ `summaryJobId` فيها.
             */
            'localeLinks' => PublishedLocales::for($content->summaryJobId, $content->locale),

            // رابط المحاضرة نفسها لا قناة الجهة العامّة — زرّ «مشاهدة المحاضرة
            // كاملة» يفتح ما استُخرج منه الملخّص تحديداً.
            'lectureUrl' => $majlis['source_url'] ?? null,
            'lectureUrlShort' => BrandKit::shorten($majlis['source_url'] ?? null),
            'socialShort' => BrandKit::shorten($brand->socialUrl),
        ])->render();

        return new RenderedOutput(
            type: $this->type(),
            format: $this->type()->format(),
            contents: $html,
            rendererVersion: self::VERSION,
            meta: [
                'palette' => $brand->palette->key,
                'venue_mode' => $venueMode->value,
                'evidence_count' => count($content->evidence),
            ],
        );
    }

    /**
     * The page's own public URL, when an earlier publish has recorded it — T-144.
     */
    private function pageUrl(ContentObject $content): ?string
    {
        if ($content->summaryJobId === null) {
            return null;
        }

        return Output::query()
            ->where('summary_job_id', $content->summaryJobId)
            ->where('type', OutputType::Page->value)
            ->where('locale', $content->locale->value)
            ->value('public_url');
    }

    /**
     * الآية المفتاح في الترويسة — من مخرَج المرحلة الثانية.
     *
     * وتُطابَق على الشواهد المحسومة **فتُعرض بلفظ مصدرها**: النموذج ينقلها
     * في `structure_json` كما سمعها، والمحقّق ردّها إلى رسم المصحف.
     */
    private function heroAyah(ContentObject $content): ?RenderedEvidence
    {
        $key = $content->structure['key_ayah'] ?? null;

        if (! is_array($key) || ($key['text'] ?? '') === '') {
            return null;
        }

        foreach ($content->evidence as $item) {
            if ($item->kind === 'ayah') {
                return $item;
            }
        }

        // شاهدٌ لم يبلغ التحقّق لا يُعرض بلفظ النموذج — فالغياب أسلم.
        return null;
    }

    /**
     * حاشية قائمة التخريج.
     *
     * وتُذكر **متى وُجد ضعيف**: سياسة البيان تنشره مبيَّناً، والحاشية تقول
     * للقارئ أنّ الدرجة مذكورة عمداً لا سهواً.
     */
    /**
     * نسبةُ ترجمات الآيات إلى مترجمها — **مرّةً في ذيل الصفحة**، T-89.
     *
     * ★ **والنسبةُ لا تسقط، تنتقل**: ترجمةٌ منشورةٌ لها أصحابها «فلا تُنشر
     * مجهولة» (T-38). وتغيب حين لا ترجمةَ آيةٍ في الصفحة — نسبةُ ما لم يُعرض
     * سطرٌ يسأل عنه القارئ ولا يجده.
     */
    private function translationCredit(ContentObject $content): ?string
    {
        $translator = $content->locale->quranTranslationName();

        if ($translator === null || ! str_contains((string) $content->bodyHtml, 'ayah-tr')) {
            return null;
        }

        return str_replace(':name', $translator, PageStrings::of('ayah_translation_credit', $content->locale)).'.';
    }

    private function sourcesNote(ContentObject $content): ?string
    {
        foreach ($content->evidence as $item) {
            if (in_array($item->grade, ['daif', 'mawdu'], true)) {
                return 'ما كان من الأحاديث دون الصحيح والحسن فقد بُيّنت درجته إلى جانبه.';
            }
        }

        return null;
    }
}
