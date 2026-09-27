<?php

declare(strict_types=1);

use App\Support\Model\JsonSchema;

// المواصفة §12: «مخرَج كلّ مرحلة يُتحقّق من مخطّطه، فأيّ خروج عن الشكل
// المتوقّع يُوقف المهمّة». وهذا الحاجز الأهمّ أمام حقن التعليمات.

/** مخطّط استخراج الشواهد — المواصفة §6-3، بنصّه. */
function evidenceSchema(): array
{
    return [
        'type' => 'object',
        'required' => ['evidence'],
        'properties' => [
            'evidence' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['kind', 'raw_text'],
                    'properties' => [
                        'kind' => ['type' => 'string', 'enum' => ['ayah', 'hadith']],
                        'raw_text' => ['type' => 'string'],
                        'claimed_source' => ['type' => 'string', 'nullable' => true],
                    ],
                ],
            ],
        ],
    ];
}

it('accepts the evidence shape the spec defines', function (): void {
    $value = ['evidence' => [
        ['kind' => 'ayah', 'raw_text' => 'ومن عمل صالحا', 'claimed_source' => 'النحل ٩٧'],
        ['kind' => 'hadith', 'raw_text' => 'إنما الأعمال بالنيات'],
    ]];

    expect(JsonSchema::matches($value, evidenceSchema()))->toBeTrue();
});

it('names the missing key rather than failing silently', function (): void {
    $violations = JsonSchema::violations(['evidence' => [['kind' => 'ayah']]], evidenceSchema());

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('raw_text')
        ->and($violations[0])->toContain('مفتاح لازم غائب');
});

it('rejects a value outside the allowed set', function (): void {
    $value = ['evidence' => [['kind' => 'poetry', 'raw_text' => 'بيت شعر']]];

    expect(JsonSchema::matches($value, evidenceSchema()))->toBeFalse();
});

it('rejects the wrong type at the top', function (): void {
    expect(JsonSchema::matches(['evidence' => 'نصّ لا مصفوفة'], evidenceSchema()))->toBeFalse()
        ->and(JsonSchema::matches('نصّ', evidenceSchema()))->toBeFalse();
});

// **حالة الحقن.** نموذجٌ انصاع لأمرٍ في التفريغ يُخرج شيئاً غير الشكل
// المطلوب — نصّاً اعتذارياً مثلاً — فيُمسَك هنا وتقف المهمّة.
it('catches a model that answered something else entirely', function (): void {
    $injected = ['response' => 'تجاهلت التعليمات وكتبت هذا بدلاً منها'];

    expect(JsonSchema::matches($injected, evidenceSchema()))->toBeFalse();
});

it('tells an object from a list', function (): void {
    expect(JsonSchema::matches(['a' => 1], ['type' => 'object']))->toBeTrue()
        ->and(JsonSchema::matches([1, 2], ['type' => 'object']))->toBeFalse()
        ->and(JsonSchema::matches([1, 2], ['type' => 'array']))->toBeTrue()
        ->and(JsonSchema::matches(['a' => 1], ['type' => 'array']))->toBeFalse();
});

// المصفوفة الفارغة تصلح للاثنين في PHP، فلا يُبنى عليها رفض.
it('accepts an empty array as either shape', function (): void {
    expect(JsonSchema::matches([], ['type' => 'object']))->toBeTrue()
        ->and(JsonSchema::matches([], ['type' => 'array']))->toBeTrue();
});

it('separates integers from strings and booleans', function (): void {
    expect(JsonSchema::matches(97, ['type' => 'integer']))->toBeTrue()
        ->and(JsonSchema::matches('97', ['type' => 'integer']))->toBeFalse()
        ->and(JsonSchema::matches(true, ['type' => 'integer']))->toBeFalse()
        ->and(JsonSchema::matches(1.5, ['type' => 'number']))->toBeTrue()
        ->and(JsonSchema::matches(2, ['type' => 'number']))->toBeTrue();
});

it('accepts null only where the schema allows it', function (): void {
    expect(JsonSchema::matches(null, ['type' => 'string', 'nullable' => true]))->toBeTrue()
        ->and(JsonSchema::matches(null, ['type' => 'string']))->toBeFalse();
});

// المفاتيح الزائدة تُقبل: النماذج تضيف حقولاً، ورفضُها يُوقف المهمّة بلا
// ضرر حقيقي — والحاجز على ما نحتاجه لا على ما لا نحتاجه.
it('tolerates keys the schema does not mention', function (): void {
    $value = ['evidence' => [['kind' => 'ayah', 'raw_text' => 'نصّ', 'confidence' => 0.9]]];

    expect(JsonSchema::matches($value, evidenceSchema()))->toBeTrue();
});

it('reports the path of a nested violation', function (): void {
    $value = ['evidence' => [
        ['kind' => 'ayah', 'raw_text' => 'صحيح'],
        ['kind' => 'hadith', 'raw_text' => 12345],
    ]];

    expect(JsonSchema::violations($value, evidenceSchema())[0])
        ->toContain('evidence[1].raw_text');
});

// مخطّط البنية — المواصفة §6-2، متداخل بعمق.
it('validates the structure schema from the spec', function (): void {
    $schema = [
        'type' => 'object',
        'required' => ['title_ar', 'axes'],
        'properties' => [
            'title_ar' => ['type' => 'string'],
            'key_ayah' => [
                'type' => 'object',
                'required' => ['text', 'surah', 'ayah_number'],
                'properties' => [
                    'text' => ['type' => 'string'],
                    'surah' => ['type' => 'string'],
                    'ayah_number' => ['type' => 'integer'],
                ],
            ],
            'axes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'required' => ['name', 'steps'],
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ],
    ];

    $good = [
        'title_ar' => 'عنوان الدرس',
        'key_ayah' => ['text' => 'من عمل صالحا', 'surah' => 'النحل', 'ayah_number' => 97],
        'axes' => [['name' => 'المحور الأول', 'steps' => ['خطوة', 'خطوة أخرى']]],
    ];

    expect(JsonSchema::matches($good, $schema))->toBeTrue();

    // رقم الآية نصّاً لا عدداً — خطأٌ شائع في مخرَج النماذج.
    $bad = $good;
    $bad['key_ayah']['ayah_number'] = '97';

    expect(JsonSchema::matches($bad, $schema))->toBeFalse();
});
