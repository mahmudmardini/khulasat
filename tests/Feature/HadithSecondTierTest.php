<?php

declare(strict_types=1);

use App\Contracts\VerifierRegistry;
use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Enums\MatchStatus;
use App\Models\Hadith;
use App\Support\Arabic;
use App\Support\Hadith\MatnExtractor;
use App\Support\Hadith\Takhrij;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\VerificationResult;
use Database\Seeders\HadithTestSeeder;

/*
 * الطبقة الثانية من المدوّنة — T-170: مسند أحمد وسنن الدارمي، بلا أحكام.
 *
 * **تُسأل بعد الكتب المحكومة لا قبلها.** فالمسندُ يروي كثيراً ممّا في
 * الصحيحين بلفظٍ قريب، ولو زاحمهما لأخرج نسخةً بلا حكمٍ مكانَ نسخةٍ صحيحة.
 * والصفوفُ هنا من ملفّ المسند المحفوظ نفسه، لا مكتوبةٌ في الاختبار.
 */

/** صفٌّ من المسند المحفوظ برقمه، يُبذر كما يبذره `khulasah:seed-hadith`. */
function seedMusnad(string ...$numbers): void
{
    static $rows = null;

    $rows ??= collect(json_decode((string) gzdecode((string) file_get_contents(database_path('data/hadith/ahmad.json.gz'))), true))
        ->keyBy('number');

    foreach ($numbers as $number) {
        $text = $rows[$number]['text'];

        Hadith::query()->create([
            'book' => HadithBook::Ahmad->value,
            'hadith_number' => $number,
            'text' => $text,
            'text_plain' => Arabic::stripDiacritics($text),
            'text_matn' => MatnExtractor::extract($text),
            'text_normalized' => Arabic::normalize($text),
            'grade' => HadithGrade::Unknown->value,
            'grade_raw' => null,
            'graders_json' => null,
            'takhrij' => Takhrij::forBook(HadithBook::Ahmad),
        ]);
    }
}

function verifyAcrossTiers(string $text): VerificationResult
{
    return app(VerifierRegistry::class)
        ->for(DomainPolicy::DEFAULT, 'hadith')
        ->verify(new EvidenceInput(kind: 'hadith', rawText: $text));
}

beforeEach(function (): void {
    $this->seed(HadithTestSeeder::class);
});

it('finds a hadith that is only in Musnad Ahmad, and says it has no stated grade', function (): void {
    seedMusnad('2');

    $r = verifyAcrossTiers('ما من رجل يذنب ذنبا فيتوضأ فيحسن الوضوء');

    expect($r->status)->not->toBe(MatchStatus::None)
        ->and($r->sourceMeta['book'])->toBe('مسند أحمد')
        ->and($r->sourceMeta['hadith_number'])->toBe('2')
        ->and($r->sourceMeta['grade'])->toBe('unknown')
        // **بلا حكمٍ فلا يُنشر** — سياسة البيان، ولا يُشتقّ حكمٌ من كونه في كتاب.
        ->and($r->sourceMeta['has_stated_grade'])->toBeFalse();
});

it('keeps the graded book when the Musnad carries the same hadith', function (): void {
    // ٢٥١٠٣ و٢٥١٣٨ في المسند: «أحبّ الأعمال إلى الله أدومها…».
    seedMusnad('25103', '25138');

    // H-EXACT-01 — في الصحيحين بلفظه، فيبقى بحكمه وتخريجه.
    $exact = verifyAcrossTiers('أحب الأعمال أدومها إلى الله وإن قل');

    expect($exact->status)->toBe(MatchStatus::Exact)
        ->and($exact->sourceMeta['book'])->not->toBe('مسند أحمد')
        ->and($exact->sourceMeta['has_stated_grade'])->toBeTrue();

    // H-NEARMISS-01 — قريبٌ من لفظ الصحيح، وفي المسند ما هو أقربُ منه لفظاً.
    // ★ **ويبقى القريبُ المحكوم** لا الأقربُ الذي لا حكم له.
    $near = verifyAcrossTiers('أحب الأعمال إلى الله أدومها وإن قل');

    expect($near->status)->not->toBe(MatchStatus::None)
        ->and($near->sourceMeta['book'])->not->toBe('مسند أحمد')
        ->and($near->sourceMeta['has_stated_grade'])->toBeTrue();
});

it('accepts only exact matches from the second tier, so an altered hadith is not brought near the Musnad', function (): void {
    seedMusnad('25103', '25138');

    // H-ALTERED-01 — صحيحٌ حُرّف كلمةً. لا يقع في الكتب المحكومة، ويقع «قريباً»
    // من لفظٍ في المسند. ★ **ولا مقاربةَ من كتابٍ بلا حكم** — none تعني none.
    expect(verifyAcrossTiers('أحب الأعمال إلى الله أكثرها وإن قل')->status)->toBe(MatchStatus::None);
});

it('names Musnad Ahmad and al-Darimi in the takhrij', function (): void {
    expect(Takhrij::forBook(HadithBook::Ahmad))->toBe('رواه أحمد')
        ->and(Takhrij::forBook(HadithBook::Darimi))->toBe('رواه الدارمي')
        ->and(HadithBook::primary())->not->toContain(HadithBook::Ahmad)
        ->and(HadithBook::secondary())->toBe([HadithBook::Ahmad, HadithBook::Darimi]);
});
