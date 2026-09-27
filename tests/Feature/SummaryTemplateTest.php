<?php

declare(strict_types=1);

use App\Enums\SummaryTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedEvidence;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * قوالب المخرَج — T-45.
 *
 * **والمقياس الحاكم هنا أنّ القالب ورقةُ أنماطٍ لا بنيةٌ ثانية**: فأيّ
 * قالبٍ يجب أن يُخرج كتل المتن كلَّها كما هي، وإلّا سقطت كتلةٌ في قالبٍ
 * دون قالب — وهو عطبٌ لا يُرى إلّا بعد النشر.
 */

function sample(): ContentObject
{
    return new ContentObject(
        structure: ['title_ar' => 'درس'],
        evidence: [],
        majlis: ['title' => 'درس', 'sheikh' => 'الملقي', 'weekday' => 'الجمعة'],
        bodyHtml: '<section><div class="sec-head"><span class="mark"></span><h2>محور</h2></div>'
            .'<div class="sacred"><p class="text">شاهد</p><span class="src">مصدره</span></div>'
            .'<div class="trio"><article class="imam"><h3>بطاقة</h3><p>متنها</p></article></div>'
            .'</section>',
    );
}

function renderWith(SummaryTemplate $template): string
{
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => $template->value]]);

    return app(PageRenderer::class)->render(sample(), BrandKit::forTenant($tenant))->contents;
}

it('يرسم كلَّ قالبٍ بورقة أنماطه', function (SummaryTemplate $template): void {
    $html = renderWith($template);

    // البنية واحدة: الكتل نفسها في القوالب كلّها.
    expect($html)->toContain('class="sacred"')
        ->and($html)->toContain('class="text"')
        ->and($html)->toContain('class="imam"')
        // وورقة الأنماط موجودة: صفحةٌ بلا CSS تخرج نصّاً عارياً.
        ->and($html)->toContain('.sacred{');
})->with(SummaryTemplate::cases());

it('يُنسّق كلُّ قالبٍ كلَّ صنفٍ يقبله المنقّي', function (SummaryTemplate $template): void {
    $css = app('view')->make($template->styleView())->render();

    /*
     * **قائمة سماح المنقّي هي العقد**: ما يمرّ منه يجب أن يجد نمطاً، وإلّا
     * خرجت الكتلة بلا تنسيق **صامتةً** — لا خطأ ولا تحذير، والصفحة تبدو
     * رديئةً بلا سببٍ ظاهر.
     */
    $mustStyle = [
        'sacred', 'text', 'src', 'ayah-hero', 'ayah-no', 'imam', 'trio',
        'compare', 'axis-card', 'axis-q', 'pair', 'side', 'tag', 'quad',
        'pillar', 'pillar-title', 'step', 'num', 'paths', 'path', 'node',
        'night', 'checks', 'closing', 'sec-head', 'lead', 'muted',
    ];

    foreach ($mustStyle as $class) {
        expect($css)->toContain('.'.$class);
    }
})->with(SummaryTemplate::cases());

it('يسقط إلى الشرعيّ عند قالبٍ لا يُعرف', function (): void {
    // جهةٌ قديمة بلا قالب، وأخرى بمفتاحٍ محرَّف: كلتاهما تُرسم ولا تنكسر.
    expect(SummaryTemplate::parse(null))->toBe(SummaryTemplate::Classic)
        ->and(SummaryTemplate::parse('لا-قالب-بهذا-الاسم'))->toBe(SummaryTemplate::Classic)
        ->and(SummaryTemplate::parse(42))->toBe(SummaryTemplate::Classic);
});

it('لا يُبدّل مخرَج الشرعيّ عمّا كان', function (): void {
    /*
     * ★ **الحارس الأهمّ في هذا الملفّ** — CLAUDE.md §2 القاعدة الأولى.
     * `classic` هو قالب `khulasah.skill` حرفاً بحرف، وT-45 تُضيف إلى جانبه
     * لا فوقه. فلو أشار `classic` يوماً إلى ورقةٍ أخرى لتبدّل المنشورُ كلُّه
     * بلا أن يطلب أحد.
     */
    expect(SummaryTemplate::Classic->styleView())->toBe('summary.partials._style');
});

it('يعرض القوالب الستّة في شاشة الهوية', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'journal']]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Brand')
            ->where('tenant.template', 'journal')
            ->has('templates', 6)
            // الاسم والوصف عربيّان من `lang/ar/templates.php`، لا مفاتيح خام.
            ->where('templates.0.name', 'الشرعي')
            ->has('templates.0.description')
        );
});

it('يحفظ القالب المختار في هوية الجهة', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)
        ->post('/panel/settings/brand', [
            'name_ar' => $tenant->name_ar,
            'palette' => 'emerald',
            'template' => SummaryTemplate::Journal->value,
        ])
        ->assertRedirect();

    expect($tenant->fresh()->brand_kit['template'])->toBe('journal');
});

it('يرفض قالباً خارج القائمة ولا يحفظه', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'modern']]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)
        ->post('/panel/settings/brand', [
            'name_ar' => $tenant->name_ar,
            'palette' => 'emerald',
            'template' => 'قالبٌ مخترَع',
        ])
        ->assertSessionHasErrors('template');

    expect($tenant->fresh()->brand_kit['template'])->toBe('modern');
});

/*
 * ══ حارس مفردات T-46 ══
 *
 * **العطب الذي يحرسه هذا:** صنفٌ يُخرجه `BodyBlocks` ولا يُنسّقه قالبٌ يخرج
 * نصّاً عارياً في صفحةٍ منشورة — بلا خطأ ولا سجلّ. وقد وقع أربع مرّات في
 * هذا المشروع (`article` · `note` · `closing` · `label`/`input`)، ولم
 * يُكتشف إلّا بحارسٍ آليّ. فالقوالبُ الثلاثة تُنسّق كلَّ كتلةٍ أو لا تُقبل.
 */
it('يُنسّق كلُّ قالبٍ مفردات كتل T-46 وT-72 وT-76 كلَّها', function (): void {
    $classes = [
        'data', 'listing', 'bullets', 'numbered',
        'figures-wrap', 'figures', 'figure', 'fig-num', 'fig-label',
        'timeline', 'event', 'when', 'what',
        'tree', 'root', 'branches', 'branch', 'leaf',
        // ★ T-72 — هرمٌ وعجلة، ونفسُ الحارس يسري عليهما.
        'pyramid', 'tiers', 'tier',
        'gauge', 'ring', 'reading', 'poles', 'pole', 'fill-0', 'fill-100',
        // ★ T-76 — تعريف مصطلح وسؤال وجواب وتنبيه وتصحيح شبهة.
        'gloss', 'gloss-head', 'gloss-term', 'gloss-body', 'gloss-label', 'gloss-text',
        'qa', 'qa-item', 'qa-q', 'qa-a',
        'cnote', 'cn-benefit', 'cn-warning', 'cn-subtle', 'cn-title', 'cn-text',
        'mfix', 'mfix-claim', 'mfix-fix', 'mfix-tag',
    ];

    foreach (SummaryTemplate::cases() as $template) {
        $css = (string) file_get_contents(
            resource_path('views/'.str_replace('.', '/', $template->blocksView()).'.blade.php'),
        );

        foreach ($classes as $class) {
            expect($css)->toContain('.'.$class);
        }

        // ولا بدّ من قاعدةٍ للجدول نفسه: الوسوم مسموحةٌ منذ البداية ولم
        // يكن يُنسّقها قالبٌ واحد — فكانت تخرج بأنماط المتصفّح الافتراضية.
        expect($css)->toContain('.data table')
            // الجوال والطباعة شرطٌ في كلّ قالب، لا تحسيناً يُؤجَّل.
            ->toContain('@media (max-width:640px)')
            ->toContain('@media print')
            // ولا لونَ صريحٍ خارج الطباعة: يكسر لوحةَ الجهة.
            ->toContain('var(--')
            // ★ T-75 — القائمة المرقّمة عربيةٌ هنديّة كسائر أرقام الصفحة،
            // لا لاتينية كما كانت في القوالب الستّة كلِّها.
            ->toContain('list-style-type:arabic-indic');
    }
});

// `classic` يبقى مشيراً إلى ورقة المهارة — و**مفرداتُ T-46 في ورقةٍ ثانية**
// حتى لا يُمسّ `_style.blade.php` بحرف (§2 القاعدة الأولى).
it('يفصل ورقة الكتل عن ورقة القالب', function (): void {
    expect(SummaryTemplate::Classic->styleView())->toBe('summary.partials._style')
        ->and(SummaryTemplate::Classic->blocksView())->toBe('summary.blocks.classic')
        ->and(SummaryTemplate::Modern->blocksView())->toBe('summary.blocks.modern')
        ->and(SummaryTemplate::Journal->blocksView())->toBe('summary.blocks.journal');
});

/*
 * ══ حرّاس T-49 — القوالب ببنيةٍ خاصّة ══
 */

/** عيّنةٌ بشواهد — يحتاجها ترتيبُ التخريج، و`sample()` بلا شاهد. */
function sampleWithEvidence(): ContentObject
{
    return new ContentObject(
        structure: ['title_ar' => 'درس'],
        evidence: [new RenderedEvidence(kind: 'ayah', text: 'شاهد', sourceRef: 'النحل: ٩٧')],
        majlis: ['title' => 'درس', 'sheikh' => 'الملقي', 'weekday' => 'الجمعة'],
        bodyHtml: '<section><h2>محور</h2><p>متن</p></section>',
    );
}

function renderEvidenced(SummaryTemplate $template): string
{
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => $template->value]]);

    return app(PageRenderer::class)->render(sampleWithEvidence(), BrandKit::forTenant($tenant))->contents;
}

// **العطب الذي يحرسه هذا:** قالبٌ يُسقط كتلةً أنتجها الخطّ يتّخذ قرارَ
// محتوًى لا قرارَ عرض — وذلك لصاحب المحتوى لا للقالب. وقرار مالك المنتج
// في ٩ أيلول ٢٠٢٦ صريح: «المحتوى كامل».
it('لا يُسقط أيُّ قالبٍ كتلةً من المتن', function (): void {
    $counts = [];

    foreach (SummaryTemplate::cases() as $template) {
        $html = renderWith($template);

        foreach (['sacred', 'trio', 'imam', 'text'] as $block) {
            $counts[$block][$template->value] = substr_count($html, 'class="'.$block.'"');
        }
    }

    foreach ($counts as $block => $perTemplate) {
        expect(array_unique($perTemplate))->toHaveCount(1, "الكتلة {$block} تختلف بين القوالب");
    }
});

// **البنية تختلف فعلاً، لا لونُها فقط** — وهذا اعتراض مالك المنتج الذي
// أنشأ T-49: «القوالب الثلاثة ليست ثلاثة قوالب».
it('يملك كلُّ قالبٍ من T-49 بنيةَ صفحته', function (): void {
    expect(SummaryTemplate::Classic->layoutView())->toBe('summary.layout')
        ->and(SummaryTemplate::Modern->layoutView())->toBe('summary.layout')
        ->and(SummaryTemplate::Journal->layoutView())->toBe('summary.layout')
        ->and(SummaryTemplate::Lesson->layoutView())->toBe('summary.templates.lesson')
        ->and(SummaryTemplate::Brief->layoutView())->toBe('summary.templates.brief')
        ->and(SummaryTemplate::Research->layoutView())->toBe('summary.templates.research');

    // السرلوح المذهّب لقالب المهارة وحدَه — وظهورُه في «الموجزة» كان
    // العطبَ بعينه الذي أنشأ هذه المهمّة.
    foreach ([SummaryTemplate::Lesson, SummaryTemplate::Brief, SummaryTemplate::Research] as $template) {
        expect(renderWith($template))->not->toContain('class="crest"');
    }

    // والثلاثةُ الأولى لم تتبدّل: سرلوحُها كما كان.
    expect(renderWith(SummaryTemplate::Classic))->toContain('class="crest"');
});

// ★ **المواضعُ آخرَ الصفحة في القوالب كلّها** — قرار مالك المنتج، ١١ أيلول
// ٢٠٢٦ (T-99). وكان البحثيُّ يُقدّمها على المتن (T-49)، فأُلغي ذلك بالقرار نفسه.
it('يجعل المواضع آخرَ الصفحة في كلّ قالب، والبحثيُّ منها', function (SummaryTemplate $template): void {
    $html = renderEvidenced($template);

    $sources = strpos($html, '<div class="sources"');

    expect($sources)->not->toBeFalse()
        ->and($sources)->toBeGreaterThan(strpos($html, '<footer class="colophon"'))
        ->and($sources)->toBeGreaterThan(strpos($html, '<div class="actions"'));
})->with(SummaryTemplate::cases());
