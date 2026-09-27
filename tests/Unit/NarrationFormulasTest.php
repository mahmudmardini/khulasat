<?php

declare(strict_types=1);

use App\Support\Hadith\NarrationFormulas;

// T-06ب القرار الرابع: قائمةُ صيغِ الرواية، ويُشترط أن يكون المطابَق
// زائداً عليها لا هي نفسها.

it('reads a bare narration formula as no citation at all', function (string $text): void {
    // ★ «قال رسول الله صلى الله عليه وسلم» في آلاف صفوف المدوّنة، فكانت
    //   تُطابَق تامّةً **وتُنشر حديثاً صحيحاً بتخريجٍ كامل** — قِيس فعلاً.
    expect(NarrationFormulas::isFormulaic($text))->toBeTrue();
})->with([
    'قال رسول الله ﷺ' => ['قال رسول الله صلى الله عليه وسلم'],
    'الصلاة على النبي وحدها' => ['صلى الله عليه وسلم'],
    'عن النبي ﷺ قال' => ['عن النبي صلى الله عليه وسلم قال'],
    'سمعت رسول الله ﷺ' => ['سمعت رسول الله صلى الله عليه وسلم'],
    'صيغة تحديث' => ['حدثنا أخبرنا'],
    'ترضية' => ['رضي الله عنه'],
    'فارغ' => [''],
]);

it('reads a real matn as a citation however short it is', function (string $text): void {
    expect(NarrationFormulas::isFormulaic($text))->toBeFalse();
})->with([
    'حديث تامّ من ثلاث كلمات' => ['إنما الأعمال بالنيات'],
    'سبع كلمات — بطول الصيغة نفسها' => ['المسلم من سلم المسلمون من لسانه ويده'],
    'ضعيف مشهور' => ['طلب العلم فريضة على كل مسلم'],
    'متن مسبوق بصيغته' => ['قال رسول الله صلى الله عليه وسلم إنما الأعمال بالنيات'],
]);

it('does not separate formula from matn by length', function (): void {
    // ★ **الفاصل ليس الطول.** الصيغة سبع كلمات والمتن سبع كلمات، ورفعُ
    //   حدّ الكلمات إلى ثمانٍ يردّ الحديث ويُبقي الثغرة لصيغةٍ من تسع.
    $formula = 'قال رسول الله صلى الله عليه وسلم';
    $matn = 'المسلم من سلم المسلمون من لسانه ويده';

    expect(count(explode(' ', $formula)))->toBe(count(explode(' ', $matn)))
        ->and(NarrationFormulas::isFormulaic($formula))->toBeTrue()
        ->and(NarrationFormulas::isFormulaic($matn))->toBeFalse();
});

it('strips the formula and leaves the matn untouched', function (): void {
    expect(NarrationFormulas::strip('قال رسول الله صلى الله عليه وسلم إنما الأعمال بالنيات'))
        ->toBe('انما الاعمال بالنيات');
});

it('keeps words that belong to matns even when they look formulaic', function (): void {
    // «قال» و«يقول» تردان في المتون («قال الله تعالى»)، فنزعُهما يُنقص
    // متناً حقيقياً — والإفراط في النزع يردّ الصحيح، وهو أضرّ من التفريط.
    expect(NarrationFormulas::strip('قال الله تعالى أنا عند ظن عبدي بي'))
        ->toContain('قال')
        ->and(NarrationFormulas::isFormulaic('قال الله تعالى أنا عند ظن عبدي بي'))->toBeFalse();
});
