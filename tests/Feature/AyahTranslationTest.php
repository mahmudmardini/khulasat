<?php

declare(strict_types=1);

use App\Actions\Stages\TranslateSummary;
use App\Console\Commands\SeedQuranTranslations;
use App\Enums\Locale;
use App\Models\EvidenceItem;
use App\Models\QuranAyah;
use App\Models\QuranTranslation;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Services\Render\PageRenderer;
use App\Support\Quran\AyahCitation;
use App\Support\Quran\AyahTranslations;
use App\Support\Render\BodyBlocks;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;

/*
 * ترجمةُ الآية المعتمدة تحت لفظها — T-80، البندُ الثاني الباقي من T-38.
 *
 * ★★ **ولا يترجمها نموذج**: تُقرأ من `quran_translations` المبذور من
 * ترجماتٍ معتمدةٍ منشورة. وكانت مبذورةً لا يعرضها أحد، فخرجت الآيةُ في
 * الصفحة الإنجليزية عربيةً عارية والحديثُ تحته معناه.
 */

/** لفظُ الشاهد كما يُحفظ في `matched_text`: مقوَّساً ومعلَّماً (T-56). */
function isra9Text(): string
{
    return '﴿إِنَّ هَـٰذَا ٱلْقُرْءَانَ يَهْدِى لِلَّتِى هِىَ أَقْوَمُ ۝٩﴾';
}

/**
 * مهمّةٌ فيها آيةٌ مثبَّتة وكتلتُها في المتن، والمصحفُ وترجمتُه مبذوران.
 *
 * @param  array<string, mixed>  $meta  ما يُضاف إلى `source_meta`
 * @param  string  $uthmani  لفظ الآية في المصحف — أطولُ من الكتلة إن كانت شذرة
 */
function isra9Job(array $meta = [], string $uthmani = 'إِنَّ هَـٰذَا ٱلْقُرْءَانَ يَهْدِى لِلَّتِى هِىَ أَقْوَمُ', ?string $blockText = null): SummaryJob
{
    $job = SummaryJob::factory()->create(['body_json' => ['sections' => [[
        'heading' => 'الهداية',
        'blocks' => [[
            'type' => 'evidence',
            'kind' => 'ayah',
            'text' => $blockText ?? isra9Text(),
            // هكذا صاغه نموذجُ الترجمة — ويُستبدل بالمبنيّ.
            'source' => 'Surah Al-Isra, verse 9',
        ]],
    ]]]]);

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'ayah',
        'matched_text' => isra9Text(),
        'source_ref' => 'سورة الإسراء، الآية ٩',
        'source_meta' => ['surah_number' => 17, 'ayah_number' => 9, 'is_fragment' => true, ...$meta],
    ]);

    QuranAyah::query()->updateOrCreate(['surah' => 17, 'ayah' => 9], [
        'surah_name_ar' => 'الإسراء',
        'text_uthmani' => $uthmani,
        'text_imlaei' => 'إن هذا القرآن يهدي للتي هي أقوم',
        'text_normalized' => 'ان هذا القران يهدي للتي هي اقوم',
    ]);

    QuranTranslation::query()->updateOrCreate(['surah' => 17, 'ayah' => 9, 'locale' => 'en'], [
        'translation_id' => 20,
        'text' => 'Indeed, this Qur’an guides to that which is most suitable',
    ]);

    return $job->fresh();
}

function englishBody(SummaryJob $job): string
{
    return BodyBlocks::toHtml(AyahTranslations::apply($job->body_json, $job, Locale::En), Locale::En);
}

it('يضع الترجمة المعتمدة تحت الآية موسومةً باسم مترجمها', function (): void {
    $html = englishBody(isra9Job());

    expect($html)
        // اللفظُ عربيٌّ باتّجاهه كما كان — T-38 وT-66.
        ->toContain('<p class="text" lang="ar" dir="rtl">﴿إِنَّ')
        // والترجمةُ تحته بلغتها واتّجاهها.
        ->toContain('<p class="src ayah-tr" lang="en" dir="ltr">Indeed, this Qur’an guides to that which is most suitable')
        // ★ **ولا وسمَ فوقها** — T-89: النسبةُ مرّةً في ذيل الصفحة لا تحت كلّ آية.
        ->not->toContain('Saheeh International');
});

/*
 * ★ **والنسبةُ لا تسقط، تنتقل** — T-89. سطرٌ واحد في ذيل الصفحة، ويغيب حين
 * لا ترجمةَ آيةٍ فيها.
 */
it('ينسب ترجمات الآيات إلى مترجمها مرّةً واحدة في ذيل الصفحة', function (): void {
    $job = isra9Job();

    $render = function (string $body) use ($job): string {
        SummaryTranslation::query()->updateOrCreate(
            ['summary_job_id' => $job->id, 'locale' => 'en'],
            ['tenant_id' => $job->tenant_id, 'title' => 'Guidance', 'body_html' => $body],
        );

        return app(PageRenderer::class)
            ->render(ContentObject::fromJob($job->fresh(), Locale::En), BrandKit::forTenant($job->tenant))
            ->contents;
    };

    $html = $render(englishBody($job));

    expect(substr_count($html, 'Saheeh International'))->toBe(1)
        ->and($html)->toContain('Translations of the meanings of the verses: Saheeh International.');

    // وصفحةٌ بلا ترجمة آيةٍ لا تنسب ما لم تعرض.
    expect($render('<p class="lead">English.</p>'))->not->toContain('Saheeh International');
});

/*
 * **والتخريجُ يُبنى من رقمَي الآية** — فهو هو في المتن وفي القائمة (T-69)،
 * ولا تتبدّل صياغتُه بين تشغيلين.
 */
it('يبني تخريج الآية من رقميها بلسان الصفحة', function (): void {
    $html = englishBody(isra9Job());

    expect($html)->toContain('<span class="src">Surah Al-Isra 17:9</span>')
        ->and($html)->not->toContain('Surah Al-Isra, verse 9');

    expect(AyahCitation::for(17, 9, null, Locale::Tr))->toBe('İsrâ Suresi 17:9')
        ->and(AyahCitation::for(17, 9, null, Locale::Ru))->toBe('Сура «Ночной перенос» 17:9')
        ->and(AyahCitation::for(17, 9, 10, Locale::En))->toBe('Surah Al-Isra 17:9–10')
        // والعربيةُ تخريجُها المحفوظ هو الأصل — لا يُبنى لها.
        ->and(AyahCitation::for(17, 9, null, Locale::Ar))->toBeNull()
        ->and(AyahCitation::for(115, 1, null, Locale::En))->toBeNull();
});

// **ولا يظهر في الصفحة العربية**: الأصلُ بلسانه، ولا ترجمةَ تُعرض فيه.
it('لا يمسّ صفحة لغة المصدر', function (): void {
    $job = isra9Job();

    expect(AyahTranslations::apply($job->body_json, $job, Locale::Ar))->toBe($job->body_json)
        ->and(BodyBlocks::toHtml($job->body_json, Locale::Ar))->not->toContain('Indeed');
});

/*
 * ★ **وما لم يُربط يخرج عربياً وحده** — ترجمةٌ مخمَّنةٌ تحت آيةٍ نسبةُ معنًى
 * إلى كتاب الله في غير موضعه.
 */
it('لا يخمّن ترجمةً لآيةٍ لم تُربط بشاهدٍ مثبَّت', function (): void {
    $html = englishBody(isra9Job(blockText: '﴿وَقُل رَّبِّ زِدْنِى عِلْمًا﴾'));

    expect($html)->not->toContain('Indeed')
        ->and($html)->not->toContain('ayah-tr')
        ->and($html)->toContain('Surah Al-Isra, verse 9');
});

// **ولفظٌ واحدٌ لآيتين مختلفتين لا يُخمَّن أيُّهما** — نظيرُ حارس T-73.
it('لا يربط لفظاً ملتبساً بين موضعين', function (): void {
    $job = isra9Job();

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'ayah',
        'matched_text' => isra9Text(),
        'source_meta' => ['surah_number' => 2, 'ayah_number' => 2],
    ]);

    expect(englishBody($job->fresh()))->not->toContain('Indeed');
});

// **والمتنُ يعرض بعضَ الآية**: فالترجمةُ تُقال للآية كلّها لا لما عُرض منها.
it('يقول إنّ الترجمة للآية كلّها حين يعرض المتن بعضها', function (): void {
    $html = englishBody(isra9Job(
        uthmani: 'إِنَّ هَـٰذَا ٱلْقُرْءَانَ يَهْدِى لِلَّتِى هِىَ أَقْوَمُ وَيُبَشِّرُ ٱلْمُؤْمِنِينَ',
    ));

    expect($html)->toContain('<p class="src ayah-tr" lang="en" dir="ltr"><em>Whole verse:</em> Indeed');
});

// **ولا نصفَ ترجمة**: آيتان موصولتان ترجمةُ إحداهما وحدها تُقرأ ترجمةً للاثنتين.
it('لا ينشر نصف ترجمة لآيتين موصولتين', function (): void {
    $html = englishBody(isra9Job(['ayah_number_end' => 10]));

    expect($html)->not->toContain('Indeed')
        // والتخريجُ يبقى مبنيّاً — لا يتوقّف على الترجمة.
        ->and($html)->toContain('Surah Al-Isra 17:9–10');
});

/*
 * **ومن طرفٍ إلى طرف**: مرحلةُ الترجمة تحفظ المتن بترجمة الآية — والبوّابةُ
 * الوهمية لا تبلغها الآيةُ أصلاً ({@see BodyStrings} لا تضع لفظ الشاهد في
 * الجدول)، فما يظهر تحتها من الجدول المبذور لا من نموذج.
 */
it('تحفظ مرحلة الترجمة المتن بترجمة الآية المعتمدة', function (): void {
    $translation = app(TranslateSummary::class)->handle(isra9Job(), Locale::En);

    expect($translation->body_html)->toContain('Indeed, this Qur’an guides')
        ->and($translation->body_html)->toContain('class="src ayah-tr"')
        ->and($translation->body_json['sections'][0]['blocks'][0]['text'])->toBe(isra9Text());
});

/*
 * ★ **والحاشيةُ تُنزع بما فيها** — كانت `strip_tags` تُبقي رقمَها ملتصقاً
 * بالكلمة، فظهر «His Messenger1» تحت آية الحجرات في صفحةٍ منشورة.
 */
it('ينزع حواشي الترجمة برقمها لا بوسمها وحده', function (): void {
    expect(SeedQuranTranslations::clean('before Allāh and His Messenger<sup foot_note=197401>1</sup> but fear Allāh.'))
        ->toBe('before Allāh and His Messenger but fear Allāh.')
        ->and(SeedQuranTranslations::clean('Those who &quot;believe&quot;'))->toBe('Those who "believe"');
});
