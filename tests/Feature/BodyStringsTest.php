<?php

declare(strict_types=1);

use App\Support\I18n\BodyStrings;

/*
 * جدولُ نصوص المتن — T-38.
 *
 * ★★ **والمقياس الحاكم أنّ لفظ الشاهد لا يُرسَل ولا يعود.** سياسةُ T-38:
 * «الشاهد يبقى بلفظه العربي، ولا يُترجَم نصُّه ولا يُستبدل به». وهذا هو
 * الحدُّ الذي لو انكسر لخرج شاهدٌ بلفظٍ لم يقله المتحدّث في صفحةٍ منشورة.
 */

function bodyFixture(): array
{
    return [
        'sections' => [[
            'heading' => 'عنوان القسم',
            'blocks' => [
                ['type' => 'lead', 'text' => 'فقرة الافتتاح'],
                ['type' => 'evidence', 'kind' => 'ayah', 'text' => 'لفظ الآية', 'source' => 'النحل ٩٧', 'tone' => 'dark'],
                ['type' => 'evidence', 'kind' => 'hadith', 'text' => 'لفظ الحديث', 'source' => 'رواه البخاري'],
                ['type' => 'stats', 'items' => [['value' => '٧٠%', 'label' => 'دلالة']]],
                ['type' => 'night', 'title' => 'الختام', 'checks' => ['مهمّة']],
            ],
        ]],
        'closing' => 'جملة الختام',
    ];
}

it('لا يُرسل لفظ الشاهد للترجمة', function (): void {
    $strings = BodyStrings::extract(bodyFixture());

    // ★★ الحدّ: اللفظان العربيّان ليسا في الجدول أصلاً، فلا يبلغان النموذج.
    expect($strings)->not->toContain('لفظ الآية')
        ->and($strings)->not->toContain('لفظ الحديث')
        // وما حولهما يُترجَم: التخريج والعنوان والفقرة.
        ->and($strings)->toContain('النحل ٩٧')
        ->and($strings)->toContain('رواه البخاري')
        ->and($strings)->toContain('عنوان القسم')
        ->and($strings)->toContain('فقرة الافتتاح')
        ->and($strings)->toContain('جملة الختام')
        ->and($strings)->toContain('مهمّة');
});

it('لا يُرسل قيم التعداد ولا الأرقام', function (): void {
    $strings = BodyStrings::extract(bodyFixture());

    // ترجمةُ `type` تُسقط الكتلة، وترجمةُ `kind` تُسقط نبرتها.
    expect($strings)->not->toContain('lead')
        ->and($strings)->not->toContain('evidence')
        ->and($strings)->not->toContain('hadith')
        ->and($strings)->not->toContain('dark')
        // والأرقام لا تُترجَم.
        ->and($strings)->not->toContain('٧٠%')
        ->and($strings)->toContain('دلالة');
});

it('يُعيد المترجَم إلى موضعه بلا تبديل بنية', function (): void {
    $body = bodyFixture();
    $strings = BodyStrings::extract($body);

    $translated = array_map(static fn (string $v): string => 'TR:'.$v, $strings);
    $out = BodyStrings::apply($body, $translated);

    expect($out['sections'][0]['heading'])->toBe('TR:عنوان القسم')
        ->and($out['closing'])->toBe('TR:جملة الختام')
        ->and($out['sections'][0]['blocks'][0]['text'])->toBe('TR:فقرة الافتتاح')
        // ★★ واللفظُ العربي كما هو، ولو أرسل النموذج له ترجمة.
        ->and($out['sections'][0]['blocks'][1]['text'])->toBe('لفظ الآية')
        ->and($out['sections'][0]['blocks'][2]['text'])->toBe('لفظ الحديث')
        // والبنية لم تتبدّل: الأنواع والأصناف كما كانت.
        ->and($out['sections'][0]['blocks'][1]['type'])->toBe('evidence')
        ->and($out['sections'][0]['blocks'][1]['kind'])->toBe('ayah')
        ->and($out['sections'][0]['blocks'][1]['tone'])->toBe('dark')
        ->and($out['sections'][0]['blocks'][3]['items'][0]['value'])->toBe('٧٠%');
});

// **ما لم يعُد له ترجمةٌ يبقى بأصله** — سطرٌ عربيّ في صفحةٍ تركية أهونُ من
// سطرٍ فارغ: القارئ يرى أنّ شيئاً لم يُترجم، ولا يظنّ أنّ شيئاً نقص.
it('يُبقي ما لم تعُد له ترجمة بأصله', function (): void {
    $body = bodyFixture();
    $out = BodyStrings::apply($body, ['sections.0.heading' => 'Bölüm']);

    expect($out['sections'][0]['heading'])->toBe('Bölüm')
        ->and($out['closing'])->toBe('جملة الختام')
        // وترجمةٌ فارغة تُهمَل ولا تُفرّغ الأصل.
        ->and(BodyStrings::apply($body, ['closing' => '   '])['closing'])->toBe('جملة الختام');
});

// ترجمةُ المعنى مطلبٌ منفصل — والآياتُ ليست فيه، ترجمتُها معتمدةٌ تُقرأ
// من الجدول ولا يترجمها نموذج.
it('يجمع ألفاظ الحديث وحدها لترجمة المعنى', function (): void {
    $hadith = BodyStrings::hadithTexts(bodyFixture());

    expect($hadith)->toHaveCount(1)
        ->and(array_values($hadith)[0])->toBe('لفظ الحديث')
        ->and(array_key_first($hadith))->toBe('sections.0.blocks.2.text');
});

it('لا ينكسر على متنٍ فارغ أو معطوب', function (): void {
    expect(BodyStrings::extract(null))->toBe([])
        ->and(BodyStrings::apply(null, ['a' => 'b']))->toBeNull()
        ->and(BodyStrings::hadithTexts(null))->toBe([])
        ->and(BodyStrings::extract(['sections' => 'ليست قائمة']))->toBe(['sections' => 'ليست قائمة']);
});
