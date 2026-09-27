<?php

declare(strict_types=1);

use App\Actions\Render\RenderOutput;
use App\Actions\Stages\TranslateSummary;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\I18n\BodyStrings;
use App\Support\Publish\Paths;
use App\Support\Render\BodyBlocks;
use App\Support\Render\ContentObject;

/*
 * ترجمةُ الملخّص — T-38.
 *
 * ★★ **والمقياس الحاكم أنّ لفظ الشاهد لا يتبدّل.** «الشاهد يبقى بلفظه
 * العربي، ولا يُترجَم نصُّه ولا يُستبدل به» — فلو انكسر هذا لخرج شاهدٌ
 * بلفظٍ لم يقله المتحدّث في صفحةٍ منشورة باسم الجهة.
 */

it('لا يُبدّل لفظ الشاهد مهما ردّ النموذج', function (): void {
    $job = SummaryJob::factory()->create([
        'body_json' => [
            'sections' => [[
                'heading' => 'عنوان',
                'blocks' => [
                    ['type' => 'evidence', 'kind' => 'hadith', 'text' => 'إنّما الأعمال بالنيّات', 'source' => 'البخاري'],
                    ['type' => 'paragraph', 'text' => 'فقرة'],
                ],
            ]],
            'closing' => 'ختام',
        ],
    ]);

    $translation = app(TranslateSummary::class)->handle($job, Locale::Tr);

    $blocks = $translation->body_json['sections'][0]['blocks'];

    // ★★ اللفظ العربي كما هو — والبوّابةُ الوهمية تردّ ما تردّ.
    expect($blocks[0]['text'])->toBe('إنّما الأعمال بالنيّات')
        ->and($blocks[0]['kind'])->toBe('hadith')
        ->and($blocks[0]['type'])->toBe('evidence');
});

it('يرفض ترجمة لغة المصدر', function (): void {
    $job = SummaryJob::factory()->create(['body_json' => ['sections' => []]]);

    expect(fn () => app(TranslateSummary::class)->handle($job, Locale::Ar))
        ->toThrow(InvalidArgumentException::class);
});

it('يحفظ ترجمةً واحدة لكل لغة ولا يكرّرها', function (): void {
    $job = SummaryJob::factory()->create([
        'body_json' => ['sections' => [['heading' => 'عنوان', 'blocks' => [['type' => 'paragraph', 'text' => 'متن']]]]],
    ]);

    app(TranslateSummary::class)->handle($job, Locale::Tr);
    app(TranslateSummary::class)->handle($job, Locale::Tr);

    expect(SummaryTranslation::query()->where('summary_job_id', $job->id)->count())->toBe(1);
});

// **لغةُ المصدر بلا مقطع** — فكلُّ رابطٍ منشور قبل هذه المهمّة يبقى يعمل.
it('يُبقي مسار العربية كما كان ويُفرد مقطعاً لغيرها', function (): void {
    expect(Paths::forOutput('tenant-a', 'slug', OutputType::Page))->toBe('tenant-a/slug/index.html')
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::Ar))->toBe('tenant-a/slug/index.html')
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::Tr))->toBe('tenant-a/slug/tr/index.html')
        ->and(Paths::publicUrl('tenant-a', 'slug', OutputType::Page, Locale::Ar))
        ->toBe(Paths::publicUrl('tenant-a', 'slug', OutputType::Page))
        ->and(Paths::publicUrl('tenant-a', 'slug', OutputType::Page, Locale::Ru))->toEndWith('/slug/ru');
});

// **سقوطٌ إلى الأصل لا صفحةٌ فارغة**: القارئ يجد ملخّصاً كاملاً بلغةٍ غير
// التي طلب، لا لا شيء.
it('يسقط إلى العربية حين لا ترجمة', function (): void {
    $job = SummaryJob::factory()->create(['body_html' => '<p>عربي</p>']);

    $content = ContentObject::fromJob($job->fresh(), Locale::Tr);

    expect($content->locale)->toBe(Locale::Ar)
        ->and($content->bodyHtml)->toBe('<p>عربي</p>');
});

// ★★ **لغةُ المصدر ليست لغةَ نشرٍ مفروضة** — T-51. والتحقّق يجري على
// العربيّ في الحالين، ونشرُه قرارُ صاحب المحتوى.
it('يقرأ لغات الجهة كما اختارتها', function (): void {
    expect(Tenant::factory()->create(['brand_kit' => ['locales' => ['tr']]])->outputLocales())
        ->toBe([Locale::Tr])
        // ومن لم يختر يُنشر له بلغة المصدر — لا ملخّصَ بلا لغة.
        ->and(Tenant::factory()->create(['brand_kit' => []])->outputLocales())
        ->toBe([Locale::Ar]);
});

/*
 * ══ لغاتُ النشر تتبع الاختيار — T-51 ══
 *
 * ★★ **طلبُ مالك المنتج، ٩ أيلول ٢٠٢٦:** «لمّا بده ملخّص بالعربي يطلع بس
 * بالعربي، ولمّا بده بس بالإنجليزي يطلع بس بالإنجليزي، ولمّا بده الاثنين
 * يطلع له ملخّصين».
 *
 * وكان المنتج يفرض العربية دائماً، خلطاً بين **لغة المصدر** — داخلية،
 * عليها يجري التحقّق — و**لغات النشر**، وهي اختيارُ صاحب المحتوى.
 */

it('ينشر بالإنجليزية وحدها حين تُختار وحدها', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['locales' => ['en']]]);

    expect($tenant->outputLocales())->toBe([Locale::En])
        // ★ والجذرُ إنجليزيّ: لا يبقى جذرٌ فارغ ينتظر عربيّةً لم تُطلب.
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::En, Locale::En))
        ->toBe('tenant-a/slug/index.html');
});

it('يجعل الجذر للأولى ومقطعاً لما بعدها', function (): void {
    // عربيةٌ وإنجليزية: العربيةُ أولى بترتيب الحالات، فما نُشر قبلُ يبقى مكانه.
    expect(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::Ar, Locale::Ar))
        ->toBe('tenant-a/slug/index.html')
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::En, Locale::Ar))
        ->toBe('tenant-a/slug/en/index.html')
        // وإنجليزيةٌ وتركية بلا عربية: الإنجليزيةُ على الجذر والتركيةُ بمقطعها.
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Page, Locale::Tr, Locale::En))
        ->toBe('tenant-a/slug/tr/index.html');
});

it('ينشر ملخّصين حين تُختار لغتان', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['locales' => ['ar', 'en']]]);

    $locales = $tenant->outputLocales();

    expect($locales)->toBe([Locale::Ar, Locale::En])
        ->and(Locale::primaryOf($locales))->toBe(Locale::Ar);

    // ولكلٍّ مسارُه، فلا يدهس أحدُهما الآخر.
    $paths = array_map(
        static fn (Locale $l): string => Paths::forOutput('tenant-a', 'slug', OutputType::Page, $l, Locale::Ar),
        $locales,
    );

    expect($paths)->toBe(['tenant-a/slug/index.html', 'tenant-a/slug/en/index.html'])
        ->and(array_unique($paths))->toHaveCount(2);
});

/*
 * ═══ T-63 — لغةٌ تُترجَم ويُدفع ثمنُها ثمّ تضيع ═══
 *
 * بلاغُ تشغيلٍ حقيقيّ (المهمّة ٣٤، لغاتُها `["en","tr"]`): خرجت الإنجليزيةُ
 * على الجذر، **وضاعت التركية بعد أن تُرجمت**. والسبب أنّ `RenderAndPublish`
 * يرسم الأولى ثمّ يترجم الثانية، فتُحمَّل علاقةُ الترجمات **قبل** أن تُكتب
 * الثانية — فلا تُرى، فيسقط العارضُ إلى العربية صامتاً.
 *
 * ★ **ولا يظهر إلّا حين لا تكون العربيةُ الأولى**: فالأولى إن كانت المصدر
 * لم تُقرأ العلاقةُ أصلاً، فتُقرأ طازجةً لما بعدها. ولذلك عملت مهمّةٌ
 * عربيّتُها أولى وسقطت التي أُولاها إنجليزية.
 */
it('يرسم اللغة المطلوبة ولو تُرجمت بعد تحميل العلاقة', function (): void {
    $job = SummaryJob::factory()->create([
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">فقرة عربية.</p>',
    ]);

    SummaryTranslation::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'locale' => Locale::En->value,
        'title' => 'The Lost Treasure',
        'body_html' => '<p class="lead">An English paragraph.</p>',
    ]);

    // ١. تُرسم الأولى (الإنجليزية) — وهنا تُحمَّل العلاقةُ وتُخزَّن.
    expect(ContentObject::fromJob($job, Locale::En)->locale)->toBe(Locale::En);

    // ٢. ثمّ تُكتب التركية، كما يفعل `TranslateSummary` بعدها.
    SummaryTranslation::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'locale' => Locale::Tr->value,
        'title' => 'Kayıp Hazine',
        'body_html' => '<p class="lead">Türkçe bir paragraf.</p>',
    ]);

    // ٣. **وهنا كان يسقط إلى العربية** فيُنشر ملفٌّ عربيٌّ باسم التركية.
    $content = ContentObject::fromJob($job, Locale::Tr);

    expect($content->locale)->toBe(Locale::Tr)
        ->and($content->bodyHtml)->toContain('Türkçe');
});

// **والصفُّ يُقيَّد باللغة المطلوبة لا بالمرسومة.** فصفٌّ بلغةٍ غير التي
// طُلبت يجعل الناشرَ يبحث عن `page:tr` فلا يجده، فيرمي، فيُلتقط ويُسجَّل —
// وتبدو المهمّةُ ناجحةً وقد ضاعت لغةٌ دُفع ثمنُها.
it('لا يقيّد مخرَجاً بلغةٍ غير التي طُلبت', function (): void {
    $job = SummaryJob::factory()->create([
        'structure_json' => ['title_ar' => 'عنوان'],
        'body_html' => '<p class="lead">فقرة.</p>',
    ]);

    // لا ترجمةَ تركية البتّة: طلبُ رسمِها إخفاقٌ يُعلَن، لا صفٌّ عربيّ يُسجَّل.
    expect(fn (): mixed => app(RenderOutput::class)
        ->handle($job, app(PageRenderer::class), Locale::Tr))
        ->toThrow(RuntimeException::class);

    expect(Output::query()->where('summary_job_id', $job->id)->count())->toBe(0);
});

/*
 * ═══ T-67 — معنى الحديث يُعرَض تحته موسوماً ═══
 *
 * **بلاغُ مالك المنتج:** الصفحةُ الإنجليزية تعرض الحديث عربياً عارياً.
 * والمعاني كانت تُترجَم وتُحفظ في `meanings` **ولا يقرؤها أحد** — عملٌ
 * يُنجَز ويُدفع ثمنُه ثمّ لا يُعرَض.
 */

/** كتلُ متنٍ فيها شاهدُ حديث، بالشكل الذي تُخرجه المرحلة ٥. */
function bodyWithHadith(): array
{
    return ['sections' => [[
        'heading' => 'السكينة',
        'blocks' => [[
            'type' => 'evidence',
            'kind' => 'hadith',
            'text' => 'سَدِّدوا وقارِبوا',
            'source' => 'رواه البخاري',
        ]],
    ]]];
}

it('يعرض معنى الحديث موسوماً تحت لفظه في اللغة الأخرى', function (): void {
    $body = BodyStrings::withMeanings(bodyWithHadith(), [
        'sections.0.blocks.0.text' => 'Be moderate and steadfast.',
    ]);

    $html = BodyBlocks::toHtml($body, Locale::En);

    expect($html)
        // اللفظُ عربيٌّ باتّجاهه — T-38 وT-66.
        ->toContain('<p class="text hadith" lang="ar" dir="rtl">سَدِّدوا وقارِبوا</p>')
        // والمعنى تحته، بلغته واتّجاهها.
        ->toContain('lang="en" dir="ltr"')
        ->toContain('Be moderate and steadfast.')
        // ★★ **وموسوماً**: ترجمةٌ بلا وسمٍ تُقرأ حديثاً بلغةٍ أخرى، وذلك
        // نسبةُ لفظٍ إلى النبيّ ﷺ لم يقله.
        ->toContain('<em>Meaning:</em>');
});

it('يوسم المعنى بلسان كلّ لغة', function (): void {
    $body = BodyStrings::withMeanings(bodyWithHadith(), ['sections.0.blocks.0.text' => 'Dosdoğru olun.']);

    expect(BodyBlocks::toHtml($body, Locale::Tr))->toContain('<em>Anlamı:</em>')
        ->and(BodyBlocks::toHtml($body, Locale::Ru))->toContain('Dosdoğru olun.');
});

// **ولا يظهر في الصفحة العربية**: الأصلُ بلسانه، ولا معنى يُترجَم إليه.
it('لا يعرض معنًى في صفحة لغة المصدر', function (): void {
    $body = BodyStrings::withMeanings(bodyWithHadith(), ['sections.0.blocks.0.text' => 'Be moderate.']);

    expect(BodyBlocks::toHtml($body, Locale::Ar))->not->toContain('Be moderate.')
        ->and(BodyBlocks::toHtml($body))->not->toContain('ترجمة معنى');
});

// شاهدٌ بلا معنًى مترجَم يخرج عربياً وحده — بلا وسمٍ لا شيء تحته.
it('لا يترك موضعاً فارغاً حين لا معنى', function (): void {
    $html = BodyBlocks::toHtml(bodyWithHadith(), Locale::En);

    expect($html)->toContain('سَدِّدوا وقارِبوا')
        ->and($html)->not->toContain('Meaning');
});

it('لا يكتب معنًى فارغاً ولا يقع على مسارٍ لا وجود له', function (): void {
    $body = bodyWithHadith();

    expect(BodyStrings::withMeanings($body, ['sections.0.blocks.0.text' => '   ']))->toBe($body)
        ->and(BodyStrings::withMeanings($body, ['sections.9.blocks.9.text' => 'x']))->toBe($body)
        // ومفتاحٌ لا ينتهي بـ`.text` ليس مسارَ شاهد.
        ->and(BodyStrings::withMeanings($body, ['majlis.title' => 'x']))->toBe($body);
});
