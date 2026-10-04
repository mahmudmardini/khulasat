<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Models\QuranAyah;
use App\Services\Verification\QuranVerifier;
use App\Support\Arabic;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\VerificationResult;
use Database\Seeders\QuranTestSeeder;

beforeEach(function (): void {
    $this->seed(QuranTestSeeder::class);
    $this->verifier = new QuranVerifier;
});

function verifyAyah(string $text): VerificationResult
{
    return test()->verifier->verify(new EvidenceInput(kind: 'ayah', rawText: $text));
}

// ── حالات العيّنة الحاكمة — fixtures/evidence-fixtures.json ──────

it('matches a whole ayah and returns it in Uthmani script', function (): void {
    // Q-EXACT-01
    $r = verifyAyah('من عمل صالحا من ذكر أو أنثى وهو مؤمن فلنحيينه حياة طيبة ولنجزينهم أجرهم بأحسن ما كانوا يعملون');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['surah_number'])->toBe(16)
        ->and($r->sourceMeta['ayah_number'])->toBe(97)
        // اللفظ المُعاد عثماني لا إملائي — فلا يرى القارئ إلا رسم المصحف.
        ->and($r->matchedText)->toContain('حَيَوٰةً');
});

it('matches an undiacritized ayah with different hamza forms', function (): void {
    // Q-EXACT-02 — يختبر التطبيع
    $r = verifyAyah('انما يخشى الله من عباده العلماء');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['surah_number'])->toBe(35)
        ->and($r->sourceMeta['ayah_number'])->toBe(28);
});

it('matches across two consecutive ayat of one surah', function (): void {
    // Q-EXACT-03
    $r = verifyAyah('إن الإنسان خلق هلوعا إذا مسه الشر جزوعا');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['surah_number'])->toBe(70)
        ->and($r->sourceMeta['ayah_number'])->toBe(19)
        ->and($r->sourceMeta['spans_multiple'])->toBeTrue();
});

it('marks a fragment of an ayah as such', function (): void {
    // Q-PARTIAL-01
    $r = verifyAyah('ولنجزينهم أجرهم بأحسن ما كانوا يعملون');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['surah_number'])->toBe(16)
        ->and($r->sourceMeta['ayah_number'])->toBe(97)
        ->and($r->sourceMeta['is_fragment'])->toBeTrue();
});

// ── ما يجب ألّا يُطابَق — وهذه أهمّ الحالات ──────────────────────

it('refuses text stitched from two different surahs', function (): void {
    // Q-STITCHED-01 — أهمّ حالة في العيّنة.
    // تمريرها يعني أنّ النظام يخترع آيات.
    $r = verifyAyah('إن الإنسان خلق هلوعا وهو مؤمن فلنحيينه حياة طيبة');

    expect($r->status)->toBe(MatchStatus::None)
        ->and($r->matchedText)->toBeNull();
});

it('refuses an ayah with one word swapped', function (): void {
    // Q-ALTERED-01 — «مؤمن» ← «مسلم»
    $r = verifyAyah('من عمل صالحا من ذكر أو أنثى وهو مسلم فلنحيينه حياة طيبة');

    expect($r->status)->toBe(MatchStatus::None);
});

it('never guesses the nearest ayah', function (string $forged): void {
    // المواصفة §7-2: لا تخمين، ولا أقرب تطابق، ولا سؤال نموذج.
    expect(verifyAyah($forged)->status)->toBe(MatchStatus::None);
})->with([
    'نصّ مخترَع' => ['من داوم على قراءة سورة الكهف كل خميس رفع الله عنه هم الدنيا'],
    'آية بزيادة مقحمة' => ['إنما يخشى الله من عباده العلماء الصالحين'],
    'كلام عادي' => ['الحمد لله رب العالمين والصلاة والسلام على أشرف المرسلين وبعد'],
    'نصّ فارغ' => [''],
    'مسافات فقط' => ['   '],
]);

it('does not span two ayat from different surahs', function (): void {
    // آخر آية في سورة وأوّل آية في التي تليها ليستا متتاليتين.
    $r = verifyAyah('وما لهم من دونه من وال إن الإنسان خلق هلوعا');

    expect($r->status)->toBe(MatchStatus::None);
});

/*
 * ═══ T-168 — آيتان متتاليتان تُطابَقان أينما بدأ الاقتباس ═══
 *
 * كان المِجسّ يشترط أن تكون أوّلُ أربع كلماتٍ من الاقتباس آخرَ أربعٍ في
 * الآية الأولى بالضبط، فلا يُطابَق الممتدّ إلّا صدفة. والقلم ١٠–١١ بلفظها
 * التامّ كانت تعود `none`.
 */
function seedQalam(): void
{
    // القلم ١٠–١١ — ليستا في عيّنة الرسم، فتُضافان هنا بلفظهما.
    foreach ([
        10 => ['وَلَا تُطِعْ كُلَّ حَلَّافٍ مَّهِينٍ', 'وَلَا تُطِعْ كُلَّ حَلَّافٍ مَّهِينٍ'],
        11 => ['هَمَّازٍ مَّشَّآءٍۭ بِنَمِيمٍ', 'هَمَّازٍ مَّشَّاءٍ بِنَمِيمٍ'],
    ] as $ayah => [$uthmani, $imlaei]) {
        QuranAyah::query()->create([
            'surah' => 68,
            'ayah' => $ayah,
            'surah_name_ar' => 'القلم',
            'text_uthmani' => $uthmani,
            'text_imlaei' => $imlaei,
            'text_normalized' => Arabic::normalize($imlaei),
        ]);
    }
}

it('matches two whole consecutive ayat', function (): void {
    seedQalam();

    $r = verifyAyah('ولا تطع كل حلاف مهين هماز مشاء بنميم');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta)->toMatchArray(['surah_number' => 68, 'ayah_number' => 10, 'ayah_number_end' => 11, 'spans_multiple' => true]);
});

it('matches a span that starts early in the first ayah', function (): void {
    // تبدأ قبل آخر الأولى بثلاث كلمات — والمِجسّ القديم يأخذ أربعاً فيتجاوزها.
    $r = verifyAyah('الإنسان خلق هلوعا إذا مسه الشر جزوعا');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta)->toMatchArray(['surah_number' => 70, 'ayah_number' => 19, 'ayah_number_end' => 20]);
});

it('matches a span whose part in the first ayah is shorter than four words', function (): void {
    seedQalam();

    expect(verifyAyah('خلق هلوعا إذا مسه الشر')->sourceMeta)->toMatchArray(['ayah_number' => 19, 'ayah_number_end' => 20])
        ->and(verifyAyah('حلاف مهين هماز مشاء')->sourceMeta)->toMatchArray(['surah_number' => 68, 'ayah_number' => 10]);
});

it('splits only at word boundaries, never inside a word', function (): void {
    seedQalam();

    // «هين» بعضُ «مهين»، والوصلُ لا يقع داخل كلمة.
    expect(verifyAyah('هين هماز مشاء بنميم')->status)->toBe(MatchStatus::None);
});
