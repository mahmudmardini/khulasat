<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Render\BodyBlocks;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;

/*
 * إطارُ الصفحة بلسانها ومحاذاتُها — T-87، بلاغُ مالك المنتج بلقطة:
 * «يُظنّ» و«والصواب» عربيّتان في الصفحة الإنجليزية، ورأسُ الجدول مدفوعٌ
 * يميناً فوق خلايا يسارية.
 *
 * ★ **وهي علّةُ T-69 في مواضعَ لم تُفتَّش** — فالحارسُ هنا لا يعدّ وسوماً
 * بعينها، بل يرسم الصفحة كلَّها ولا يقبل حرفاً عربياً خارج ما وُسم عربياً.
 */

/** متنٌ إنجليزيٌّ بالكتل ذوات الوسوم الثابتة — كما تُخرجه مرحلةُ الترجمة. */
function englishChromeBody(): array
{
    return ['sections' => [[
        'heading' => 'Politics and covenants',
        'blocks' => [
            ['type' => 'term_gloss', 'title' => 'Aqwamiyyah', 'linguistic' => 'Being most upright.', 'technical' => 'The Qur’an’s primacy.'],
            ['type' => 'misconception_fix', 'claim' => 'Politics is the art of interests.', 'correction' => 'That is opportunistic logic.'],
            ['type' => 'qa_pair', 'items' => [['q' => 'Why?', 'a' => 'Because it guides.']]],
            ['type' => 'table', 'columns' => ['Ruling', 'Domain'], 'rows' => [['Shura', 'Politics']]],
        ],
    ]]];
}

function chromePage(Locale $locale, SummaryTemplate $template = SummaryTemplate::Classic): string
{
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => $template->value]]);
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id, 'speaker_name' => 'ملقي الاختبار']);
    $job = SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => $lecture->id,
        'structure_json' => ['title_ar' => 'عنوان آخر'],
        'body_html' => BodyBlocks::toHtml(englishChromeBody()),
    ]);

    if (! $locale->isSource()) {
        SummaryTranslation::query()->create([
            'summary_job_id' => $job->id,
            'tenant_id' => $tenant->id,
            'locale' => $locale->value,
            'title' => 'The Qur’an’s Supreme Rectitude',
            'body_html' => BodyBlocks::toHtml(englishChromeBody(), $locale),
        ]);
    }

    return app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->fresh(), $locale), BrandKit::forTenant($tenant))
        ->contents;
}

/**
 * النصُّ الظاهر في المتن خارج ما وُسم عربياً — والموسومُ عربياً لفظُ شاهدٍ
 * أو اسمُ علمٍ أو تاريخ، وهو عربيٌّ عمداً في كلّ لغة (T-66).
 */
function textOutsideArabic(string $html): string
{
    $html = (string) preg_replace('#<(style|script)\b.*?</\1>#s', '', $html);

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();

    foreach (iterator_to_array((new DOMXPath($dom))->query('//*[@lang="ar"]') ?: []) as $node) {
        $node->parentNode?->removeChild($node);
    }

    return (string) $dom->getElementsByTagName('body')->item(0)?->textContent;
}

it('يكتب وسوم الكتل بلسان الصفحة', function (): void {
    $html = chromePage(Locale::En);

    expect($html)->toContain('<span class="mfix-tag">Misconception</span>')
        ->toContain('<span class="mfix-tag">Correction</span>')
        ->toContain('<span class="gloss-label">Linguistically</span>')
        ->toContain('<span class="gloss-label">Technically</span>')
        ->toContain('Share the summary')
        ->toContain('html[lang="en"] .qa-q::before{content:"Q" counter(qa)}');
});

it('لا يترك في الصفحة غير العربية حرفاً عربياً خارج ما وُسم عربياً', function (SummaryTemplate $template): void {
    preg_match_all('/[ء-ي]+/u', textOutsideArabic(chromePage(Locale::En, $template)), $arabic);

    expect($arabic[0])->toBe([]);
})->with(SummaryTemplate::cases());

// **والصفحةُ العربيةُ لا يتبدّل منها حرف** — الوسومُ هي هي.
it('يُبقي الصفحة العربية على وسومها', function (): void {
    $html = chromePage(Locale::Ar);

    expect($html)->toContain('<span class="mfix-tag">يُظنّ</span>')
        ->toContain('<span class="mfix-tag">والصواب</span>')
        ->toContain('<span class="gloss-label">اصطلاحاً</span>')
        ->toContain('مشاركة الملخّص')
        ->toContain('ألقاها');
});

/*
 * ★ **ولا لقبَ يُضاف بلا طلب** — T-102، بلاغُ مالك المنتج بلقطة: سطرُ
 * النسبة كان «محاضرةٌ للشيخ فلان»، فمن لم يكن شيخاً لُقّب به في صفحةٍ
 * تُنشر باسمه. واللقبُ يُكتب في اسم الملقي إن أراده صاحبُه.
 */
it('لا يُلقّب الملقي في سطر النسبة', function (Locale $locale): void {
    $html = chromePage($locale);

    expect($html)->not->toContain('للشيخ')
        ->not->toContain('Sheikh')
        ->not->toContain('шейх');
})->with([Locale::Ar, Locale::En, Locale::Tr, Locale::Ru]);

/*
 * ★ **المحاذاةُ منطقيّةٌ لا فيزيائية** — `right` يصحّ في العربية وحدها،
 * و`start` يمينٌ فيها ويسارٌ في اللاتينية. والتعليقاتُ خارج الفحص: فيها
 * ذكرُ `text-align:right` شرحاً لما أُصلح.
 */
it('لا يحاذي بجهةٍ فيزيائية في أوراق الكتل', function (): void {
    foreach (glob(resource_path('views/summary/blocks/*.blade.php')) ?: [] as $file) {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($file));

        expect($css)->not->toMatch('/text-align:(right|left)/', basename($file));
    }
});
