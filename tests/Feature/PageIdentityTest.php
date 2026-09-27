<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Enums\VenueMode;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedEvidence;

/*
 * الصفحة المنشورة بالهوية البصرية الثانية — T-98، وشعارُ الجهة فيها T-90.
 *
 * **والحارسُ الأوّل ليس هنا**: «ينقل CSS القالب وJavaScriptه حرفاً بحرف»
 * في `PageRendererTest` يبقى أخضر بلا تعديل — فالهوية ورقةٌ فوق القالب.
 */

const IDENTITY_LOGO = 'data:image/png;base64,iVBORw0KGgo=';

function identityPage(
    SummaryTemplate $template = SummaryTemplate::Classic,
    ?string $logo = IDENTITY_LOGO,
    VenueMode $mode = VenueMode::Institution,
    Locale $locale = Locale::Ar,
): string {
    $kit = ['template' => $template->value];

    if ($logo !== null) {
        $kit['logo_data_uri'] = $logo;
    }

    $tenant = Tenant::factory()->create(['brand_kit' => $kit]);

    return app(PageRenderer::class)->render(new ContentObject(
        structure: ['title_ar' => 'درس'],
        evidence: [new RenderedEvidence(kind: 'ayah', text: 'شاهد', sourceRef: 'النحل: ٩٧')],
        majlis: ['title' => 'درس', 'sheikh' => 'الملقي', 'weekday' => 'الجمعة', 'venue_mode' => $mode],
        bodyHtml: '<section><h2>محور</h2><p>متن</p></section>',
        locale: $locale,
    ), BrandKit::forTenant($tenant))->contents;
}

/*
 * ─── شعار الجهة — T-90 ────────────────────────────────────────────────
 */

it('يرسم شعار الجهة في الرأس وبجانب اسمها في البصمة', function (SummaryTemplate $template): void {
    $html = identityPage($template);

    $mark = strpos($html, '<div class="brand-mark"><img src="'.IDENTITY_LOGO.'"');
    $colophon = strpos($html, '<footer class="colophon">');
    $beside = strpos($html, '<span class="venue-logo"><img src="'.IDENTITY_LOGO.'" alt="">');

    expect($mark)->not->toBeFalse()
        ->and($mark)->toBeLessThan(strpos($html, '<h1>'))
        ->and($beside)->not->toBeFalse()
        ->and($beside)->toBeGreaterThan($colophon)
        ->and($html)->toContain('<div class="venue has-logo">');
})->with(SummaryTemplate::cases());

// **والصفحةُ بلا شعارٍ لا وسمَ فيها للشعار ولا فراغَ مكانه.**
it('لا يترك للشعار وسماً في صفحةٍ بلا شعار', function (SummaryTemplate $template): void {
    $html = identityPage($template, logo: null);

    expect($html)->not->toContain('class="brand-mark"')
        ->not->toContain('class="venue-logo"')
        ->not->toContain('venue has-logo');
})->with(SummaryTemplate::cases());

// الشعارُ نسبةٌ بالصورة كما الاسمُ نسبةٌ بالحرف: ما أخفى الجهةَ أخفاه.
it('يُخفي الشعار حيث تُخفى الجهة', function (VenueMode $mode): void {
    $html = identityPage(mode: $mode);

    expect($html)->not->toContain('class="brand-mark"')
        ->not->toContain('class="venue-logo"');
})->with([VenueMode::SpeakerOnly, VenueMode::PublisherOnly]);

/*
 * ─── سطر الاعتماد — الهوية §٠٥ ───────────────────────────────────────
 */

it('يختم البصمة بسطر الاعتماد بلسان الصفحة', function (): void {
    config(['khulasah.platform_url' => 'https://khulasat.example']);

    $ar = identityPage();
    $attest = substr($ar, (int) strpos($ar, '<p class="attest">'));

    expect($attest)->toContain('<span>أُعدّت باستخدام</span>')
        // بضمّتها كما في الهوية — T-99.
        ->toContain('<a href="https://khulasat.example" target="_blank" rel="noopener"><b class="wm" lang="ar">خُلاصات</b></a>');
    // ★ **وسطرُ الاعتماد بلا طرفٍ ثالث** — والصفحةُ كلُّها كذلك (T-153،
    // يحرسها اختبارُ السطر اللاتيني أدناه).

    // وموضعُ الاسم يتبع اللسان: التركية تؤخّر الفعل.
    $tr = identityPage(locale: Locale::Tr);

    expect($tr)->toContain('<b class="wm">Khulasat</b></a>')
        ->toContain('<span>ile hazırlandı</span>')
        ->not->toContain(':brand');
});

/*
 * ─── ترتيب الذيل ─────────────────────────────────────────────────────
 */

// ترتيبُ T-98 (المواضع ← الأزرار ← البصمة) بدّله قرارُ مالك المنتج في T-99:
// المواضعُ آخرَ الصفحة والملفّ في القوالب كلّها.
it('يرتّب الذيل: الأزرار ثمّ البصمة ثمّ المواضع آخراً', function (SummaryTemplate $template): void {
    $html = identityPage($template);

    $sources = strpos($html, '<div class="sources">');
    $actions = strpos($html, '<div class="actions">');
    $colophon = strpos($html, '<footer class="colophon">');

    expect($sources)->not->toBeFalse()
        ->and($actions)->toBeLessThan($colophon)
        ->and($colophon)->toBeLessThan($sources);

    // ولا قسمَ يُرى بعد المواضع — ولوحُ الصانع (T-108) توقيعٌ لا قسم.
    $tail = substr($html, $sources);

    expect($tail)->not->toContain('<footer')
        ->not->toContain('class="actions"')
        ->not->toContain('<section');
})->with(SummaryTemplate::cases());

/*
 * ─── الورقة ─────────────────────────────────────────────────────────
 */

// الهويةُ ورقةٌ لا سمات: البنيةُ بلا `style=`، والقوالبُ كلُّها تُدرجها.
it('يُدرج ورقة الهوية ولا يترك سمة style في البنية', function (SummaryTemplate $template): void {
    $html = identityPage($template);

    expect($html)->toContain('.attest{')
        ->not->toContain(' style="');
})->with(SummaryTemplate::cases());

/*
 * ─── بطاقة بيانات المجلس — T-105 ─────────────────────────────────────
 *
 * بلاغُ مالك المنتج بلقطة: شريطُ النسبة سطرٌ مسرود تفصله نقاط، فلا يُعرف
 * أين ينتهي اسمٌ ويبدأ مكان. فصار حقائقَ لكلٍّ وسمُه فوقه وقيمتُه تحته.
 */

function attribPage(
    SummaryTemplate $template = SummaryTemplate::Classic,
    VenueMode $mode = VenueMode::Institution,
    Locale $locale = Locale::Ar,
    array $majlis = [],
): string {
    $tenant = Tenant::factory()->create([
        'name_ar' => 'جهة الاختبار',
        'name_latin' => 'TENANT NAME',
        'brand_kit' => ['template' => $template->value],
    ]);

    return app(PageRenderer::class)->render(new ContentObject(
        structure: ['title_ar' => 'درس'],
        evidence: [],
        majlis: [
            'title' => 'درس',
            'sheikh' => 'ملقي الاختبار',
            'weekday' => 'الجمعة',
            'date_hijri' => '٢٩ ربيع الأول ١٤٤٨',
            'date_gregorian' => '2026/09/11',
            'time_note' => 'بعد صلاة الجمعة',
            'venue_mode' => $mode,
            ...$majlis,
        ],
        bodyHtml: '<section><h2>محور</h2><p>متن</p></section>',
        locale: $locale,
    ), BrandKit::forTenant($tenant))->contents;
}

it('يعرض بيانات المجلس حقائقَ موسومةً لا سطراً مسروداً', function (SummaryTemplate $template): void {
    $html = attribPage($template);

    expect($html)->toContain('<span class="k">ألقاها</span>')
        ->toContain('<span class="v" lang="ar" dir="rtl">ملقي الاختبار</span>')
        ->toContain('<span class="k">المكان</span>')
        ->toContain('<span class="v" lang="ar" dir="rtl">جهة الاختبار</span>')
        ->toContain('<span class="sub">TENANT NAME</span>')
        ->toContain('<span class="k">التاريخ</span>')
        // الهجريُّ بارزٌ ومعه يومُه، والميلاديُّ ووقتُه تحته بفاصلٍ بينهما.
        ->toContain('<span class="v" lang="ar" dir="rtl">الجمعة ٢٩ ربيع الأول ١٤٤٨</span>')
        ->toContain('<span class="sub" lang="ar" dir="rtl">2026/09/11، بعد صلاة الجمعة</span>')
        // ولا نقاطَ تفصل سطراً مسروداً.
        ->not->toContain('<span class="dot"></span>');
})->with(SummaryTemplate::cases());

// **وإن غاب الهجريُّ واليومُ صعد الميلاديُّ مكانَه** — ولا وسمٌ فوق فراغ.
it('يرفع الميلادي إلى موضع البارز حين لا هجريَّ ولا يوم', function (): void {
    $html = attribPage(majlis: ['weekday' => '', 'date_hijri' => null, 'time_note' => '']);

    expect($html)->toContain('<span class="v">2026/09/11</span>')
        ->toContain('<span class="k">التاريخ</span>');
});

// وبطاقةٌ لا حقيقةَ فيها لا تُرسم — إطارٌ فارغ أسوأ من إطارٍ محذوف.
it('يطوي البطاقة كلَّها حين لا بيانَ يُعرض', function (): void {
    $html = attribPage(
        mode: VenueMode::SpeakerOnly,
        majlis: ['sheikh' => null, 'weekday' => '', 'date_hijri' => null, 'date_gregorian' => null, 'time_note' => ''],
    );

    expect($html)->not->toContain('class="attrib"');
});

// ونمطُ النسبة يحكم ما يظهر كما كان قبل البطاقة.
it('يُخفي حقل المكان حيث تُخفى الجهة', function (): void {
    $html = attribPage(mode: VenueMode::SpeakerOnly);

    expect($html)->not->toContain('<span class="k">المكان</span>')
        ->not->toContain('جهة الاختبار')
        ->toContain('<span class="k">ألقاها</span>');
});

// والوسومُ بلسان الصفحة — T-87. والقيمُ عربيّةٌ موسومةٌ في كلّ لسان.
it('يكتب وسوم الحقائق بلسان الصفحة', function (): void {
    $html = attribPage(locale: Locale::En);

    expect($html)->toContain('<span class="k">Delivered by</span>')
        ->toContain('<span class="k">Venue</span>')
        ->toContain('<span class="k">Date</span>')
        ->toContain('<span class="v" lang="ar" dir="rtl">ملقي الاختبار</span>');
});

it('ينسّق البطاقة في ورقة الهوية لا في ورقة القالب', function (): void {
    $html = attribPage();

    expect($html)->toContain('.unwan .attrib .fact{')
        // و`_style` لم يُمسّ: سطرُه المسرود باقٍ كما نُقل من المهارة.
        ->toContain('.attrib b{color:var(--emerald);font-weight:500}');
});

/*
 * ─── الأعمدةُ تبدأ من حافّة الصفّ العليا معاً — T-110 ─────────────────
 *
 * بلاغُ مالك المنتج بلقطة: عمود «ألقاها» (سطران: الوسم والقيمة، بلا
 * سطرٍ فرعيّ) وقع وسط الشريط رأسيّاً بدل أن يبدأ من أعلاه كـ«المكان»
 * و«التاريخ» (ثلاثةُ أسطرٍ لكلٍّ). والعلّة قِيست بمتصفّحٍ حقيقيّ لا خُمّنت:
 * `align-items:center` من `.attrib{}` القديمة في `_style` (باقيةٌ لأنّ
 * §2 القاعدة الأولى تمنع لمسها) تتسرّب إلى شبكة `.unwan .attrib` لأنّ
 * ورقة الهوية لم تُعرِّف `align-items` أصلاً — فيتوسَّط عمودٌ محتواه أقصر
 * من إخوته بدل أن يبدأ من حافّتهم. `align-items:stretch` هنا يُسقطها.
 */
it('يُسقط توسيطاً متسرّباً من القاعدة القديمة فتبدأ الأعمدة من الحافّة العليا معاً', function (): void {
    $html = attribPage();

    $selectorAt = strpos($html, '.unwan .attrib{');
    expect($selectorAt)->not->toBeFalse();

    // الخاصّيةُ داخل هذه القاعدة تحديداً — لا وجودَ النصّ في الصفحة عامّةً،
    // وإلا مرّ الاختبارُ لو وُجدت الكلمة في قاعدةٍ أخرى لا صلة لها.
    $rule = substr($html, (int) $selectorAt, (int) strpos($html, '}', (int) $selectorAt) - (int) $selectorAt);

    expect($rule)->toContain('align-items:stretch');
});

/*
 * ★ **وموضعُها داخل السرلوح** — T-106، بلاغُ مالك المنتج بلقطة: بطاقةٌ
 * مستقلّةٌ تحت السرلوح لوحٌ ثانٍ يُشتّت، وذيلُ السرلوح يجمع البيان بصاحبه.
 */
it('يجعل بيانات المجلس ذيلاً للسرلوح لا لوحاً تحته', function (SummaryTemplate $template): void {
    $html = attribPage($template);

    $header = strpos($html, '<header class="unwan">');
    $band = strpos($html, '<div class="attrib">');
    $close = strpos($html, '</header>');

    expect($band)->not->toBeFalse()
        ->and($band)->toBeGreaterThan($header)
        ->and($band)->toBeLessThan($close)
        // ووعاءُ الموجز الذي كانت فيه رُفع، فلا يبقى حدُّه خطّاً معلَّقاً.
        // **والوسمُ هو المنفيّ لا اللفظ**: في ورقة الموجز تعليقٌ يذكر رفعه.
        ->and($html)->not->toContain('<div class="brief-foot">');
})->with(SummaryTemplate::cases());

// والميزانُ على صورته في المهارة — T-106: تصميمُ T-98 لم يُرضِ مالك المنتج.
it('يترك الميزان على صورته الأولى', function (): void {
    $blocks = (string) file_get_contents(resource_path('views/summary/blocks/classic.blade.php'));

    expect($blocks)->not->toContain('.axis-q')
        ->not->toContain('.pair > .side')
        // ورأسُ السؤال رماديٌّ هادئ كما في `_style`، لا متدرّجاً ولا منجَّماً.
        ->and(attribPage())->toContain('background:var(--paper-3);');
});

/*
 * ─── لوحُ صانع الصفحة — T-108 ────────────────────────────────────────
 *
 * بطلب مالك المنتج: «يجب إضافة شعار الإدارة الخاصّة بنا في النهاية».
 * والرمزُ على مربّعٍ مصمت وحده — موضعُه المجاز في §٠٥ من ملفّ الهوية.
 */
it('يختم الصفحة بلوح صانعها بعد المواضع', function (SummaryTemplate $template): void {
    config(['khulasah.platform_url' => 'https://khulasat.example']);

    $html = identityPage($template);

    $sources = strpos($html, '<div class="sources">');
    $maker = strpos($html, '<div class="maker">');

    expect($maker)->not->toBeFalse()
        ->and($sources)->not->toBeFalse()
        // المواضعُ قبله، فترتيبُ T-99 باقٍ على حاله.
        ->and($maker)->toBeGreaterThan($sources)
        ->and($html)->toContain('<a class="maker-lockup" href="https://khulasat.example"')
        // الكلمةُ شعارٌ لا يُترجَم، فتُوسَم عربيّةً في كلّ لسان — حارس T-87.
        ->toContain('<b class="wm" lang="ar" dir="rtl">خُلاصات</b>');

    // ولا شيءَ بعده: هو آخرُ ما في الصفحة.
    expect(substr($html, $maker))->not->toContain('<div class="sources">')
        ->not->toContain('<footer');
})->with(SummaryTemplate::cases());

/*
 * ─── السطرُ اللاتيني — T-109، وT-153 ─────────────────────────────────
 *
 * «khulasat.io» في `maker-latin` رابطٌ إلى المنصّة، بلا تغيير لونٍ ولا
 * تصميم. **ولا نسبةَ إلى شركةٍ صانعة** في السطر ولا في الصفحة كلّها —
 * بطلب مالك المنتج (T-153)، في اللغات الأربع.
 */
it('يجعل khulasat.io وحدها في السطر اللاتيني رابطاً، بلا شركةٍ صانعة', function (Locale $locale): void {
    config(['khulasah.platform_url' => 'https://khulasat.example']);

    $html = identityPage(locale: $locale);

    expect($html)->toContain('<p class="maker-latin" dir="ltr"><a href="https://khulasat.example" target="_blank" rel="noopener">khulasat.io</a></p>')
        // لا تغيير بصريّ — الرابطُ يرث لون السطر ولا يُزخرَف.
        ->toContain('.maker-latin a{color:inherit;text-decoration:none}');
})->with(Locale::cases());

it('يرسم التاج ونمط الخلفية بألوان اللوحة لا بذهبٍ مثبَّت', function (): void {
    $html = identityPage(logo: null);

    $crest = substr($html, (int) strpos($html, '<svg class="crest"'));
    $crest = substr($crest, 0, (int) strpos($crest, '</svg>'));

    expect($crest)->toContain('url(#crest-gilt)')
        ->not->toMatch('/#[0-9A-Fa-f]{6}\b/')
        ->and($html)->toContain('mask-image:url(')
        ->and($html)->toContain('.crest .gilt-lo{stop-color:var(--gold)}');
});
