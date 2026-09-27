<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Enums\VenueMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Tenant;
use App\Services\Brand\LogoSanitizer;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\Palette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

/**
 * هوية الجهة — SCREENS.md الشاشة 8.
 *
 * **والمعاينة بالقالب الحقيقي لا بمحاكاة.** فمحاكاةٌ تُرضي في الشاشة ثمّ
 * تخالف المنشور تُفقد الثقة في المعاينة كلِّها، ويصير المستخدم ينشر ليرى.
 */
class BrandController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        $tenant = $this->tenant($request);

        $this->authorize('updateBrand', $tenant);

        $kit = (array) ($tenant->brand_kit ?? []);

        return Inertia::render('Settings/Brand', [
            'tenant' => [
                'name_ar' => $tenant->name_ar,
                'name_ar_full' => $tenant->name_ar_full,
                'name_latin' => $tenant->name_latin,
                'disclaimer_text' => $tenant->disclaimer_text,
                'palette' => $kit['palette'] ?? Palette::DEFAULT,
                'template' => SummaryTemplate::parse($kit['template'] ?? null)->value,
                'youtube_url' => $kit['youtube_url'] ?? null,
                'social_url' => $kit['social_url'] ?? null,
                'has_logo' => isset($kit['logo_data_uri']),
                // الشعار المحفوظ نفسه — T-85. منقّى قبل الحفظ، ويُرى في الشاشة
                // قبل أن يُستبدل؛ وكانت منطقة الرفع لا تقول أفيه شعارٌ أم لا.
                'logo_url' => $kit['logo_data_uri'] ?? null,
                // اللوح الفاتح خلف الشعار — T-90. اختياريّ منذ T-125.
                'logo_transparent' => (bool) ($kit['logo_transparent'] ?? false),
                // لغاتُ المخرَج — T-38. والعربيةُ مضمومةٌ دائماً، فتصل
                // الواجهةَ محسومةً لا تُحسب فيها.
                'locales' => Locale::normalizeSet($kit['locales'] ?? null),
            ],
            'locales' => array_values(array_map(
                static fn (Locale $locale): array => [
                    'key' => $locale->value,
                    'name' => $locale->label(),
                    'native' => $locale->nativeName(),
                    'direction' => $locale->direction(),
                    // العربيةُ لغةُ المصدر: تُعرض ولا تُنزع، والواجهةُ تُقفلها.
                    'is_source' => $locale->isSource(),
                    'quran_translation' => $locale->quranTranslationName(),
                ],
                Locale::all(),
            )),
            'templates' => array_values(array_map(
                static fn (SummaryTemplate $template): array => [
                    'key' => $template->value,
                    'name' => $template->label(),
                    'description' => $template->description(),
                ],
                SummaryTemplate::all(),
            )),
            'palettes' => array_values(array_map(
                static fn (Palette $palette): array => [
                    'key' => $palette->key,
                    'name' => $palette->nameAr,
                    // ثلاثة ألوان تكفي لتمييز اللوحة بصرياً في البطاقة.
                    'swatches' => [
                        $palette->vars['emerald'],
                        $palette->vars['gold'],
                        $palette->vars['paper-2'],
                    ],
                ],
                Palette::all(),
            )),
        ]);
    }

    public function update(UpdateBrandRequest $request): RedirectResponse
    {
        $tenant = $this->tenant($request);

        $this->authorize('updateBrand', $tenant);

        $kit = (array) ($tenant->brand_kit ?? []);

        $kit['palette'] = $request->string('palette')->toString();
        // ما لم يُعرف من القوالب يسقط إلى الافتراضي، فلا يُخزَّن مفتاحٌ
        // لا ورقةَ أنماطٍ له — T-45.
        $kit['template'] = SummaryTemplate::parse($request->input('template'))->value;

        // **تُطبَّع قبل الحفظ**: العربيةُ تُضمّ ولو لم تُرسل، والمكرّر يُسقَط،
        // والترتيبُ ثابت — فلا يتبدّل المخرَج بترتيب ما وصل من المتصفّح.
        $kit['locales'] = Locale::normalizeSet($request->input('locales'));
        $kit['youtube_url'] = $request->input('youtube_url');
        $kit['social_url'] = $request->input('social_url');
        $kit['logo_transparent'] = $request->boolean('logo_transparent');

        if ($request->hasFile('logo')) {
            try {
                $logo = app(LogoSanitizer::class)->sanitize($request->file('logo'));
            } catch (RuntimeException $e) {
                return back()->withErrors(['logo' => $e->getMessage()]);
            }

            // **يُخزَّن منقّى**: الملفّ الملوّث في التخزين يخرج يوماً من
            // طريقٍ لم يُنقَّ.
            $kit['logo_data_uri'] = app(LogoSanitizer::class)->toDataUri($logo);
        } elseif ($request->boolean('remove_logo')) {
            // الشعار اختياريّ — T-99. و`null` يُسقطه `array_filter` أدناه.
            $kit['logo_data_uri'] = null;
        }

        $tenant->forceFill([
            'name_ar' => $request->string('name_ar')->toString(),
            'name_ar_full' => $request->input('name_ar_full'),
            'name_latin' => $request->input('name_latin'),
            'disclaimer_text' => $request->input('disclaimer_text'),
            'brand_kit' => array_filter($kit, static fn (mixed $v): bool => $v !== null),
        ])->save();

        return back()->with('message', 'حُفظت هوية الجهة.');
    }

    /**
     * المعاينة الحيّة — **بالقالب الحقيقي من T-14**.
     *
     * وتُبنى على محتوى عيّنة ثابت لا على ملخّصٍ حقيقي: الجهة قد لا تملك
     * ملخّصاً بعد، والمعاينة يجب أن تعمل من أوّل دقيقة.
     */
    public function preview(Request $request): Response
    {
        $tenant = $this->tenant($request);

        $this->authorize('updateBrand', $tenant);

        // اللوحة والقالب يُجرَّبان قبل الحفظ، فيرى الأثر قبل أن يلتزم به.
        $kit = (array) ($tenant->brand_kit ?? []);
        $kit['palette'] = $request->string('palette', $kit['palette'] ?? Palette::DEFAULT)->toString();
        $kit['template'] = SummaryTemplate::parse($request->input('template', $kit['template'] ?? null))->value;

        $preview = clone $tenant;
        $preview->brand_kit = $kit;

        return $this->previewResponse($preview);
    }

    /**
     * المعاينة الحيّة **لما لم يُحفظ** — T-85.
     *
     * كانت المعاينة تتبع اللوحة والقالب وحدهما (بـ`GET`)، والاسمُ والتنويه
     * والشعار لا تظهر إلّا بعد الحفظ — فوعدُ الشاشة 8 «أثرُ كلّ تغيير
     * فوراً» نصفُه منفَّذ. و`POST` لأنّ التنويه نصٌّ قد يطول عن رابط.
     *
     * ★ **والشعارُ المختار ولم يُحفظ يُرسم أيضاً** — T-90 وT-98. كان لا
     * يُقبل هنا لأنّ قالب الصفحة لم يرسم شعاراً؛ فلمّا رسمه صار من اختار
     * شعاراً لا يراه حتى يحفظ. **وينقّيه المنقّي نفسه قبل رسمه**: فلا يبلغ
     * الإطارَ ما لا يبلغ الصفحة، ولا يُفتح بابٌ ثانٍ لرفع SVG بلا تنقية.
     *
     * **ولا يكتب شيئاً**: نسخةٌ من الجهة في الذاكرة تُرسم وتُرمى.
     */
    public function draft(Request $request): Response
    {
        $tenant = $this->tenant($request);

        $this->authorize('updateBrand', $tenant);

        // حدود الحفظ نفسها ({@see UpdateBrandRequest})، إلّا أنّ الفارغ هنا جائز:
        // من مسح الاسم ليكتب غيره يرى الاسم المحفوظ حتى يكتب.
        $input = $request->validate([
            'name_ar' => ['nullable', 'string', 'max:120'],
            'name_ar_full' => ['nullable', 'string', 'max:200'],
            'name_latin' => ['nullable', 'string', 'max:120'],
            'disclaimer_text' => ['nullable', 'string', 'max:400'],
            'palette' => ['nullable', 'string', Rule::in(array_keys(Palette::all()))],
            'template' => ['nullable', 'string'],
            /*
             * **والحدُّ هنا كما في الحفظ** — T-124. كان بلا `max`، فالمعاينة
             * تستقبل ملفّاً بحجم `upload_max_filesize` (٥١٢م عندنا) قبل أن
             * يقول المنقّي «كبير». والرفضُ بعد الاستقبال رفضٌ متأخّر.
             *
             * **والنوعُ يبقى بالمحتوى في `LogoSanitizer` لا بقاعدة هنا**:
             * الامتدادَ يكتبه الرافع، وfinfo يقرأ SVG الصغير `text/plain`
             * فتردّ `mimetypes` شعاراً صحيحاً. والمنقّي يقرأ البايتات.
             */
            'logo' => ['nullable', 'file', 'max:500'],
            'remove_logo' => ['nullable', 'boolean'],
            'logo_transparent' => ['nullable', 'boolean'],
        ]);

        $kit = (array) ($tenant->brand_kit ?? []);
        $kit['palette'] = $input['palette'] ?? $kit['palette'] ?? Palette::DEFAULT;
        $kit['template'] = SummaryTemplate::parse($input['template'] ?? $kit['template'] ?? null)->value;
        // يُرسم قبل الحفظ كما تُرسم بقيّة الحقول — T-125.
        $kit['logo_transparent'] = $request->boolean('logo_transparent', (bool) ($kit['logo_transparent'] ?? false));

        if ($request->hasFile('logo')) {
            $sanitizer = app(LogoSanitizer::class);

            try {
                $kit['logo_data_uri'] = $sanitizer->toDataUri($sanitizer->sanitize($request->file('logo')));
            } catch (RuntimeException) {
                // ما رُفض يبقى المحفوظُ مكانه في المعاينة، والحفظُ يقول سببَ رفضه —
                // فلا تتوقّف المعاينة كلُّها لشعارٍ لا يُقبل.
            }
        } elseif ($request->boolean('remove_logo')) {
            // الإزالةُ تُرى قبل الحفظ كما يُرى الشعارُ الجديد — T-99.
            unset($kit['logo_data_uri']);
        }

        $preview = clone $tenant;
        $preview->brand_kit = $kit;
        $preview->name_ar = ($input['name_ar'] ?? '') !== '' ? $input['name_ar'] : $tenant->name_ar;

        foreach (['name_ar_full', 'name_latin', 'disclaimer_text'] as $field) {
            if ($request->exists($field)) {
                $preview->{$field} = ($input[$field] ?? '') !== '' ? $input[$field] : null;
            }
        }

        return $this->previewResponse($preview);
    }

    /** الجهةُ كما ستُرى، بالقالب الحقيقي على محتوى العيّنة. */
    private function previewResponse(Tenant $preview): Response
    {
        $html = app(PageRenderer::class)
            ->render($this->sampleContent(), BrandKit::forTenant($preview))
            ->contents;

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // المعاينة تُعرض في إطار داخل اللوحة، ولا تُفتح من موقع آخر.
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * محتوى العيّنة الذي تُرسم عليه المعاينة — وعامٌّ منذ T-95: شاشةُ الإنشاء
     * تعاين «المظهر» عليه نفسه، فلا تفترق عيّنتان يُقارَن بينهما.
     *
     * @param  VenueMode|null  $venueMode  نمطُ النسبة المختار — T-126. ومعاينةُ
     *                                     هوية الجهة لا تُرسله فتبقى على
     *                                     الافتراض (`institution`)، فتُري
     *                                     اللوحة كاملةً كعادتها.
     */
    public static function sampleContent(?VenueMode $venueMode = null): ContentObject
    {
        return new ContentObject(
            structure: ['title_ar' => 'نموذج المعاينة'],
            evidence: [],
            majlis: [
                'title' => 'نموذج المعاينة',
                'subtitle' => 'هكذا يظهر ملخّصكم',
                'sheikh' => 'اسم الملقي',
                'sheikh_full' => 'اسم الملقي',
                'weekday' => 'الجمعة',
                'date_gregorian' => now()->format('Y/m/d'),
                'venue_mode' => $venueMode ?? VenueMode::default(),
            ],
            /*
             * **العيّنة تعرض الكتل لا الفقرات وحدها** — T-45. فالقوالب
             * تختلف في الشاهد والمقارنة والخطوة أكثر ممّا تختلف في فقرةٍ
             * عادية، ومعاينةٌ بفقرتين تُخفي عن المُختار ما يختار لأجله.
             */
            bodyHtml: '<section><div class="sec-head"><span class="mark"></span>'
                .'<h2>عنوان محور</h2><span class="rule"></span></div>'
                .'<p class="lead">فقرةٌ أولى، وهي أكبر خطًّا من غيرها.</p>'
                .'<p>وفقرةٌ عادية بعدها، تُظهر لون النصّ ومقاسه وتباعد سطوره.</p>'
                .'<div class="sacred"><p class="text">وهكذا يظهر الشاهد في هذا القالب.</p>'
                .'<span class="src">موضع التخريج</span></div>'
                .'<div class="compare"><article class="axis-card">'
                .'<h4 class="axis-q">وهكذا تظهر المقارنة</h4><div class="pair">'
                .'<div class="side calm"><span class="tag">الأوّل</span><p>وجهٌ أوّل.</p></div>'
                .'<div class="side panic"><span class="tag">الثاني</span><p>وجهٌ ثانٍ.</p></div>'
                .'</div></article></div></section>'
                // الخاتمة تلي الأقسام كما يبنيها BodyBlocks حقيقةً، لا حقلاً
                // منفصلاً في majlis — وإلّا ظهرت خاتمتان (البقّ المُصلَح هنا).
                .'<p class="closing">وهذه جملةٌ ختامية كما تظهر في آخر الملخّص.</p>',
        );
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, 403);

        return $tenant;
    }
}
