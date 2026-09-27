<?php

declare(strict_types=1);

use App\Support\Quran\AyahText;

// T-56 — وحدةٌ خالصة: التزيين حتميّ، بلا نموذج ولا قاعدة بيانات.

it('يقوّس الآية التامّة ويعلّمها برقمها', function (): void {
    expect(AyahText::decorate('مَنْ عَمِلَ صَالِحًا', ['ayah_number' => 97, 'is_fragment' => false]))
        ->toBe('﴿مَنْ عَمِلَ صَالِحًا ۝٩٧﴾');
});

// **الشذرةُ لا تُعلَّم**: «۝» تقول إنّ الآية انتهت هنا، وذلك خبرٌ كاذب.
it('يقوّس الشذرة ولا يضع لها علامة', function (): void {
    expect(AyahText::decorate('مَنْ عَمِلَ صَالِحًا', ['ayah_number' => 97, 'is_fragment' => true]))
        ->toBe('﴿مَنْ عَمِلَ صَالِحًا﴾');
});

it('يعلّم الآيتين الموصولتين كلَّ واحدةٍ بموضعها', function (): void {
    $first = 'إِنَّ الْإِنسَانَ خُلِقَ هَلُوعًا';
    $second = 'إِذَا مَسَّهُ الشَّرُّ جَزُوعًا';

    expect(AyahText::decorate($first.' '.$second, [
        'ayah_number' => 19,
        'ayah_number_end' => 20,
        'ayah_break_at' => mb_strlen($first),
    ]))->toBe('﴿'.$first.' ۝١٩ '.$second.' ۝٢٠﴾');
});

// **مرّةً واحدة**: التحقّق يزيّن، والعارض يعرض. وتكرارُ التزيين يُضاعف الأقواس.
it('لا يزيّن ما زُيّن', function (): void {
    $done = '﴿مَنْ عَمِلَ صَالِحًا ۝٩٧﴾';

    expect(AyahText::decorate($done, ['ayah_number' => 97]))->toBe($done);
});

it('يقوّس بلا علامة حين لا يُعرف رقم الآية', function (): void {
    expect(AyahText::decorate('نصٌّ بلا بيانات', []))->toBe('﴿نصٌّ بلا بيانات﴾');
});

it('يلبس العلامة صنفَ المهارة ويهرّب ما حولها', function (): void {
    $html = AyahText::html('﴿مَنْ عَمِلَ ۝٩٧﴾ <img src=x>');

    expect($html)->toContain('<span class="ayah-no">۝٩٧</span>')
        ->and($html)->toContain('&lt;img')
        ->and($html)->not->toContain('<img');
});

it('لا يلبس شيئاً في نصٍّ بلا علامة', function (): void {
    expect(AyahText::html('حديثٌ لا آية'))->toBe('حديثٌ لا آية');
});
