<?php

declare(strict_types=1);

use App\Actions\Render\RenderOutput;
use App\Enums\Locale;
use App\Enums\MatchStatus;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\VenueMode;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Services\Render\BodyPurifier;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\Palette;
use App\Support\Render\RenderedEvidence;
use Illuminate\Support\Facades\Log;

/*
 * عارض الصفحة — T-14، والمواصفة §8 و§8-أ.
 *
 * **والحدّ الأوّل هنا:** لم يُغيَّر حرفٌ من CSS القالب ولا من JavaScriptه.
 * ولذلك أوّل ما يُختبر هو ذلك بعينه، بمقارنةٍ مع الملفّ داخل `khulasah.skill`.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name_ar' => 'جهة الاختبار',
        'name_ar_full' => 'جهة الاختبار الكاملة',
        'name_latin' => 'Tenant Name',
    ]);

    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'subtitle_ar' => 'عنوان فرعي',
        'speaker_name' => 'اسم الملقي',
        'speaker_title' => 'الشيخ',
        'weekday' => 'الجمعة',
        'hijri_date' => '١٤٤٨/٣/١٥',
        'venue_mode' => VenueMode::Institution->value,
    ]);

    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => $this->lecture->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<section><h2 class="sec-head">المحور الأوّل</h2><p class="lead">فقرة.</p></section>'
            .'<p class="closing">فمن حفظ وقته حفظه الله.</p>',
    ]);
});

/** شاهدٌ محسوم على المهمّة — به تُرسم قائمةُ التخريج. */
function withEvidence(SummaryJob $job): SummaryJob
{
    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'hadith',
        'matched_text' => 'سَدِّدوا وقارِبوا',
        'match_status' => MatchStatus::Exact,
        'review_status' => ReviewStatus::AutoPassed,
        'source_meta' => ['takhrij' => 'رواه البخاري', 'grade' => 'sahih',
            'book' => 'صحيح البخاري', 'hadith_number' => '923'],
    ]);

    return $job->fresh();
}

/** الصفحةُ مرسومةً بلغةٍ أخرى — تُبنى من ترجمةٍ محفوظة كما في التشغيل. */
function renderTranslated(SummaryJob $job, Locale $locale): string
{
    SummaryTranslation::query()->updateOrCreate(
        ['summary_job_id' => $job->id, 'locale' => $locale->value],
        [
            'tenant_id' => $job->tenant_id,
            'title' => 'The Lost Treasure',
            'body_html' => '<p class="lead">An English paragraph.</p>',
        ],
    );

    return app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->fresh(), $locale), BrandKit::forTenant($job->tenant))
        ->contents;
}

function renderPage(SummaryJob $job): string
{
    return app(PageRenderer::class)
        ->render(ContentObject::fromJob($job), BrandKit::forTenant($job->tenant))
        ->contents;
}

/*
 * ─── الحدّ الذي لا يُتجاوز ───────────────────────────────────────────
 */

/**
 * **لم يُغيَّر حرف من CSS أو JS القالب** — CLAUDE.md §2 القاعدة الأولى.
 *
 * ويُقاس بالمقارنة مع الأرشيف نفسه لا بالثقة: `khulasah.skill` ملفّ ZIP،
 * فيُفكّ ويُقرأ منه القالب، وتُقارَن الكتلتان بايتاً ببايت.
 */
it('ينقل CSS القالب وJavaScriptه حرفاً بحرف', function (): void {
    $zip = new ZipArchive;
    expect($zip->open(base_path('khulasah.skill')))->toBeTrue();

    $template = (string) $zip->getFromName('khulasah/assets/template.html');
    $zip->close();

    expect($template)->not->toBe('');

    /*
     * الكتلتان تُقتطعان **بالوسوم نفسها لا بأرقام أسطر**: رقمٌ ثابت يعتمد
     * على ألّا يتغيّر القالب، وهذا الاختبار غرضه أن يسقط إن تغيّر — فلا
     * يُبنى على ما يحرسه.
     */
    $between = static function (string $haystack, string $open, string $close): string {
        $start = strpos($haystack, $open);
        $end = strpos($haystack, $close, (int) $start);

        return trim(substr($haystack, (int) $start + strlen($open), (int) $end - (int) $start - strlen($open)));
    };

    /*
     * ★ **استثناءٌ واحدٌ مسمّى** — T-55. مواضعُ `{{TITLE}}` وأخواتها في
     * سكربت المهارة **مواضعُ تُملأ لا تُصان**، و`@verbatim` صانتها فخرجت
     * أسماؤها نصّاً لمن يشارك الصفحة. فتُردّ هنا إلى صورتها في المهارة قبل
     * المقارنة، **ويبقى الحارس على ما سواها**: أيّ تعديلٍ آخر في السكربت
     * يُسقط هذا الاختبار كما كان.
     */
    $sanctioned = [
        'title:KHULASAH_SHARE.title,' => "title:'{{TITLE}} — {{SUBTITLE}}',",
        'text:KHULASAH_SHARE.text,' => "text:'ملخّصٌ لمحاضرة {{SHEIKH_FULL}} في {{VENUE_SHORT}}.',",
        // ★ **ورسائلُ النسخ الثلاث** — T-87: تُملأ بلسان الصفحة، وتُردّ هنا إلى
        // لفظها في المهارة. وما سواها محروسٌ كما كان.
        'say(KHULASAH_SHARE.copied);' => "say('نُسخ الرابط، يمكنك لصقه ومشاركته');",
        'say(KHULASAH_SHARE.copyFailed);' => "say('تعذّر النسخ، انسخ الرابط من شريط العنوان');",
        'say(KHULASAH_SHARE.copyManual);' => "say('انسخ الرابط من شريط العنوان أعلى المتصفّح');",
    ];

    $ours = static function (string $view) use ($between, $sanctioned): string {
        $raw = (string) file_get_contents(resource_path("views/summary/partials/{$view}.blade.php"));

        return str_replace(
            array_keys($sanctioned),
            array_values($sanctioned),
            $between($raw, "@verbatim\n", '@endverbatim'),
        );
    };

    expect($ours('_style'))->toBe($between($template, "<style>\n", '</style>'))
        ->and($ours('_script'))->toBe($between($template, "<script>\n", '</script>'));
});

/*
 * ─── التنقية — المخطر الأوّل في المشروع (§12) ────────────────────────
 */

/** **`<script>alert(1)</script>` لا يصل إلى المخرَج** — البند السادس. */
it('لا يمرّر سكربتاً في متن النموذج', function (): void {
    $this->job->forceFill([
        'body_html' => '<p class="lead">نصّ</p><script>alert(1)</script>',
    ])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->not->toContain('alert(1)')
        ->and($html)->toContain('نصّ');
});

it('يُسقط style و on* من متن النموذج', function (): void {
    $purified = app(BodyPurifier::class)->purify(
        '<p class="lead" style="color:red" onclick="steal()">نصّ</p>'
    );

    expect($purified)->not->toContain('style=')
        ->not->toContain('onclick')
        ->toContain('نصّ');
});

/** الصنف الغريب يسقط، **ويبقى النصّ**: لا يضيع محتوى بصنفٍ لا نعرفه. */
it('يُسقط الصنف غير المعروف ويُبقي نصّه', function (): void {
    $purified = app(BodyPurifier::class)->purify('<p class="hacker-block">نصّ باقٍ</p>');

    expect($purified)->not->toContain('hacker-block')
        ->toContain('نصّ باقٍ');
});

it('يُبقي أصناف المهارة', function (string $class): void {
    $purified = app(BodyPurifier::class)->purify("<div class=\"{$class}\">نصّ</div>");

    expect($purified)->toContain($class);
})->with(['sec-head', 'lead', 'axis-card', 'compare', 'pillar', 'muted']);

/*
 * ★ **الوسوم التي تنصّ عليها تعليمات المرحلة ٥** — T-45.
 *
 * وكانت `article` ساقطةً من قائمة السماح مع أنّ التعليمات تنصّ عليها في
 * موضعين (`article.imam` و`article.axis-card`)، فكان غلافُ كلّ بطاقةٍ في
 * كلّ ملخّصٍ يسقط صامتاً. فيُحرَس الوسمُ هنا بالاسم لا بالصنف وحده.
 */
it('يُبقي الوسوم التي تطلبها حزمة التعليمات', function (string $tag, string $class): void {
    $purified = app(BodyPurifier::class)->purify("<{$tag} class=\"{$class}\">نصّ</{$tag}>");

    expect($purified)->toContain("<{$tag} class=\"{$class}\">");
})->with([
    ['article', 'imam'],
    ['article', 'axis-card'],
    ['section', 'night'],
]);

it('يُسجّل ما أسقطه بدل أن يُسقطه صامتاً', function (): void {
    Log::spy();

    app(BodyPurifier::class)->purify('<div class="comparison"><p class="lead">نصّ</p></div>');

    Log::shouldHaveReceived('warning')
        ->withArgs(static fn (string $message, array $context): bool => $context['classes'] === ['comparison']);
});

it('لا يُسجّل شيئاً حين لا يسقط شيء', function (): void {
    Log::spy();

    app(BodyPurifier::class)->purify('<article class="imam"><h3>عنوان</h3></article>');

    Log::shouldNotHaveReceived('warning');
});

/*
 * ─── اللوحات والصيغ ──────────────────────────────────────────────────
 */

it('يعرّف ست لوحات ألوان', function (): void {
    expect(Palette::all())->toHaveCount(6);
});

/*
 * ★ **بطاقةُ التطبيق تتبع اللوحة** — T-79، بلاغُ مالك المنتج.
 *
 * كان تدرّجُها صريحاً في ورقة القالب بلون الزمرّدية، فخرجت خضراءَ بين
 * ترويسةٍ وكولوفونٍ كحليّين في اللوحة النيليّة.
 */
it('يلوّن بطاقة التطبيق بلوحة الجهة', function (): void {
    $this->tenant->forceFill(['brand_kit' => ['palette' => 'indigo']])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->toContain('--night:#192848')
        ->and($html)->toContain('.night{background:linear-gradient(170deg,var(--night),var(--night-deep))}');

    // وكلُّ لوحةٍ تعرّفهما — لوحةٌ بلا متغيّرٍ تُسقط التدرّج كلَّه فتخرج البطاقةُ بلا خلفية.
    foreach (Palette::all() as $palette) {
        expect($palette->vars)->toHaveKeys(['night', 'night-deep']);
    }
});

// **والزمرّديةُ بقيمتَي القالب حرفاً** — فمخرَجُها قبل T-79 وبعدها واحد.
it('يبقي بطاقة التطبيق الزمرّدية بلون القالب نفسه', function (): void {
    expect(Palette::find('emerald')->vars['night'])->toBe('#123028')
        ->and(Palette::find('emerald')->vars['night-deep'])->toBe('#0A1F19');
});

it('يحقن متغيّرات اللوحة في :root ولا يمسّ ورقة القالب', function (): void {
    $this->tenant->forceFill(['brand_kit' => ['palette' => 'indigo']])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->toContain('--emerald:#243B6B')
        // ورقة القالب باقية بقيمها الأصلية، والحقن يعلوها بترتيب التتالي.
        ->and($html)->toContain('--paper:#F3EEE1');
});

it('يقبل اللوحة المجهولة بالافتراضية ولا يسقط', function (): void {
    $this->tenant->forceFill(['brand_kit' => ['palette' => 'لا-وجود-لها']])->save();

    expect(renderPage($this->job->refresh()))->toContain('--emerald:#1B4D3E');
});

/** الصيغ الثلاث لبطاقة المجلس — البند الخامس. */
it('يطوي كتلة الجهة في صيغة الشيخ وحده', function (): void {
    $this->lecture->forceFill(['venue_mode' => VenueMode::SpeakerOnly->value])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->not->toContain('جهة الاختبار الكاملة')
        ->and($html)->toContain('اسم الملقي');
});

it('يعرض كتلة الجهة في صيغة المؤسّسة', function (): void {
    expect(renderPage($this->job))->toContain('جهة الاختبار الكاملة');
});

/*
 * ─── الشواهد والتخريج ────────────────────────────────────────────────
 */

/** **الدرجة تُطبع مع الضعيف** — سياسة البيان §7-5، وقاعدة حاجبة. */
it('يطبع درجة الضعيف في قائمة التخريج', function (): void {
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => 'لفظ المحاضرة',
        'normalized_text' => 'لفظ المحاضرة',
        'matched_text' => 'لفظ المصدر كما هو',
        'match_status' => 'partial',
        'review_status' => ReviewStatus::AutoPassed->value,
        'source_meta' => ['takhrij' => 'رواه أبو داود', 'grade' => 'daif'],
    ])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->toContain('لفظ المصدر كما هو')
        ->and($html)->toContain('رواه أبو داود')
        ->and($html)->toContain('ضعيف')
        // والحاشية تقول إنّ البيان مقصود لا سهو.
        ->and($html)->toContain('فقد بُيّنت درجته');
});

/** **ولا يُعرض لفظ المحاضرة** — يُعرض لفظ المصدر. */
it('يعرض لفظ المصدر لا لفظ المحاضرة', function (): void {
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => 'لفظ زاغ عن مصدره',
        'normalized_text' => 'لفظ زاغ عن مصدره',
        'matched_text' => 'لفظ المصدر الصحيح',
        'match_status' => 'partial',
        'review_status' => ReviewStatus::AutoPassed->value,
    ])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->toContain('لفظ المصدر الصحيح')
        ->and($html)->not->toContain('لفظ زاغ عن مصدره');
});

/** والمحذوف لا يظهر في التخريج — أُخرج من المتن فلا يعود من بابٍ آخر. */
it('لا يُدرج المحذوف في قائمة التخريج', function (): void {
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => 'شاهد محذوف',
        'normalized_text' => 'شاهد محذوف',
        'match_status' => 'none',
        'review_status' => ReviewStatus::Removed->value,
    ])->save();

    expect(renderPage($this->job->refresh()))->not->toContain('شاهد محذوف');
});

/*
 * ─── العقد والمخرجات ─────────────────────────────────────────────────
 */

it('يقيّد المخرَج في outputs بنسخة العارض', function (): void {
    app(RenderOutput::class)->handle($this->job, app(PageRenderer::class));

    $output = Output::query()->where('summary_job_id', $this->job->id)->first();

    expect($output)->not->toBeNull()
        ->and($output->type)->toBe(OutputType::Page)
        ->and($output->renderer_version)->not->toBe('')
        ->and($output->meta['bytes'])->toBeGreaterThan(1000);
});

/** إعادة العرض تكتب فوق الصفّ ولا تكدّس — §8-أ. */
it('لا يكدّس صفوف outputs عند إعادة العرض', function (): void {
    app(RenderOutput::class)->handle($this->job, app(PageRenderer::class));
    app(RenderOutput::class)->handle($this->job->refresh(), app(PageRenderer::class));

    expect(Output::query()->where('summary_job_id', $this->job->id)->count())->toBe(1);
});

/** **ولا يُرسَم ما لم يُحسم** — الحدّ الرابع. */
it('يمنع الرسم وشاهدٌ لم يُحسم', function (): void {
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => 'شاهد معلّق',
        'normalized_text' => 'شاهد معلّق',
        'match_status' => 'partial',
        'review_status' => ReviewStatus::Pending->value,
    ])->save();

    expect(fn () => app(RenderOutput::class)->handle($this->job->refresh(), app(PageRenderer::class)))
        ->toThrow(RuntimeException::class);
});

/*
 * ─── الملفّ قائم بذاته ───────────────────────────────────────────────
 */

/**
 * **CSS داخلي، ولا اعتماد خارجي إلّا خطوط Google** — §8.
 *
 * ★ **واستُثنيت معه شاهدةُ العدّ وحدها** (T-31)، والفرق بينها وبين الاعتمادية
 * فرقٌ في الجنس لا في الدرجة: **ورقةُ أنماطٍ لم تصل تكسر الصفحة، وشاهدةٌ لم
 * تصل لا يُرى أثرُ غيابها** — لا في بكسل ولا في سطر. ولذلك يُفحص أدناه أنّها
 * داخل `hidden`، فلا تحجز مكاناً حتى وهي تُحمَّل.
 *
 * **والملفّ المنزَّل يبقى قائماً بذاته قطعاً** — انظر الاختبار الذي يليه:
 * فيه الاستثناء مرفوعٌ ولا يُقبل رابطٌ إلّا للخطوط.
 */
it('يُخرج ملفّاً قائماً بذاته، ولا يستثني إلّا شاهدة العدّ', function (): void {
    $html = renderPage($this->job);

    /*
     * ويُفحص **ما يُحمَّل** لا كلّ رابط: `<a href>` تصفّحٌ لا اعتمادية،
     * والصفحة تعمل كاملةً بلا فتحه. وأمّا `<link>` و`<script src>`
     * و`<img src>` فمواردُ لا تُرسم الصفحة بدونها.
     */
    preg_match_all('/<(?:link|script|img|iframe)\b[^>]*\b(?:src|href)="(https?:[^"]+)"/i', $html, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $url) {
        expect(str_starts_with($url, 'https://fonts.g') || str_contains($url, '/v/'))
            ->toBeTrue("اعتمادية خارجية غير مأذون فيها: {$url}");
    }

    // والشاهدة داخل `hidden`، فلا تُزحزح بكسلاً ولو حُمّلت.
    // واللسانُ في مسارها منذ T-140، فلكلّ لغةٍ صفُّها في العدّاد.
    expect($html)->toMatch('/<div hidden><img src="[^"]+\/v\/\d+\/page\/ar\.gif"/');

    expect($html)->toContain('<style>')
        ->and($html)->not->toContain('<link rel="stylesheet" href="/');
});

/**
 * ★ **والنسخة التي تُنزَّل أو تُعايَن لا شاهدة فيها أصلاً** — T-31.
 *
 * فالمنزَّل يُفتح من قرصٍ بلا شبكة، **والمعاينة معاينةُ صاحب الصفحة**:
 * عدُّها زيارةً يجعل العدّاد مرآةً له لا لقرّائه.
 */
it('لا يضع شاهدة عدّ في النسخة المنزَّلة ولا في المعاينة', function (): void {
    $content = ContentObject::fromJob($this->job->refresh())->withoutBeacon();

    $html = app(PageRenderer::class)
        ->render($content, BrandKit::forTenant($this->job->tenant))
        ->contents;

    preg_match_all('/<(?:link|script|img|iframe)\b[^>]*\b(?:src|href)="(https?:[^"]+)"/i', $html, $matches);

    foreach ($matches[1] as $url) {
        expect($url)->toStartWith('https://fonts.g');
    }

    expect($html)->not->toContain('/v/');
});

/*
 * ─── الخاتمة والكولوفون — بلاغ سلطان، ٩ أيلول ٢٠٢٦ ───────────────────
 */

/**
 * **لا خاتمتان** — `closing_line` سقالةٌ لمرحلة الكتابة (المرحلة ٥) لا
 * نصٌّ للعرض، والمعروض هو `body.closing` وحده المدموج في `bodyHtml`.
 * وكانتا تُطبعان معاً قبل هذا الإصلاح.
 */
it('لا يكرّر الخاتمة من structure_json وbody معاً', function (): void {
    $this->job->forceFill([
        'structure_json' => ['title_ar' => 'عنوان الدرس', 'closing_line' => 'فمن حفظ وقته حفظه الله.'],
    ])->save();

    $html = renderPage($this->job->refresh());

    expect(substr_count($html, 'فمن حفظ وقته حفظه الله.'))->toBe(1);
});

/** **زرّ «مشاهدة المحاضرة كاملة» يفتح المحاضرة نفسها**، لا قناة الجهة. */
it('يربط زر المحاضرة برابط المحاضرة لا بقناة الجهة', function (): void {
    $this->lecture->forceFill(['source_url' => 'https://www.youtube.com/watch?v=lecture123'])->save();
    $this->tenant->forceFill(['brand_kit' => ['youtube_url' => 'https://www.youtube.com/@channel']])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->toContain('href="https://www.youtube.com/watch?v=lecture123"')
        ->and($html)->not->toContain('href="https://www.youtube.com/@channel"');
});

/** ولا زرّ أصلاً حين لا رابط للمحاضرة — القناة العامّة ليست بديلاً صامتاً. */
it('يخفي زر المحاضرة حين لا رابط لها', function (): void {
    $this->lecture->forceFill(['source_url' => null])->save();
    $this->tenant->forceFill(['brand_kit' => ['youtube_url' => 'https://www.youtube.com/@channel']])->save();

    $html = renderPage($this->job->refresh());

    expect($html)->not->toContain('مشاهدة المحاضرة كاملة');
});

it('يبني صفحة كاملة صالحة', function (): void {
    $html = renderPage($this->job);

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and($html)->toContain('dir="rtl"')
        ->and($html)->toContain('عنوان الدرس')
        ->and($html)->toContain('فمن حفظ وقته حفظه الله.')
        ->and($html)->toContain('</html>');
});

/*
 * T-55 — زرّ المشاركة.
 *
 * `@verbatim` تحفظ السكربت بايتاً ببايت، **فحفظت مواضعَ الملء كما حفظت
 * السكربت نفسه**، فكان الزرّ يعرض `{{TITLE}}` نصّاً لمن يشارك الصفحة.
 */
it('يملأ نصّ المشاركة ولا يترك اسم الموضع', function (): void {
    $html = renderPage($this->job);

    expect($html)->not->toContain('{{TITLE}}')
        ->and($html)->not->toContain('{{SUBTITLE}}')
        ->and($html)->not->toContain('{{SHEIKH_FULL}}')
        ->and($html)->not->toContain('{{VENUE_SHORT}}')
        ->and($html)->toContain('KHULASAH_SHARE')
        ->and($html)->toContain('عنوان الدرس');
});

// **والنصّ يدخل سلسلةَ JavaScript لا متنَ HTML**، فتهريبُ HTML وحده لا
// يحرس هذا الموضع: عنوانٌ فيه علامةُ اقتباس يكسر السكربت كلَّه فيسقط زرّ
// الطباعة معه، وعنوانٌ فيه `</script>` يُغلق الوسمَ فيهرب ما بعده إلى المتن.
it('لا يكسر السكربت بعنوانٍ فيه علامة اقتباس أو وسم', function (): void {
    $this->job->forceFill([
        'structure_json' => ['title_ar' => 'عنوان \'الدرس\' "حقًّا" </script><img src=x>'],
    ])->save();

    $html = renderPage($this->job->fresh());

    preg_match('/var KHULASAH_SHARE = (.+);/', $html, $match);

    expect($match)->not->toBeEmpty()
        // الوسمُ لا يخرج حرفاً، فلا يُغلق السكربت من داخل السلسلة.
        ->and($match[1])->not->toContain('</script>')
        ->and($match[1])->not->toContain('<img')
        // بل يخرج مهرَّباً لسياق JavaScript.
        ->and($match[1])->toContain('\\u003C')
        // وعلامةُ الاقتباس لا تُنهي السلسلة.
        ->and($match[1])->toContain('\\u0027');

    // والصفحة تبقى وثيقةً واحدة: وسمُ سكربتٍ واحدٌ يُفتح وواحدٌ يُغلق.
    expect(substr_count($html, '<script>'))->toBe(substr_count($html, '</script>'));
});

/*
 * T-57 — بيانات الصفحة.
 *
 * `BuildOutputMeta` كانت مبنيّةً لا يناديها أحد، فيخرج الرأسُ بـ`<title>`
 * وحده: **لا وصفَ ولا وسمَ مشاركة** في منتجٍ غايتُه صفحةٌ تُشارَك.
 */
it('يكتب وسوم الوصف والمشاركة من مخرَج المرحلة السادسة', function (): void {
    $this->job->forceFill(['output_meta_json' => [
        'meta_title' => 'عنوان الدرس — السكينة',
        'meta_description' => 'ملخّصٌ يبيّن معنى الحياة الطيّبة وأسباب السكينة.',
    ]])->save();

    $html = renderPage($this->job->fresh());

    expect($html)->toContain('<meta name="description" content="ملخّصٌ يبيّن معنى الحياة الطيّبة وأسباب السكينة.">')
        ->and($html)->toContain('<meta property="og:description"')
        ->and($html)->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->and($html)->toContain('<title>عنوان الدرس — السكينة</title>');
});

// **وسقوطُها لا يُسقط النشر.** فصفحةٌ بلا وصفٍ تُرسم كاملةً، ولا يخرج وسمٌ
// فارغ: بطاقةٌ بوصفٍ فارغ تُعرض ناقصةً ولا تُطوى.
it('يرسم الصفحة كاملةً حين تسقط المرحلة السادسة', function (): void {
    $this->job->forceFill(['output_meta_json' => null])->save();

    $html = renderPage($this->job->fresh());

    expect($html)->not->toContain('<meta name="description"')
        ->and($html)->not->toContain('og:description')
        // والعنوانُ يسقط إلى عنوان المحاضرة، والصفحةُ تخرج بمتنها.
        ->and($html)->toContain('<title>عنوان الدرس — عنوان فرعي</title>')
        ->and($html)->toContain('class="closing"');
});

/*
 * ═══ T-66 — اتّجاهُ العربيّ داخل صفحةٍ غير عربية ═══
 *
 * **بلاغُ مالك المنتج بلقطات من الصفحة الإنجليزية:** `dir` كان على `<html>`
 * وحده، فالعربيُّ داخلها بلا اتّجاه. فتحلّ خوارزميةُ يونيكود علاماتِ الترقيم
 * المحايدة بحسب اتّجاه الفقرة الإنجليزيّ، فتقفز النقطةُ والقوسُ إلى الطرف
 * الخطأ في متن كلّ حديث، وينقلب ترتيبُ شطري شريط النسبة.
 *
 * ★ **ولفظُ الشاهد عربيٌّ في كلّ اللغات** — T-38. فاتّجاهُه عليه لا على الصفحة.
 */
it('يثبّت اتّجاه العربيّ على عناصره لا على الصفحة', function (): void {
    $this->job->forceFill([
        'body_html' => '<div class="sacred"><p class="text hadith" lang="ar" dir="rtl">'
            .'قال رسول الله ﷺ: «سدّدوا وقاربوا».</p><span class="src">رواه البخاري</span></div>',
    ])->save();

    $html = renderPage($this->job->fresh());

    expect($html)->toContain('<p class="text hadith" lang="ar" dir="rtl">')
        // وفي شريط النسبة الأسماءُ وحدها موسومة — وسومُه بلسان الصفحة (T-87)،
        // وأسماءُ الشيخ والجهة لا تُترجَم. **والوسمُ هو المحروس هنا لا وعاؤه**:
        // صار الشريطُ بطاقةَ حقائقَ في T-105، فالاسمُ في قيمتها لا في `<b>`.
        ->and($html)->toContain('<span class="v" lang="ar" dir="rtl">اسم الملقي</span>')
        // والتنويهُ في الذيل — `auto` لأنّ الجهة قد تكتبه بلغةٍ غير لغة
        // الصفحة، فيُقرأ اتّجاهُه من أوّل حرفٍ ذي اتّجاه فيه (T-69).
        ->and($html)->toContain('<p class="note" dir="auto">');
});

// **والمنقّي يمرّرهما ولا يمرّر ما سواهما.** فقيمةُ `dir` محصورةٌ في
// `ltr|rtl`، و`style` تبقى ممنوعةً — §8.
it('يمرّر الاتّجاه واللغة ولا يفتح بهما باباً', function (): void {
    $purified = app(BodyPurifier::class)->purify(
        '<p class="text" lang="ar" dir="rtl">نصّ</p>'
        .'<p dir="javascript:alert(1)" style="color:red" onclick="x()">ب</p>'
    );

    expect($purified)->toContain('lang="ar" dir="rtl"')
        ->and($purified)->not->toContain('javascript')
        ->and($purified)->not->toContain('style=')
        ->and($purified)->not->toContain('onclick');
});

/*
 * ═══ T-69 — إطارُ الصفحة بلسان قارئها ═══
 *
 * **بلاغُ مالك المنتج بلقطتين:** المتنُ إنجليزيٌّ والإطارُ عربيٌّ كلُّه.
 * وقارئٌ لا يعرف العربية لا يعرف أنّ الزرّ يفتح المحاضرة، ولا أنّ السطر
 * تنويهٌ لا متن.
 */
it('يكتب إطار الصفحة بلسانها لا بالعربية دائماً', function (): void {
    $english = renderTranslated(withEvidence($this->job), Locale::En);

    expect($english)->toContain('Sources of the verses and hadiths')
        ->and($english)->toContain('Delivered by')
        ->and($english)->toContain('is not a verbatim transcript')
        ->and($english)->toContain('Report an error in this summary')
        ->and($english)->not->toContain('مواضع الآيات والأحاديث')
        ->and($english)->not->toContain('للتنبيه على خطأ');
});

it('يبقي الإطار عربياً في صفحة لغة المصدر', function (): void {
    $arabic = renderPage(withEvidence($this->job));

    expect($arabic)->toContain('مواضع الآيات والأحاديث')
        ->and($arabic)->toContain('للتنبيه على خطأ في هذا الملخّص')
        ->and($arabic)->toContain('رواه البخاري، رقم ٩٢٣ — صحيح');
});

/*
 * **والتخريجُ واحدٌ لا اثنان.** كان يُقرأ الشاهدُ الواحد بتخريجين مختلفين
 * في صفحةٍ واحدة: إنجليزيٍّ في المتن وعربيٍّ في القائمة.
 *
 * ★ **ويُبنى ولا يُترجَم**: «رواه البخاري» اصطلاحٌ يثبت، ونموذجٌ يترجمه في
 * كلّ ملخّصٍ يصرف مالاً ويُخرج صياغةً مختلفة كلَّ مرّة.
 */
it('يبني تخريج الشاهد بلسان الصفحة بلا نداء نموذج', function (): void {
    $evidence = new RenderedEvidence(
        kind: 'hadith',
        text: 'سَدِّدوا وقارِبوا',
        takhrij: 'رواه البخاري',
        grade: 'sahih',
        book: 'صحيح البخاري',
        hadithNumber: '923',
    );

    // ★ **وفاصلتُه لاتينية** — كان هذا التوكيدُ نفسُه يحرس «،» عربيةً في
    // سطرٍ إنجليزي، فحرس الخطأ بدل أن يكشفه، حتى ظهر في صفحةٍ منشورة.
    expect($evidence->citation(Locale::En))->toBe('Reported by al-Bukhari, no. 923 — authentic')
        ->and($evidence->citation(Locale::En))->not->toContain('،')
        ->and($evidence->citation(Locale::Tr))->toContain('Buhârî')
        ->and($evidence->citation(Locale::Ar))->toBe('رواه البخاري، رقم ٩٢٣ — صحيح');
});

// **ويسقط إلى المحفوظ متى لم يُعرف الكتاب** — نصٌّ قائم خيرٌ من فراغ.
it('يسقط إلى التخريج المحفوظ حين لا يُعرف الكتاب', function (): void {
    $evidence = new RenderedEvidence(
        kind: 'hadith',
        text: 'نصّ',
        takhrij: 'رواه أحمد في المسند',
        grade: 'hasan',
    );

    expect($evidence->citation(Locale::En))->toBe('رواه أحمد في المسند — good');
});

/*
 * ── شريطُ لغات الملخّص — T-134 ──────────────────────────────────────────
 *
 * ★ **وننشر بأربع لغاتٍ منذ T-38 ولا رابطَ يصل صفحةً بأختها.** فالعربيةُ
 * والإنجليزيةُ جزيرتان، ومن بلغ إحداهما لا يعلم بالأخرى — والجهةُ دفعت
 * ثمنَ ترجمةٍ لا يجدها قارئُها.
 *
 * **والحدُّ الحاكم أنّ القالب لا يُمسّ**: روابطُ `<a>` محضة بلا سطر
 * JavaScript، وورقتُها في `_identity` — على سنّة شاهدةِ العدّ في T-31.
 */

/** يُقيّد مخرَجَ صفحةٍ منشورةً بلغةٍ ورابط. */
function publishedPage(SummaryJob $job, Locale $locale, string $url): Output
{
    return Output::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'type' => OutputType::Page->value,
        'format' => OutputType::Page->format()->value,
        'locale' => $locale->value,
        'storage_path' => "tenant-a/anuan/{$locale->value}/index.html",
        'public_url' => $url,
        'renderer_version' => '1.0.0',
    ]);
}

it('لا يرسم شريط لغات لملخّصٍ بلغةٍ واحدة', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');

    $html = renderPage($this->job->fresh());

    /*
     * **والمفحوصُ الوسمُ لا اسمُ الصنف**: `_identity` تحمل ورقةَ الشريط
     * دائماً، فاسمُه في `<style>` في كلّ صفحة. والمقصودُ أن لا يُرسَم.
     */
    expect($html)->not->toContain('<nav class="locale-strip"')
        ->and($html)->not->toContain('rel="alternate"');
});

it('يصل كلَّ لغةٍ بأختيها ولا يصلها بنفسها', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');
    publishedPage($this->job, Locale::Tr, 'https://khulasat.test/anuan/tr');

    $arabic = renderPage($this->job->fresh());

    expect($arabic)->toContain('locale-strip')
        ->and($arabic)->toContain('https://khulasat.test/anuan/en')
        ->and($arabic)->toContain('https://khulasat.test/anuan/tr')
        // **ولا رابطَ لنفسها**: صفحةٌ تُحيل إلى نفسها تُقرأ عطلاً.
        ->and($arabic)->not->toContain('href="https://khulasat.test/anuan"');

    // وأسماءُ اللغات بألسنتها لا مترجَمةً — فمن يبحث عن لغته يعرفها بحرفها.
    expect($arabic)->toContain('English')->and($arabic)->toContain('Türkçe');

    // والإنجليزيةُ تُحيل إلى العربية والتركية — والعنوانُ بلسانها.
    $english = renderTranslated($this->job->fresh(), Locale::En);

    expect($english)->toContain('https://khulasat.test/anuan')
        ->and($english)->toContain('https://khulasat.test/anuan/tr')
        // **وعنوانُ الغرض في `aria-label` لا في نصٍّ مرئيّ** — T-139.
        ->and($english)->toContain('aria-label="In another language"')
        ->and($english)->not->toContain('href="https://khulasat.test/anuan/en"');
});

it('لا يضع في الشريط نصّاً لا يُنقر', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');

    $html = renderPage($this->job->fresh());

    preg_match('/<nav class="locale-strip".*?<\/nav>/s', $html, $strip);

    /*
     * ★ **بلاغُ مالك المنتج، T-139**: «(in another language) section… it's not
     * clickable and don't show anything». فنصٌّ يجاور روابطَ يُقرأ رأسَ قائمةٍ
     * تُفتح — فيُضغط ولا شيء يحدث.
     *
     * **والمقيسُ النصُّ المرئيُّ لا كلُّ ورود**: العنوانُ باقٍ في
     * `aria-label` على `<nav>` بالحكم أعلاه، فيُنزَع الوسمُ وسمتُه ثمّ
     * يُقرأ ما بقي — فلا يخفق الحارسُ على ما أُريد بقاؤه.
     */
    $visible = trim(strip_tags(preg_replace('/<nav\b[^>]*>/', '', $strip[0]) ?? ''));
    $visible = trim(preg_replace('/\s+/u', ' ', $visible) ?? '');

    expect($visible)->toBe('English');
});

it('يبني الروابط على ما نُشر فعلاً لا على ما طُلب', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');

    /*
     * لغةٌ قُصدت وأخفق نشرُها: صفُّها قائمٌ و`storage_path` فارغ — وهو حالُ
     * ما تلتقطه `secondaryLocales` وتمضي (T-38). **ورابطُها ٤٠٤ في صفحة
     * جهة**، وهو أسوأ من غيابه.
     */
    Output::query()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->job->tenant_id,
        'type' => OutputType::Page->value,
        'format' => OutputType::Page->format()->value,
        'locale' => Locale::Tr->value,
        'storage_path' => null,
        'public_url' => null,
        'renderer_version' => '1.0.0',
    ]);

    $html = renderPage($this->job->fresh());

    expect($html)->toContain('https://khulasat.test/anuan/en')
        ->and($html)->not->toContain('Türkçe');
});

it('يكتب hreflang في الرأس لكلّ منشورة', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');

    $html = renderPage($this->job->fresh());

    expect($html)->toContain('<link rel="alternate" hreflang="en" href="https://khulasat.test/anuan/en">')
        // ولا `hreflang` لنفسها: الوسمُ للبديل لا للحاضر.
        ->and($html)->not->toContain('hreflang="ar"');
});

it('لا يضع شريطاً في المعاينة ولا في الملفّ المنزَّل', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');

    /*
     * `withoutBeacon` تُفرّغ `summaryJobId` — T-31. **والشريطُ يغيب معها
     * بالعلّة نفسها**: روابطُ المعاينة تُشير إلى صفحاتٍ قد لا توجد بعد،
     * ولشاشة المعاينة مبدّلُها الخاصّ من T-84.
     */
    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($this->job->fresh())->withoutBeacon(), BrandKit::forTenant($this->tenant))
        ->contents;

    expect($html)->not->toContain('<nav class="locale-strip"');
});

it('يبني الشريط ببنيةٍ محضة بلا سطر JavaScript', function (): void {
    publishedPage($this->job, Locale::Ar, 'https://khulasat.test/anuan');
    publishedPage($this->job, Locale::En, 'https://khulasat.test/anuan/en');

    $html = renderPage($this->job->fresh());

    // ★ ما بين وسم الشريط وإغلاقه: روابطٌ وقوائم، ولا `on*=` ولا شيفرة.
    expect($html)->toMatch('/<nav class="locale-strip".*?<\/nav>/s');

    preg_match('/<nav class="locale-strip".*?<\/nav>/s', $html, $strip);

    expect($strip[0])->not->toContain('<script')
        ->and($strip[0])->not->toContain('onclick')
        ->and($strip[0])->not->toContain('javascript:')
        // و`lang` على كلّ رابط: قارئُ الشاشة ينطق «Türkçe» تركيةً.
        ->and($strip[0])->toContain('lang="en"');
});
