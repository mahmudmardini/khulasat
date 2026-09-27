<?php

declare(strict_types=1);

use App\Enums\HadithGrade;
use App\Support\Hadith\GradeVocabulary;

// ── التقنين قبل البحث ───────────────────────────────────────────

it('strips the parenthesised cross-references before looking a ruling up', function (): void {
    // «Sahih Bukhari (1023) Sahih Muslim (894)» إحالةٌ إلى رقمَي الحديث،
    // ولولا حذفها لصار كلّ رقمٍ لفظاً جديداً — ألفٌ وستّمئة بدل ستّة وستّين.
    expect(GradeVocabulary::canonicalize('Sahih Bukhari (1023) Sahih Muslim (894)'))
        ->toBe('sahih bukhari sahih muslim')
        ->and(GradeVocabulary::canonicalize('  Sahih   Muslim (1480) '))->toBe('sahih muslim');
});

it('maps a ruling whatever its case and spacing', function (): void {
    expect(GradeVocabulary::map('SAHIH'))->toBe(HadithGrade::Sahih)
        ->and(GradeVocabulary::map(' hasan  sahih '))->toBe(HadithGrade::Hasan);
});

// ── ★ الجدول كلّه — ستّة وستّون لفظاً ★ ──────────────────────────

it('maps every ruling word in the corpus', function (string $raw, HadithGrade $expected): void {
    expect(GradeVocabulary::map($raw))->toBe($expected)
        ->and(GradeVocabulary::knows($raw))->toBeTrue();
})->with([
    // ── يمرّ: صحيح ─────────────────────────────────────────────
    ['Sahih', HadithGrade::Sahih],
    ['Sahih Hadith', HadithGrade::Sahih],
    ['Sahih Matn', HadithGrade::Sahih],
    ['Sahih Mutawatir', HadithGrade::Sahih],
    ['Sahih Lighairihi', HadithGrade::Sahih],
    ['Sahih - Agreed Upon', HadithGrade::Sahih],
    ['Sahih - Bukhari And Muslim', HadithGrade::Sahih],
    ['Sahih Bukhari', HadithGrade::Sahih],
    ['Sahih Muslim', HadithGrade::Sahih],
    ['Sahih Bukhari Sahih Muslim', HadithGrade::Sahih],

    // ── يمرّ: حسن ──────────────────────────────────────────────
    ['Hasan', HadithGrade::Hasan],
    ['Hasan Lighairihi', HadithGrade::Hasan],
    ['Hasan Sahih', HadithGrade::Hasan],

    // ── لا يمرّ: ضعيف ──────────────────────────────────────────
    ['Daif', HadithGrade::Daif],
    ['Very Daif', HadithGrade::Daif],
    ['Daif Isnaad', HadithGrade::Daif],
    ['Isnaad Daif', HadithGrade::Daif],
    ['Sanad Daif', HadithGrade::Daif],
    ['Very Daif Isnaad', HadithGrade::Daif],
    ['Isnaad Malool', HadithGrade::Daif],
    ['Shadh', HadithGrade::Daif],
    ['Munkar', HadithGrade::Daif],
    ['Daif Munkar', HadithGrade::Daif],
    ['Munkar Daif', HadithGrade::Daif],

    // ── لا يمرّ: موضوع ─────────────────────────────────────────
    ['Mawdu', HadithGrade::Mawdu],
    ['Batil', HadithGrade::Mawdu],

    // ── لا يمرّ: صحّة إسنادٍ لا صحّة متن ───────────────────────
    ['Isnaad Sahih', HadithGrade::Unknown],
    ['Sahih Isnaad', HadithGrade::Unknown],
    ['Isnaad Hasan', HadithGrade::Unknown],
    ['Hasan Isnaad', HadithGrade::Unknown],
    ['Hasan Sahih Isnaad', HadithGrade::Unknown],
    ['Isnaad Sahih Agreed Upon', HadithGrade::Unknown],
    ['Isnaad Sahih Bukhari And Muslim', HadithGrade::Unknown],
    ['Isnaad Sahih Sahih Bukhari', HadithGrade::Unknown],
    ['Isnaad Sahih Sahih Muslim', HadithGrade::Unknown],
    ['Isnaad Sahih Sahih Bukhari Sahih Muslim', HadithGrade::Unknown],
    ['Isnaad Hasan Sahih Bukhari', HadithGrade::Unknown],
    ['Isnaad Hasan Sahih Muslim', HadithGrade::Unknown],
    ['Isnaad Hasan Sahih Bukhari Sahih Muslim', HadithGrade::Unknown],
    ['Sahih - Isnaad Hasan Bukhari And Muslim', HadithGrade::Unknown],

    // ── لا يمرّ: موقوف ومقطوع ومرسل ───────────────────────────
    ['Maqtu', HadithGrade::Unknown],
    ['Maqtu Sahih', HadithGrade::Unknown],
    ['Maqtu Hasan', HadithGrade::Unknown],
    ['Hasan Maqtu', HadithGrade::Unknown],
    ['Maqtu Daif', HadithGrade::Unknown],
    ['Maqtu Sahih Lighairihi', HadithGrade::Unknown],
    ['Sahih Maqtu', HadithGrade::Unknown],
    ['Daif Maqtu', HadithGrade::Unknown],
    ['Sahih Isnaad Maqtu', HadithGrade::Unknown],
    ['Daif Isnaad Maqtu', HadithGrade::Unknown],
    ['Mauquf', HadithGrade::Unknown],
    ['Mauquf Sahih', HadithGrade::Unknown],
    ['Mauquf Hasan', HadithGrade::Unknown],
    ['Mauquf Daif', HadithGrade::Unknown],
    ['Mauquf Munkar', HadithGrade::Unknown],
    ['Mauquf Sahih Lighairihi', HadithGrade::Unknown],
    ['Mauquf Hasan Lighairihi', HadithGrade::Unknown],
    ['Sahih Muquf', HadithGrade::Unknown],
    ['Daif Muquf', HadithGrade::Unknown],
    ['Sahih Isnaad Mauquf', HadithGrade::Unknown],
    ['Mursal', HadithGrade::Unknown],
    ['Mursal Sahih Isnaad', HadithGrade::Unknown],
    ['Sahih Isnaad Mursal', HadithGrade::Unknown],

    // ── لا يمرّ: متعارض أو غامض أو فارغ ───────────────────────
    ['Shadh, Sahih', HadithGrade::Unknown],
    ['Sahih Witness Sahih Muslim', HadithGrade::Unknown],
    ['-', HadithGrade::Unknown],
]);

// ── ★ الإخفاق في الاتّجاه الآمن ★ ───────────────────────────────

it('never lets a sound isnad stand for a sound matn', function (string $raw): void {
    // صحّة الإسناد ليست صحّة المتن، وبينهما فرق يعرفه أهل الفنّ:
    // إسنادٌ صحيح ومتنٌ شاذّ أو معلّ. فلا يمرّ ولا يُوصَف بضعف.
    expect(GradeVocabulary::map($raw))->toBe(HadithGrade::Unknown)
        ->and(GradeVocabulary::map($raw)->mayAutoPass())->toBeFalse();
})->with(['Isnaad Sahih', 'Sahih Isnaad', 'Isnaad Hasan', 'Hasan Isnaad']);

it('never lets a chain description stand for a strength ruling', function (string $raw): void {
    // المقطوع والموقوف والمرسل ليست مرفوعةً إلى النبيّ ﷺ أصلاً،
    // فلا تُنشر حديثاً ولو صحّ إسنادها إلى قائلها.
    expect(GradeVocabulary::map($raw))->toBe(HadithGrade::Unknown);
})->with(['Maqtu', 'Mauquf', 'Mursal', 'Maqtu Sahih', 'Mauquf Sahih', 'Sahih Isnaad Mursal']);

it('reads an unlisted ruling as unknown rather than guessing', function (?string $raw): void {
    // ★ الحاجز الأخير: لفظٌ جديدٌ في المصدر لا يُشتقّ حكمه بالتخمين.
    expect(GradeVocabulary::map($raw))->toBe(HadithGrade::Unknown)
        ->and(GradeVocabulary::map($raw)->mayAutoPass())->toBeFalse();
})->with([
    'لفظ لم يرد قطّ' => ['Sahih Jiddan Wa Aktharu'],
    'عربية في حقل لاتيني' => ['صحيح'],
    'فارغ' => [''],
    'معدوم' => [null],
]);

// ── ★ اختلاف المحكِّمين: يُؤخذ بأشدّهم ★ ────────────────────────

it('takes the strictest of disagreeing scholars', function (): void {
    // الحالة المقيسة في المدوّنة نفسها — T-05ب.
    [$grade, $raw] = GradeVocabulary::strictest([
        ['name' => 'Al-Albani', 'grade' => 'Shadh'],
        ['name' => 'Zubair Ali Zai', 'grade' => 'Isnaad Sahih'],
    ]);

    expect($grade)->toBe(HadithGrade::Daif)
        ->and($raw)->toBe('Shadh');
});

it('prefers weakness to soundness however the graders are ordered', function (): void {
    $rows = [
        ['name' => 'A', 'grade' => 'Sahih'],
        ['name' => 'B', 'grade' => 'Daif'],
        ['name' => 'C', 'grade' => 'Hasan'],
    ];

    expect(GradeVocabulary::strictest($rows)[0])->toBe(HadithGrade::Daif)
        ->and(GradeVocabulary::strictest(array_reverse($rows))[0])->toBe(HadithGrade::Daif);
});

it('puts fabrication above every other verdict', function (): void {
    expect(GradeVocabulary::strictest([
        ['name' => 'A', 'grade' => 'Sahih'],
        ['name' => 'B', 'grade' => 'Mawdu'],
        ['name' => 'C', 'grade' => 'Daif'],
    ])[0])->toBe(HadithGrade::Mawdu);
});

it('ignores an unreadable ruling while any scholar spoke plainly', function (): void {
    // «مجهول» غيابُ حكمٍ لا حكم. ولو عُدّ أشدَّ الجميع لنقض حكماً منصوصاً
    // بلفظٍ لم يُقرأ، فضاع تصريح المحكِّم لأجل غموض غيره.
    expect(GradeVocabulary::strictest([
        ['name' => 'A', 'grade' => 'Maqtu Sahih'],
        ['name' => 'B', 'grade' => 'Hasan'],
    ])[0])->toBe(HadithGrade::Hasan);
});

it('returns unknown when no scholar spoke a readable ruling', function (): void {
    [$grade, $raw] = GradeVocabulary::strictest([
        ['name' => 'A', 'grade' => 'Isnaad Sahih'],
        ['name' => 'B', 'grade' => '-'],
    ]);

    expect($grade)->toBe(HadithGrade::Unknown)->and($raw)->toBeNull();
});

it('returns unknown for a row no scholar graded', function (): void {
    expect(GradeVocabulary::strictest([])[0])->toBe(HadithGrade::Unknown);
});
