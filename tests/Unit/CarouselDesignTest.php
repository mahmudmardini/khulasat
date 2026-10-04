<?php

declare(strict_types=1);

use App\Enums\SlideKind;
use App\Models\Tenant;
use App\Support\Render\CarouselDesign;

// T-173 — مواصفةُ التصميم: كتالوجٌ مغلق، وافتراضيٌّ هو القالب كما كان.

/** مواصفةٌ صالحة بكلّ حقولها — يُعدَّل منها ما يُختبر. */
function designData(array $overrides = []): array
{
    return [
        'id' => 'night-lattice',
        'name' => 'ليلي',
        'surface' => 'night',
        'bookends' => 'deep',
        'background' => 'lattice',
        'frame' => 'double',
        'ornament' => 'star',
        'heading_font' => 'reem-kufi',
        'accent' => 'gold',
        'number' => 'bar',
        'layouts' => [
            'cover' => 'poster', 'ayah' => 'medallion', 'concept' => 'band', 'diagnosis' => 'side',
            'axis' => 'band', 'comparison' => 'side', 'evidence' => 'medallion', 'closing' => 'poster',
        ],
        ...$overrides,
    ];
}

it('يجعل الافتراضيَّ أوّلَ قيمةٍ في كلّ حقل', function (): void {
    $design = CarouselDesign::default();

    expect($design->isDefault())->toBeTrue();

    foreach (CarouselDesign::CATALOG as $field => $options) {
        expect($design->value($field))->toBe($options[0]);
    }

    foreach (SlideKind::cases() as $kind) {
        expect($design->layoutFor($kind))->toBe('classic');
    }
});

// معيار قبول: ثلاثةُ تخطيطاتٍ على الأقلّ لكلّ نوع، ولا نوعَ بلا تخطيط.
it('يعطي كلَّ نوع شريحة ثلاثة تخطيطاتٍ على الأقلّ', function (): void {
    expect(array_keys(CarouselDesign::LAYOUTS))
        ->toEqualCanonicalizing(array_map(static fn (SlideKind $kind): string => $kind->value, SlideKind::cases()));

    foreach (CarouselDesign::LAYOUTS as $layouts) {
        expect(count(array_unique($layouts)))->toBeGreaterThanOrEqual(3);
    }
});

it('يقرأ مواصفةً صالحة ويعيدها كما قُرئت', function (): void {
    $design = CarouselDesign::from(designData());

    expect($design->toArray())->toBe(designData())
        ->and($design->layoutFor(SlideKind::Ayah))->toBe('medallion')
        ->and($design->deckClasses())->toContain('surface-night')->toContain('heading-font-reem-kufi')
        ->and($design->extraFonts())->toBe(['Reem+Kufi:wght@500;700']);
});

// ★ **صارمةٌ لا متسامحة**: نصفُ مواصفةٍ صحيح يُرسم شكلاً لم يعتمده أحد.
it('يرفض المواصفة كلَّها لقيمةٍ واحدة خارج الكتالوج', function (mixed $data): void {
    expect(CarouselDesign::tryFrom($data))->toBeNull()
        ->and(fn () => CarouselDesign::from($data))->toThrow(InvalidArgumentException::class);
})->with([
    'لونٌ حرّ' => [designData(['surface' => '#ff0000'])],
    'CSS في قيمة' => [designData(['background' => 'url(https://evil.test/x.png)'])],
    'حقلٌ زائد' => [designData(['css' => '.heading{content:"x"}'])],
    'حقلٌ ناقص' => [array_diff_key(designData(), ['frame' => true])],
    'تخطيطٌ لا يعرفه النوع' => [designData(['layouts' => [...designData()['layouts'], 'cover' => 'medallion']])],
    'نوعٌ ناقص' => [designData(['layouts' => array_diff_key(designData()['layouts'], ['axis' => true])])],
    'نوعٌ زائد' => [designData(['layouts' => [...designData()['layouts'], 'video' => 'classic']])],
    'معرّفٌ فيه ما لا يُقبل' => [designData(['id' => 'Night <b>'])],
    'اسمٌ طويل' => [designData(['name' => str_repeat('ا', 41)])],
    'ليست كائناً' => ['نصٌّ لا مصفوفة'],
]);

it('يختار مواصفة الجهة بمعرّفها، وإلّا أوّلَ ما اعتمدت، وإلّا الافتراضية', function (): void {
    $second = designData(['id' => 'paper', 'surface' => 'paper-2']);

    $tenant = (new Tenant)->forceFill(['brand_kit' => [
        // المحفوظةُ التي لا تصحّ تُتخطّى ولا تُسقط الرسم.
        'carousel_designs' => [designData(['surface' => 'neon']), designData(), $second],
    ]]);

    expect(CarouselDesign::forTenant($tenant, 'paper')->id)->toBe('paper')
        ->and(CarouselDesign::forTenant($tenant)->id)->toBe('night-lattice')
        ->and(CarouselDesign::forTenant($tenant, 'gone')->id)->toBe('night-lattice')
        ->and(CarouselDesign::forTenant($tenant, CarouselDesign::DEFAULT_ID)->isDefault())->toBeTrue()
        ->and(CarouselDesign::forTenant((new Tenant)->forceFill(['brand_kit' => []]))->isDefault())->toBeTrue()
        ->and(CarouselDesign::forTenant(null)->isDefault())->toBeTrue();
});
