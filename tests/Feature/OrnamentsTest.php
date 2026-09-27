<?php

declare(strict_types=1);

use App\Services\Render\BodyPurifier;
use App\Support\Render\BodyBlocks;
use App\Support\Render\Ornaments;

/*
 * T-54 — الزخارف ومواضعها.
 *
 * ورقةُ المهارة تحجز مقاساتٍ لا يملؤها أحد، فتظهر فجواتٌ بيضاء. وهذه
 * تحرس السلسلة كلَّها: يكتب `BodyBlocks` الموضع، ويمرّ بالمنقّي، ثمّ
 * تملؤه `Ornaments`. **وانكسارُ أيّ حلقةٍ صامت** — الرسمُ يسقط بلا خطأ.
 */

/** المتن كما يبلغ القارئ: مرسوماً ثمّ منقّى ثمّ مزخرفاً. */
function published(array $blocks): string
{
    $html = BodyBlocks::toHtml(['sections' => [['heading' => 'عنوان القسم', 'blocks' => $blocks]]]);

    return Ornaments::apply(app(BodyPurifier::class)->purify($html));
}

it('يملأ زخرفة صدر القسم', function (): void {
    expect(published([['type' => 'paragraph', 'text' => 'فقرة.']]))
        ->toContain('<svg class="mark"')
        ->not->toContain('<span class="mark"></span>');
});

/*
 * **بطاقةُ المجال وسمُها `a`** — والورقة تنسّق `.quad a` وحدها، فكانت
 * البطاقاتُ الأربع تخرج بلا إطارٍ ولا خلفيةٍ ولا حشوةٍ ولا توسيط.
 *
 * ★ **والمنقّي كان يمزّقها**: `a` في مذهبه مضمَّنٌ لا يحمل كتلة، فيقطع
 * `<a><h3>…</h3></a>` ثلاثاً. فيحرس هذا الاختبار الوسمَ وسلامتَه معاً.
 */
it('يُخرج بطاقة المجال وسماً واحداً يلفّ عنوانها', function (): void {
    $html = published([['type' => 'domains', 'items' => [
        ['title' => 'في العبادة', 'note' => 'فإنّ البِرَّ ليس بالإيضاع'],
    ]]]);

    expect($html)->toContain('<a>')
        ->and($html)->toContain('</a>')
        // لفّةٌ واحدة لا ثلاث: العنوانُ داخلها لا خارجها.
        ->and(substr_count($html, '<a>'))->toBe(1)
        ->and($html)->toMatch('#<a>.*<h3>في العبادة</h3>.*</a>#su')
        ->and($html)->toContain('<svg class="ico"');
});

it('يملأ أيقونة البطاقة وأيقونة الركن', function (): void {
    $cards = published([['type' => 'cards', 'items' => [['title' => 'الطبري', 'body' => 'القناعة']]]]);
    $pillar = published([['type' => 'pillar', 'title' => 'أوّلاً', 'items' => [['title' => 'اعرف', 'body' => 'نصّ']]]]);

    expect($cards)->toContain('<article class="imam"><svg class="ico"')
        ->and($pillar)->toContain('<div class="pillar-title"><svg class="ico"');
});

// **سهمٌ بين كلّ عقدتين**، ولونُه لون المسار: عُقدٌ متتابعةٌ بلا فاصل
// تُقرأ قائمةً لا سيراً.
it('يضع سهماً بين عقد المسار، بلون مساره', function (): void {
    $html = published([['type' => 'paths',
        'good' => ['title' => 'المداوِم', 'nodes' => ['عملٌ قليل', 'قصدٌ وتدرّج'], 'final' => 'سكينة'],
        'bad' => ['title' => 'المندفِع', 'nodes' => ['اندفاع'], 'final' => 'انقطاع'],
    ]]);

    // المحمود: عقدتان وخاتمة ← سهمان. والمذموم: عقدة وخاتمة ← سهم.
    expect(substr_count($html, 'stroke="#1B4D3E"'))->toBe(2)
        ->and(substr_count($html, 'stroke="#8E3B2E"'))->toBe(1)
        ->and($html)->not->toContain('<span class="arrow');
});

// **ولا يبقى موضعٌ فارغ**: موضعٌ يكتبه الراسم ولا تعرفه `Ornaments` فجوةٌ
// بيضاء بلا خطأ ولا سجلّ — وهو العطبُ الذي بُنيت هذه المهمّة له.
it('لا يترك موضع زخرفةٍ فارغاً في أيّ كتلة', function (): void {
    $html = published([
        ['type' => 'cards', 'items' => [['title' => 'ت', 'body' => 'ب']]],
        ['type' => 'domains', 'items' => [['title' => 'م', 'note' => 'ن']]],
        ['type' => 'pillar', 'title' => 'ر', 'items' => [['title' => 'خ', 'body' => 'ب']]],
        ['type' => 'paths', 'good' => ['nodes' => ['أ'], 'final' => 'ب'], 'bad' => ['nodes' => ['ج'], 'final' => 'د']],
    ]);

    expect($html)->not->toMatch('#<span class="(?:mark|ico|arrow)[^"]*"></span>#u');
});

/*
 * ═══ T-61 — الأيقونة تُختار بمعناها ═══
 *
 * T-54 ملأت المواضع بزخرفةٍ واحدة لكلّ نوع كتلة، فذهبت الفجواتُ البيضاء
 * **وبقي كلُّ قسمٍ بأيقونة أخيه**. والمرجع يختار لكلّ قسمٍ ما يدلّ عليه.
 */
it('يرسم أيقونة القسم المختارة بالاسم', function (): void {
    $html = Ornaments::apply(app(BodyPurifier::class)->purify(
        BodyBlocks::toHtml(['sections' => [
            ['heading' => 'الميزان', 'icon' => 'scales', 'blocks' => [['type' => 'paragraph', 'text' => 'ن']]],
            ['heading' => 'الداء', 'icon' => 'pulse', 'blocks' => [['type' => 'paragraph', 'text' => 'ن']]],
        ]])
    ));

    // ميزانٌ ونبضٌ — رسمان مختلفان، لا زخرفةٌ واحدة مكرّرة.
    expect($html)->toContain('M20 7v27M9 34h22')
        ->and($html)->toContain('M4 20h7l3-8')
        ->and($html)->not->toContain('<span class="mark');
});

it('يختار لكلّ بطاقةٍ وركنٍ ومجالٍ أيقونته', function (): void {
    $html = Ornaments::apply(app(BodyPurifier::class)->purify(
        BodyBlocks::toHtml(['sections' => [['heading' => 'ع', 'blocks' => [
            ['type' => 'cards', 'items' => [['title' => 'ت', 'body' => 'ب', 'icon' => 'book']]],
            ['type' => 'domains', 'items' => [['title' => 'م', 'note' => 'ن', 'icon' => 'plant']]],
            ['type' => 'pillar', 'title' => 'ر', 'icon' => 'lamp', 'items' => [['title' => 'خ', 'body' => 'ب']]],
        ]]]])
    ));

    expect($html)->toContain('M6 10h11a4 4 0')
        ->and($html)->toContain('M20 34V18M20 18c0-5.4')
        ->and($html)->toContain('M20 5v5M20 10a8 8 0');
});

/*
 * ★ **اسمٌ خارج القائمة يسقط إلى زخرفة نوعه، ولا يُكتب صنفاً.**
 *
 * فالاسمُ يكتبه نموذجٌ يقرأ نصَّ محاضرةٍ لا يسيطر عليه أحد، **فلا يدخل
 * صنفاً في HTML بلا فحص** — ويُصفّى مرّتين: في العارض ثمّ في المنقّي.
 */
it('يردّ اسماً خارج القائمة إلى زخرفة نوعه', function (): void {
    $raw = BodyBlocks::toHtml(['sections' => [
        ['heading' => 'ع', 'icon' => 'evil" onload="x', 'blocks' => [['type' => 'paragraph', 'text' => 'ن']]],
    ]]);

    expect($raw)->toBe(
        '<section><div class="sec-head"><span class="mark"></span><h2>ع</h2>'
        .'<span class="rule"></span></div><p>ن</p></section>'
    );

    $html = Ornaments::apply(app(BodyPurifier::class)->purify($raw));

    expect($html)->toContain('<svg class="mark"')
        ->and($html)->not->toContain('onload');
});

// **والمفرداتُ في التعليمات هي المفرداتُ في الكود** — قائمتان تتباعدان
// صامتتين تعني اسماً يختاره النموذج ولا رسمَ له.
it('يطابق ما في التعليمات ما في الكود', function (): void {
    $prompt = (string) file_get_contents(base_path('resources/prompts/islamic/writing.txt'));

    foreach (Ornaments::names() as $name) {
        expect($prompt)->toContain($name);
    }

    // ولا اسمَ في التعليمات بلا رسم: القائمة إحدى عشرة، والسطورُ إحدى عشرة.
    expect(Ornaments::names())->toHaveCount(11);
});
