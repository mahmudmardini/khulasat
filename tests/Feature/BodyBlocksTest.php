<?php

declare(strict_types=1);

use App\Actions\Stages\StageSchemas;
use App\Enums\Stage;
use App\Services\Render\BodyPurifier;
use App\Support\Model\JsonSchema;
use App\Support\Render\BodyBlocks;

/*
 * متن الملخّص كتلاً — T-43.
 *
 * **والمقياس الحاكم: ما يُخرجه الصانع يجب أن ينجو من المنقّي كاملاً.** فلو
 * كتب صنفاً خارج قائمة السماح لسقط صامتاً — وهي العلّة التي بُنيت هذه
 * المرحلة كلُّها لإسقاطها.
 */

function body(array $blocks, ?string $closing = null): array
{
    return array_filter([
        'sections' => [['heading' => 'محور', 'blocks' => $blocks]],
        'closing' => $closing,
    ], static fn (mixed $v): bool => $v !== null);
}

it('يكتب أصناف المهارة لا النموذج', function (): void {
    $html = BodyBlocks::toHtml(body([
        ['type' => 'lead', 'text' => 'افتتاحية'],
        ['type' => 'paragraph', 'text' => 'فقرة'],
    ], 'ختام'));

    expect($html)->toContain('<div class="sec-head"><span class="mark"></span><h2>محور</h2>')
        ->and($html)->toContain('<p class="lead">افتتاحية</p>')
        ->and($html)->toContain('<p>فقرة</p>')
        ->and($html)->toContain('<p class="closing">ختام</p>');
});

it('يرسم الكتل التسع كلَّها بأصنافها', function (array $block, string $expected): void {
    expect(BodyBlocks::toHtml(body([$block])))->toContain($expected);
})->with([
    'الشاهد' => [['type' => 'evidence', 'kind' => 'ayah', 'text' => 'آية', 'source' => 'النحل ٩٧'], '<div class="sacred"><p class="text" lang="ar" dir="rtl">آية</p><span class="src">النحل ٩٧</span></div>'],
    'الحديث' => [['type' => 'evidence', 'kind' => 'hadith', 'text' => 'متن'], '<p class="text hadith" lang="ar" dir="rtl">متن</p>'],
    'الضعيف المبيَّن' => [['type' => 'evidence', 'text' => 'متن', 'tone' => 'warn'], '<div class="sacred warn">'],
    'البطاقات' => [['type' => 'cards', 'items' => [['title' => 'ع', 'body' => 'م']]], '<div class="trio"><article class="imam"><span class="ico card"></span><h3>ع</h3><p>م</p></article></div>'],
    'المقارنة' => [['type' => 'comparison', 'items' => [['question' => 'س', 'calm' => ['tag' => 'ت', 'text' => 'أ'], 'panic' => ['text' => 'ب']]]], '<div class="side calm"><span class="tag">ت</span><p>أ</p></div>'],
    'المجالات' => [['type' => 'domains', 'items' => [['title' => 'العبادة', 'note' => 'ن']]], '<div class="quad"><a><span class="ico domain"></span><h3>العبادة</h3>'],
    'الأركان' => [['type' => 'pillar', 'title' => 'خطوات', 'items' => [['title' => 'أ', 'body' => 'ب']]], '<div class="step"><div class="num">١</div>'],
    'المسارات' => [['type' => 'paths', 'good' => ['title' => 'ح', 'nodes' => ['ن'], 'final' => 'م']], '<div class="path good"><h4>ح</h4><div class="node">ن</div><span class="arrow good"></span><div class="node final">م</div></div>'],
    'الليل' => [['type' => 'night', 'title' => 'خاتمة', 'text' => 'تأمّل', 'checks' => ['مهمّة']], '<div class="checks"><label><input type="checkbox" />مهمّة</label></div>'],
]);

/**
 * ★ **الحارس الأهمّ في هذا الملفّ.**
 *
 * كلّ ما يكتبه الصانع يجب أن ينجو من `BodyPurifier` بلا نقص. فلو أخطأ
 * صنفاً أو وسماً لسقط صامتاً وخرجت الكتلة بلا تنسيق — وهو العطب بعينه
 * الذي وُجد في `article` (T-45).
 */
it('ينجو مخرَجه من المنقّي بلا نقص', function (): void {
    $html = BodyBlocks::toHtml(body([
        ['type' => 'lead', 'text' => 'ا'],
        ['type' => 'paragraph', 'text' => 'ب', 'muted' => true],
        ['type' => 'evidence', 'kind' => 'hadith', 'text' => 'ج', 'source' => 'د', 'tone' => 'warn'],
        ['type' => 'cards', 'items' => [['title' => 'ه', 'body' => 'و']]],
        ['type' => 'comparison', 'items' => [['question' => 'ز', 'calm' => ['tag' => 'ح', 'text' => 'ط'], 'panic' => ['tag' => 'ي', 'text' => 'ك']]]],
        ['type' => 'domains', 'items' => [['title' => 'ل', 'note' => 'م']]],
        ['type' => 'pillar', 'title' => 'ن', 'items' => [['title' => 'س', 'body' => 'ع']]],
        ['type' => 'paths', 'good' => ['title' => 'ف', 'nodes' => ['ص'], 'final' => 'ق'], 'bad' => ['title' => 'ر', 'nodes' => ['ش'], 'final' => 'ت']],
        ['type' => 'night', 'title' => 'ث', 'text' => 'خ', 'note' => 'ذ', 'checks' => ['ض']],
        // ★ خمسُ T-46 — وهي في هذا الاختبار **قبل** أن تُكتب أنماطُها.
        ['type' => 'table', 'title' => 'غ', 'columns' => ['أ', 'ب'], 'rows' => [['ج', 'د']], 'note' => 'ه'],
        ['type' => 'list', 'title' => 'و', 'ordered' => true, 'items' => ['ز']],
        ['type' => 'list', 'items' => ['ح']],
        ['type' => 'stats', 'title' => 'ط', 'items' => [['value' => '٧', 'label' => 'ي']]],
        ['type' => 'timeline', 'title' => 'ك', 'items' => [['label' => 'ل', 'title' => 'م', 'body' => 'ن']]],
        ['type' => 'tree', 'title' => 'س', 'root' => 'ع', 'items' => [['title' => 'ف', 'items' => ['ص', ['title' => 'ك', 'items' => ['ه']]]]]],
        // ★ اثنتان T-72 — هرمٌ وعجلة.
        ['type' => 'pyramid', 'title' => 'ب', 'items' => [['title' => 'ت', 'body' => 'ث']], 'note' => 'ج'],
        ['type' => 'gauge', 'title' => 'ح', 'value' => 55, 'bad' => ['title' => 'خ'], 'good' => ['title' => 'د'], 'note' => 'ذ'],
        // ★ أربعٌ T-76 — تعريف مصطلح وسؤال وجواب وتنبيه وتصحيح شبهة.
        ['type' => 'term_gloss', 'title' => 'ر', 'root' => 'ز', 'linguistic' => 'س', 'technical' => 'ش', 'note' => 'ص'],
        ['type' => 'qa_pair', 'items' => [['q' => 'ض', 'a' => 'ط']]],
        ['type' => 'callout_note', 'tone' => 'benefit', 'title' => 'ظ', 'text' => 'ع'],
        ['type' => 'callout_note', 'tone' => 'warning', 'text' => 'غ'],
        ['type' => 'callout_note', 'text' => 'ف'],
        ['type' => 'misconception_fix', 'claim' => 'ق', 'correction' => 'ك', 'note' => 'ل'],
    ], 'ظ'));

    expect(app(BodyPurifier::class)->purify($html))->toBe($html);
});

it('يحذف ما لا مادّة فيه ولا يخترع', function (): void {
    // «قسم فارغ أسوأ من قسم محذوف» — حزمة التعليمات.
    expect(BodyBlocks::toHtml(['sections' => [['heading' => 'عنوان', 'blocks' => []]]]))->toBe('')
        ->and(BodyBlocks::toHtml(body([['type' => 'paragraph', 'text' => '  ']])))->toBe('')
        ->and(BodyBlocks::toHtml(body([['type' => 'cards', 'items' => [['title' => '', 'body' => '']]]])))->toBe('')
        ->and(BodyBlocks::toHtml(null))->toBe('');
});

it('يُهرّب ما يكتبه النموذج ولا ينفّذه', function (): void {
    $html = BodyBlocks::toHtml(body([
        ['type' => 'paragraph', 'text' => '<script>alert(1)</script>'],
    ]));

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('يُسقط نوعاً لا عارض له بدل أن يخترع له صنفاً', function (): void {
    expect(BodyBlocks::toHtml(body([['type' => 'مخترَع', 'text' => 'نصّ']])))->toBe('');
});

/*
 * ─── المخطّط ─────────────────────────────────────────────────────────
 */

it('يقبل الكتل التسع ويردّ ما سواها', function (): void {
    $schema = StageSchemas::for(Stage::Writing);

    expect($schema)->not->toBeNull()
        ->and(JsonSchema::violations(body([['type' => 'lead', 'text' => 'ن']]), $schema))->toBe([])
        // نوعٌ خارج القائمة يُردّ **عند المخطّط**، فيُعاد النداء مرّة ثمّ يقف
        // — بدل أن يسقط صامتاً كما كان.
        ->and(JsonSchema::violations(body([['type' => 'poem', 'text' => 'ن']]), $schema))->not->toBe([]);
});

it('يفرض وجود الأقسام', function (): void {
    expect(JsonSchema::violations(['closing' => 'ختام'], StageSchemas::for(Stage::Writing)))
        ->not->toBe([]);
});

/*
 * ══ كتل T-46 — جدولٌ وقائمةٌ وأرقامٌ وخطُّ زمنٍ وتفريع ══
 */

it('يبني جدولاً برؤوسه وصفوفه', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'table',
        'title' => 'مقارنة',
        'columns' => ['الوجه', 'الحكم'],
        'rows' => [['الأول', 'جائز'], ['الثاني', 'ممنوع']],
        'note' => 'ملاحظة',
    ]]));

    expect($html)->toContain('<div class="data">')
        ->toContain('<h3>مقارنة</h3>')
        ->toContain('<thead><tr><th>الوجه</th><th>الحكم</th></tr></thead>')
        ->toContain('<tr><td>الأول</td><td>جائز</td></tr>')
        ->toContain('<p class="note">ملاحظة</p>');
});

// رؤوسٌ بلا صفوف ليست جدولاً — فتسقط الكتلة كلُّها لا نصفُها.
it('يُسقط الجدول الذي لا صفوف له', function (): void {
    expect(BodyBlocks::toHtml(body([['type' => 'table', 'columns' => ['أ', 'ب']]])))->toBe('')
        ->and(BodyBlocks::toHtml(body([['type' => 'table', 'rows' => []]])))->toBe('');
});

it('يبني القائمة مرقّمةً ومنقّطة', function (): void {
    $ordered = BodyBlocks::toHtml(body([['type' => 'list', 'ordered' => true, 'items' => ['أ', 'ب']]]));
    $bulleted = BodyBlocks::toHtml(body([['type' => 'list', 'items' => ['ج']]]));

    expect($ordered)->toContain('<ol class="numbered"><li>أ</li><li>ب</li></ol>')
        ->and($bulleted)->toContain('<ul class="bullets"><li>ج</li></ul>');
});

// **لا كتلةَ كانت تُخرج قائمةً قبل T-46** مع أنّ `ul`/`ol` مسموحتان
// ومنسَّقتان في القوالب الثلاثة — فجوةٌ قِيست ثم سُدّت.
it('يقبل عناصر القائمة نصّاً وكائناً', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'list',
        'items' => ['نصّ', ['text' => 'كائن'], ['لا نصّ فيه'], '  ', 5],
    ]]));

    expect($html)->toContain('<li>نصّ</li>')
        ->toContain('<li>كائن</li>')
        ->toContain('<li>5</li>')
        // الفراغ والكائن الذي لا `text` فيه يسقطان ولا يُسقطان الكتلة.
        ->not->toContain('<li></li>');
});

it('يحوّل أرقام الإحصاء إلى العربية الهندية', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'stats',
        'items' => [['value' => '70%', 'label' => 'نسبة'], ['value' => '12', 'label' => 'عدد']],
    ]]));

    expect($html)->toContain('<span class="fig-num">٧٠%</span>')
        ->toContain('<span class="fig-label">نسبة</span>')
        ->toContain('<span class="fig-num">١٢</span>')
        // رقمٌ بلا قيمة لا يُعرض، فلا يبقى صندوقٌ خاوٍ في صفحةٍ منشورة.
        ->and(BodyBlocks::toHtml(body([['type' => 'stats', 'items' => [['label' => 'بلا رقم']]]])))->toBe('');
});

it('يبني خطّ الزمن بأرقام هندية', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'timeline',
        'items' => [['label' => '622', 'title' => 'الهجرة', 'body' => 'شرح']],
    ]]));

    expect($html)->toContain('<div class="timeline">')
        ->toContain('<span class="when">٦٢٢</span>')
        ->toContain('<div class="what"><h4>الهجرة</h4><p>شرح</p></div>');
});

// **فرعٌ يتفرّع فرعاً فرعياً** — T-72، توسيعاً على مستويي T-46. وثلاثةُ
// مستوياتٍ حدٌّ أقصى برمجيّ ({@see \App\Support\Render\BodyBlocks::TREE_MAX_DEPTH})،
// وهذا يُثبت أنّ الرابع يسقط بعنوانه ومادّته معاً ولا يُسقط ما فوقه.
it('يبني التفريع بأكثر من مستويين، وثلاثةٌ حدُّه الأقصى', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'tree',
        'root' => 'الأصل',
        'items' => [[
            'title' => 'فرع',
            'items' => [
                'ورقة',
                ['title' => 'فرعٌ فرعي', 'items' => [
                    'ورقةٌ عميقة',
                    ['title' => 'أعمق', 'items' => [
                        ['title' => 'الرابع', 'items' => ['لن يظهر']],
                    ]],
                ]],
            ],
        ]],
    ]]));

    expect($html)->toContain('<div class="root">الأصل</div>')
        ->toContain(
            '<div class="branch"><h4>فرع</h4><div class="leaf">ورقة</div>'
            .'<div class="branch"><h4>فرعٌ فرعي</h4><div class="leaf">ورقةٌ عميقة</div>'
            .'<div class="branch"><h4>أعمق</h4></div></div></div>'
        )
        // المستوى الرابع يسقط بعنوانه ومادّته معاً — حاجزٌ برمجيّ لا تعليماتيّ وحده.
        ->not->toContain('الرابع')
        ->not->toContain('لن يظهر');
});

it('يبني الهرم بطبقاتٍ من القمّة إلى القاعدة', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'pyramid',
        'title' => 'مراتب',
        'items' => [
            ['title' => 'الإحسان', 'body' => 'أعلاها'],
            ['title' => 'الإيمان'],
            ['title' => 'الإسلام', 'body' => 'قاعدتها'],
        ],
        'note' => 'ملاحظة',
    ]]));

    expect($html)->toContain('<div class="pyramid"><h3>مراتب</h3><div class="tiers">')
        ->toContain('<div class="tier"><h4>الإحسان</h4><p>أعلاها</p></div>')
        ->toContain('<div class="tier"><h4>الإيمان</h4></div>')
        ->toContain('<p class="note">ملاحظة</p>')
        // طبقةٌ بلا عنوانٍ ولا متن لا تُرسم.
        ->and(BodyBlocks::toHtml(body([['type' => 'pyramid', 'items' => [['title' => '', 'body' => '']]]])))->toBe('');
});

it('يبني عجلة المقياس ويقرّب القيمة إلى أقرب عشرة', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'gauge',
        'title' => 'درجة اليقظة',
        'value' => 63,
        'bad' => ['title' => 'غفلة'],
        'good' => ['title' => 'يقظة'],
        'note' => 'ملاحظة',
    ]]));

    expect($html)->toContain('<div class="gauge"><h3>درجة اليقظة</h3>')
        ->toContain('<div class="ring fill-60"><span class="reading">٦٠</span></div>')
        ->toContain('<div class="poles"><div class="pole">غفلة</div><div class="pole">يقظة</div></div>')
        ->toContain('<p class="note">ملاحظة</p>');
});

// قيمةٌ خارج ٠-١٠٠ تُحصر بين طرفيها، ولا قيمةَ فيها كتلةٌ لا مادّة لها.
it('يحصر قيمة العجلة بين صفرٍ ومئة، ويُسقطها بلا قيمة', function (): void {
    expect(BodyBlocks::toHtml(body([['type' => 'gauge', 'value' => 140]])))->toContain('fill-100')
        ->and(BodyBlocks::toHtml(body([['type' => 'gauge', 'value' => -20]])))->toContain('fill-0')
        ->and(BodyBlocks::toHtml(body([['type' => 'gauge', 'title' => 'بلا قيمة']])))->toBe('');
});

it('يبني تعريف المصطلح لغةً واصطلاحاً', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'term_gloss',
        'title' => 'التوكّل',
        'root' => 'و ك ل',
        'linguistic' => 'تفويض الأمر',
        'technical' => 'الاعتماد على الله مع الأخذ بالأسباب',
        'note' => 'ملاحظة',
    ]]));

    expect($html)->toContain(
        '<div class="gloss"><div class="gloss-head"><span class="gloss-term">التوكّل</span>'
        .'<span class="gloss-root">و ك ل</span></div><div class="gloss-body">'
        .'<span class="gloss-label">لغةً</span><p class="gloss-text">تفويض الأمر</p>'
        .'<span class="gloss-label">اصطلاحاً</span>'
        .'<p class="gloss-text">الاعتماد على الله مع الأخذ بالأسباب</p></div>'
        .'<p class="note">ملاحظة</p></div>'
    )
        // مصطلحٌ بلا أيّ معنى — لا لغةً ولا اصطلاحاً — لا يُرسم.
        ->and(BodyBlocks::toHtml(body([['type' => 'term_gloss', 'title' => 'كلمة']])))->toBe('')
        // معنًى بلا اسم مصطلح لا يُرسم أيضاً.
        ->and(BodyBlocks::toHtml(body([['type' => 'term_gloss', 'linguistic' => 'معنى']])))->toBe('');
});

it('يبني سؤالاً وجواباً بلا عنوانٍ للكتلة نفسها', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'qa_pair',
        'items' => [
            ['q' => 'هل يجوز كذا؟', 'a' => 'نعم يجوز.'],
            ['q' => 'وماذا عن كذا؟'],
        ],
    ]]));

    expect($html)->toContain('<div class="qa"><div class="qa-item"><div class="qa-q">هل يجوز كذا؟</div><div class="qa-a">نعم يجوز.</div></div>')
        ->toContain('<div class="qa-item"><div class="qa-q">وماذا عن كذا؟</div></div>')
        // لا عنوان مضافٌ للكتلة نفسها — قصدٌ لا سهو، كي لا يتكرّر عنوان القسم.
        ->not->toContain('<div class="qa"><h3>')
        ->and(BodyBlocks::toHtml(body([['type' => 'qa_pair', 'items' => [['q' => '', 'a' => '']]]])))->toBe('');
});

it('يبني تنبيهاً جانبياً بدرجته الثلاث، والافتراض لطيفة', function (): void {
    $html = BodyBlocks::toHtml(body([
        ['type' => 'callout_note', 'tone' => 'benefit', 'title' => 'فائدة', 'text' => 'نصّ الفائدة'],
        ['type' => 'callout_note', 'tone' => 'warning', 'text' => 'نصّ التنبيه'],
        ['type' => 'callout_note', 'text' => 'استطراد'],
    ]));

    expect($html)->toContain('<div class="cnote cn-benefit"><span class="cn-mark"></span><div><h4 class="cn-title">فائدة</h4><p class="cn-text">نصّ الفائدة</p></div></div>')
        ->toContain('<div class="cnote cn-warning"><span class="cn-mark"></span><div><p class="cn-text">نصّ التنبيه</p></div></div>')
        ->toContain('<div class="cnote cn-subtle"><span class="cn-mark"></span><div><p class="cn-text">استطراد</p></div></div>')
        ->and(BodyBlocks::toHtml(body([['type' => 'callout_note', 'title' => 'بلا نصّ']])))->toBe('');
});

it('يبني تصحيح شبهة، وكلا الوجهين لازمان معاً', function (): void {
    $html = BodyBlocks::toHtml(body([[
        'type' => 'misconception_fix',
        'claim' => 'يُظنّ أنّ كذا',
        'correction' => 'والصواب كذا',
        'note' => 'دليل',
    ]]));

    expect($html)->toContain(
        '<div class="mfix"><div class="mfix-claim"><span class="mfix-tag">يُظنّ</span><p>يُظنّ أنّ كذا</p></div>'
        .'<div class="mfix-fix"><span class="mfix-tag">والصواب</span><p>والصواب كذا</p></div>'
        .'<p class="mfix-evidence">دليل</p></div>'
    )
        ->and(BodyBlocks::toHtml(body([['type' => 'misconception_fix', 'claim' => 'فقط']])))->toBe('')
        ->and(BodyBlocks::toHtml(body([['type' => 'misconception_fix', 'correction' => 'فقط']])))->toBe('');
});

it('يُهرّب نصّ الكتل الجديدة كلَّها', function (): void {
    $x = '<script>alert(1)</script>';

    $html = BodyBlocks::toHtml(body([
        ['type' => 'table', 'columns' => [$x], 'rows' => [[$x]], 'title' => $x],
        ['type' => 'list', 'items' => [$x]],
        ['type' => 'stats', 'items' => [['value' => '1', 'label' => $x]]],
        ['type' => 'timeline', 'items' => [['label' => '1', 'title' => $x]]],
        ['type' => 'tree', 'root' => $x, 'items' => [['title' => $x, 'items' => [$x]]]],
        // ★ اثنتان T-72.
        ['type' => 'pyramid', 'title' => $x, 'items' => [['title' => $x, 'body' => $x]]],
        ['type' => 'gauge', 'title' => $x, 'value' => 1, 'bad' => ['title' => $x], 'good' => ['title' => $x]],
        // ★ أربعٌ T-76.
        ['type' => 'term_gloss', 'title' => $x, 'root' => $x, 'linguistic' => $x, 'technical' => $x, 'note' => $x],
        ['type' => 'qa_pair', 'items' => [['q' => $x, 'a' => $x]]],
        ['type' => 'callout_note', 'title' => $x, 'text' => $x],
        ['type' => 'misconception_fix', 'claim' => $x, 'correction' => $x, 'note' => $x],
    ]));

    expect($html)->not->toContain('<script>')
        // سبعةً وعشرين موضعاً: خمسةَ عشر من قبل T-76 (الجدول ثلاثة، والقائمة
        // والإحصاء وخطُّ الزمن واحد لكلٍّ، والشجرةُ ثلاثة، والهرمُ ثلاثة،
        // والعجلةُ ثلاثة) + تعريف المصطلح خمسة + السؤال والجواب اثنان +
        // التنبيه اثنان + تصحيح الشبهة ثلاثة.
        ->and(substr_count($html, '&lt;script&gt;'))->toBe(27);
});
