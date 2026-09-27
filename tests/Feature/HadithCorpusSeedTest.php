<?php

declare(strict_types=1);

use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Enums\MatchStatus;
use App\Models\Hadith;
use App\Services\Hadith\LocalCorpusProvider;
use App\Services\Verification\HadithVerifier;
use App\Support\Arabic;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\VerificationResult;

/** يُبذر الموطّأ وحده في أكثر الحالات: أصغر الكتب، وأحكامه منصوصة. */
function seedMalik(): void
{
    test()->artisan('khulasah:seed-hadith', ['--book' => ['malik']])->assertSuccessful();
}

/** صفٌّ يُدسّ يدوياً حين يكون المطلوب حالةً بعينها لا كتاباً كاملاً. */
function corpusRow(HadithBook $book, string $number, string $text, HadithGrade $grade = HadithGrade::Sahih): Hadith
{
    return Hadith::query()->create([
        'book' => $book->value,
        'hadith_number' => $number,
        'text' => $text,
        'text_plain' => Arabic::stripDiacritics($text),
        'text_normalized' => Arabic::normalize($text),
        'grade' => $grade->value,
        'takhrij' => 'رواه '.$book->narratedBy(),
    ]);
}

function verifyAgainstCorpus(string $text): VerificationResult
{
    return (new HadithVerifier([new LocalCorpusProvider], DomainPolicy::for(DomainPolicy::DEFAULT)))
        ->verify(new EvidenceInput(kind: 'hadith', rawText: $text));
}

// ── البذر ───────────────────────────────────────────────────────

it('seeds a book from the copy kept in the repository', function (): void {
    seedMalik();

    expect(Hadith::query()->where('book', 'malik')->count())->toBe(1829);
});

it('fills the three text columns of every row', function (): void {
    // كما في `quran_ayat`: المشكَّل يُنشر، والمجرَّد يُقرأ، والمطبَّع يُطابَق.
    seedMalik();

    $row = Hadith::query()->where('book', 'malik')->first();

    expect($row->text)->not->toBe('')
        ->and($row->text_plain)->toBe(Arabic::stripDiacritics($row->text))
        ->and($row->text_normalized)->toBe(Arabic::normalize($row->text))
        // المشكَّل يحمل حركاتٍ لا تبقى في المجرَّد.
        ->and($row->text)->not->toBe($row->text_plain);
});

it('seeds the same corpus again without duplicating it', function (): void {
    // «يعيد التشغيل بلا تكرار» — معيار قبول T-05ب.
    seedMalik();
    seedMalik();

    expect(Hadith::query()->where('book', 'malik')->count())->toBe(1829);
});

it('rebuilds a book from the file when forced', function (): void {
    seedMalik();
    Hadith::query()->where('book', 'malik')->limit(50)->delete();

    test()->artisan('khulasah:seed-hadith', ['--book' => ['malik'], '--force' => true])->assertSuccessful();

    expect(Hadith::query()->where('book', 'malik')->count())->toBe(1829);
});

it('refuses a book it does not carry instead of seeding nothing quietly', function (): void {
    test()->artisan('khulasah:seed-hadith', ['--book' => ['darimi']])->assertFailed();

    expect(Hadith::query()->count())->toBe(0);
});

it('writes nothing at all when a data file is missing', function (): void {
    // ★ البصمات تُفحص كلّها قبل أوّل صفّ: ملفٌّ ناقصٌ يوقف الأمر، ولا
    //   يترك المدوّنة نصفَ مبذورة فتُطابَق شواهدُ على نصف مصدر.
    $path = database_path('data/hadith/malik.json.gz');
    $moved = $path.'.moved-by-test';

    rename($path, $moved);

    try {
        test()->artisan('khulasah:seed-hadith', ['--book' => ['bukhari', 'malik']])->assertFailed();

        expect(Hadith::query()->count())->toBe(0);
    } finally {
        rename($moved, $path);
    }
});

// ── ★ الصحيحان: إخراج الشيخين هو الحكم ★ ───────────────────────

it('grades everything the two Sahihs narrate as sound', function (): void {
    test()->artisan('khulasah:seed-hadith', ['--book' => ['bukhari']])->assertSuccessful();

    expect(Hadith::query()->where('book', 'bukhari')->where('grade', '!=', 'sahih')->count())->toBe(0)
        // ولا حكم منقول: الصفّ جاء بلا حكم، والصحّة من إخراجه لا من نصّه.
        ->and(Hadith::query()->where('book', 'bukhari')->whereNotNull('grade_raw')->count())->toBe(0);
});

it('does not derive a grade from any other book', function (): void {
    // **الاستثناء واحد ولا يُقاس عليه.** وما لا حكم له في بقيّة الكتب
    // يبقى مجهولاً، والمجهول لا يمرّ.
    seedMalik();

    expect(Hadith::query()->where('book', 'malik')->where('grade', 'unknown')->exists())->toBeTrue();
});

// ── الأحكام: أشدّ المحكِّمين، والجميع محفوظ ────────────────────

it('stores every grader beside the ruling it took', function (): void {
    seedMalik();

    $row = Hadith::query()->where('book', 'malik')->whereNotNull('grade_raw')->first();

    expect($row->graders_json)->toBeArray()->not->toBeEmpty()
        ->and($row->grade)->toBeInstanceOf(HadithGrade::class)
        ->and(array_column($row->graders_json, 'grade'))->toContain($row->grade_raw);
});

// ── المطابقة على المدوّنة المبذورة ─────────────────────────────

it('finds a narration quoted verbatim out of a chain-bearing row', function (): void {
    // صفُّ المدوّنة يبدأ بالإسناد، والاقتباس متنٌ في وسطه. والمحاذاة
    // بالنافذة تتخطّى الصدر والعجز بلا كلفة، فيُقاس على ما اقتُبس.
    corpusRow(
        HadithBook::Bukhari,
        '10',
        'حدثنا آدم قال حدثنا شعبة عن عبد الله بن أبي السفر عن الشعبي عن عبد الله بن عمرو '
        .'رضي الله عنهما عن النبي صلى الله عليه وسلم قال المسلم من سلم المسلمون من لسانه ويده',
    );

    $r = verifyAgainstCorpus('المسلم من سلم المسلمون من لسانه ويده');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['grade'])->toBe('sahih')
        ->and($r->sourceRef)->toBe('رواه البخاري، رقم ١٠');
});

it('calls a narration agreed upon when it matches in both Sahihs', function (): void {
    // ★ «متّفقٌ عليه» تُحسب من فهرسنا ولا تُنقل: المدوّنة لا تحمل جملة
    //   تخريج البتّة، والصحيحان فيها بلا حكم ولا تخريج.
    $matn = 'قال رسول الله صلى الله عليه وسلم من كان يؤمن بالله واليوم الآخر فليقل خيرا أو ليصمت';

    corpusRow(HadithBook::Bukhari, '6018', 'حدثنا قتيبة بن سعيد '.$matn);
    corpusRow(HadithBook::Muslim, '47', 'حدثنا زهير بن حرب '.$matn);

    $r = verifyAgainstCorpus('من كان يؤمن بالله واليوم الآخر فليقل خيرا أو ليصمت');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['takhrij'])->toBe('متّفقٌ عليه: رواه البخاري ومسلم')
        // **ولا رقم مع تخريج كتابين** — الرقم رقمُ صفٍّ لا رقمٌ مشترك.
        ->and($r->sourceRef)->toBe('متّفقٌ عليه: رواه البخاري ومسلم')
        ->and($r->sourceMeta['hadith_number'])->not->toBeNull();
});

it('names one book alone when only that book carries the wording', function (): void {
    corpusRow(HadithBook::Bukhari, '1', 'حدثنا الحميدي قال إنما الأعمال بالنيات وإنما لكل امرئ ما نوى');

    expect(verifyAgainstCorpus('إنما الأعمال بالنيات وإنما لكل امرئ ما نوى')->sourceMeta['takhrij'])
        ->toBe('رواه البخاري');
});

it('reports nothing rather than the nearest row when the corpus is empty', function (): void {
    // «فراغ الجدول ليس إخفاقاً»: تعود الشواهد `none` وتُرفع للمراجعة.
    expect(verifyAgainstCorpus('من داوم على قراءة سورة الكهف رفع الله عنه هم الدنيا')->status)
        ->toBe(MatchStatus::None);
});

it('never returns a fabricated narration the corpus does not carry', function (): void {
    seedMalik();

    foreach (['حب الوطن من الإيمان وحسن الخلق نصف الدين', 'من عرف نفسه فقد عرف ربه حقا وصدقا'] as $text) {
        expect(verifyAgainstCorpus($text)->status)->toBe(MatchStatus::None);
    }
});
