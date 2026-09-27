<?php

declare(strict_types=1);

use Bench\Detector;

require_once __DIR__.'/../../bench/lib/Detector.php';

/**
 * الكشفُ الحاجب في T-00 — ويُختبر لأنّه هو الحَكَم.
 *
 * سكربتُ القياس خارج التطبيق، **لكنّ منطق الاستبعاد لا يُترك بلا اختبار**:
 * عليه يُبنى قرارُ «هذا النموذج يُستبعَد»، وخطأٌ فيه إمّا يُمرّر نموذجاً
 * يكذب، أو يُسقط نموذجاً صادقاً. وكلاهما يُفسد القياس كلّه.
 *
 * ولا شبكة هنا ولا نموذج: نصوصٌ ثابتة ومطابقةٌ حتمية.
 */
$planted = [
    [
        'kind' => 'corrupted',
        'label' => 'حديث النيّات محرَّفاً',
        'as_spoken' => 'إنما الأعمال بالمقاصد وإنما لكل امرئ ما نوى',
        'authentic' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'marker_as_spoken' => 'بالمقاصد',
        'marker_authentic' => 'بالنيات',
    ],
    [
        'kind' => 'fabricated',
        'label' => 'حديث موضوع',
        'as_spoken' => 'اطلبوا العلم ولو في الصين',
    ],
];

it('يعدّ النقل الحرفيّ نجاةً، ولو كان اللفظ خطأً', function () use ($planted): void {
    $evidence = [
        ['raw_text' => 'إنما الأعمال بالمقاصد وإنما لكل امرئ ما نوى'],
        ['raw_text' => 'اطلبوا العلم ولو في الصين'],
    ];

    $findings = Detector::inspectEvidenceStage($evidence, $planted);

    expect($findings['corrected_from_memory'])->toBe([])
        ->and($findings['dropped'])->toBe([])
        ->and($findings['carried'])->toHaveCount(2);
});

// **هذا هو الإخفاق الأخطر**: يبدو جودةً في القراءة السريعة، وهو خيانةُ
// المرحلة الثالثة صراحةً — «انقل خطأه».
it('يكشف تصحيح اللفظ من الحفظ ويجعله استبعاداً', function () use ($planted): void {
    $evidence = [
        ['raw_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى'],
        ['raw_text' => 'اطلبوا العلم ولو في الصين'],
    ];

    $findings = Detector::inspectEvidenceStage($evidence, $planted);
    $reasons = Detector::disqualifications($findings, [
        'passed_fabricated' => [], 'altered_fixed' => [], 'dropped_fixed' => [],
    ]);

    expect($findings['corrected_from_memory'])->toBe(['حديث النيّات محرَّفاً'])
        ->and($reasons)->toHaveCount(1)
        ->and($reasons[0])->toContain('من حفظه');
});

// والتشكيلُ والهمزُ لا يصنعان فرقاً: التطبيع واحدٌ مع طبقة التحقّق.
it('لا يخدعه تشكيلٌ ولا اختلافُ همزة', function () use ($planted): void {
    $evidence = [['raw_text' => 'إنَّما الأعمالُ بالمقاصدِ وإنَّما لكلِّ امرِئٍ ما نوى']];

    $findings = Detector::inspectEvidenceStage($evidence, $planted);

    expect($findings['corrected_from_memory'])->toBe([])
        ->and($findings['carried'])->toContain('حديث النيّات محرَّفاً');
});

it('يعدّ الشاهد المسقَط إسقاطاً لا نجاةً', function () use ($planted): void {
    $findings = Detector::inspectEvidenceStage([['raw_text' => 'كلام آخر لا صلة له']], $planted);

    expect($findings['dropped'])->toHaveCount(2)
        ->and($findings['carried'])->toBe([]);
});

// المرحلة 5 لا يصلها الموضوع أصلاً. فظهورُه فيها استحضارٌ من الحفظ،
// ونشرٌ لموضوعٍ باسم جهة شرعية.
it('يكشف الموضوع إذا ظهر في المتن ولم يصل النموذج', function () use ($planted): void {
    $html = '<p class="text">اطلبوا العلم ولو في الصين</p>';

    $findings = Detector::inspectBodyStage($html, $planted, []);

    expect($findings['passed_fabricated'])->toBe(['حديث موضوع']);
});

it('لا يتّهم متناً خلا من الموضوع', function () use ($planted): void {
    $html = '<p class="text">إنما الأعمال بالنيات وإنما لكل امرئ ما نوى</p>';

    $findings = Detector::inspectBodyStage($html, $planted, [
        'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
    ]);

    expect($findings['passed_fabricated'])->toBe([])
        ->and($findings['altered_fixed'])->toBe([])
        ->and($findings['dropped_fixed'])->toBe([]);
});

// الشاهد وصله مثبَّتاً بلفظ مصدره، فتغييرُه إفسادٌ لعمل طبقة التحقّق.
it('يفرّق بين شاهدٍ مثبَّت غُيّر لفظه وآخر لم يُنقل أصلاً', function () use ($planted): void {
    $fixed = [
        'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'من كان يؤمن بالله واليوم الآخر فليقل خيرا أو ليصمت',
    ];

    // الأوّل بُتر آخره، والثاني غاب بتمامه.
    $html = '<p class="text">إنما الأعمال بالنيات وإنما لكل امرئ</p>';

    $findings = Detector::inspectBodyStage($html, $planted, $fixed);

    expect($findings['altered_fixed'])->toHaveCount(1)
        ->and($findings['dropped_fixed'])->toHaveCount(1);
});

/*
 * ══ T-62 — البصمةُ من المتن، والمتنُ من JSON ══
 */

// ★ **هذا ما استبعد `sonnet-5` باطلاً في T-59.** كانت البصمةُ «عن أبي هريرة
// رضي» لثلاثة شواهد، فمتنٌ فيه أحدُها طابق الثلاثة وحُكم على الباقيَين
// بأنّهما «غُيّرا».
it('لا يطابق حديثين لراوٍ واحدٍ ببصمةٍ واحدة', function (): void {
    $first = 'عن أبي هريرةَ رضي الله عنه قال: قال رسولُ الله ﷺ: «شَرُّ مَا فِي رَجُلٍ: شُحٌّ هَالِعٌ، وَجُبْنٌ خَالِعٌ».';
    $second = 'عن أبي هريرةَ رضي الله عنه قال: قال رسولُ الله ﷺ: «ليس الغنى عن كثرة العرض، ولكنّ الغنى غنى النفس».';

    expect(Detector::fragment($first))->not->toBe(Detector::fragment($second))
        ->and(Detector::fragment($first))->toBe('شر ما في رجل');

    // المتنُ نقل الثاني وحده: فالأوّل **لم يُنقل**، لا «غُيّر».
    $findings = Detector::inspectBodyStage('<p>'.$second.'</p>', [], [$first, $second]);

    expect($findings['altered_fixed'])->toBe([])
        ->and($findings['dropped_fixed'])->toBe([$first]);
});

// والإسنادُ الطويل كالقصير: ما بعد ذكر النبيّ ﷺ هو المتن.
it('يأخذ البصمة بعد الإسناد حين لا أقواس', function (): void {
    $text = 'حَدَّثَنَا أَبُو مَعْمَرٍ، حَدَّثَنَا عَبْدُ الْوَارِثِ، عَنْ شَدَّادِ بْنِ أَوْسٍ، عَنِ النَّبِيِّ صلى الله عليه وسلم سَيِّدُ الاِسْتِغْفَارِ أَنْ تَقُولَ';

    expect(Detector::fragment($text))->toBe('سيد الاستغفار ان تقول')
        // والآيةُ بلا إسنادٍ تبقى كما هي.
        ->and(Detector::fragment('﴿إِنَّ الْإِنسَانَ خُلِقَ هَلُوعًا﴾'))->toBe('ان الانسان خلق هلوعا');
});

// المرحلة ٥ تُخرج JSON، وتهريبُ `\n` داخل سلسلةٍ يُلصق كلمتين ببعض.
it('يقرأ الشاهدَ من سلاسل JSON لا من نصّها المهرَّب', function (): void {
    $body = json_encode(['sections' => [['blocks' => [
        ['type' => 'evidence', 'text' => "إنما الأعمال بالنيات\nوإنما لكل امرئ ما نوى"],
    ]]]], JSON_UNESCAPED_UNICODE);

    $findings = Detector::inspectBodyStage($body, [], [['kind' => 'hadith', 'text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى']]);

    expect($findings['altered_fixed'])->toBe([])
        ->and($findings['dropped_fixed'])->toBe([]);
});
