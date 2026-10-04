<?php

declare(strict_types=1);

use App\Contracts\VerifierRegistry;
use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\Hadith;
use App\Support\Arabic;
use App\Support\Hadith\GradeVocabulary;
use App\Support\Hadith\MatnExtractor;
use App\Support\Hadith\Takhrij;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use Database\Seeders\HadithTestSeeder;

/*
 * T-208 — «لا يُنسب حديثٌ دون مصدرٍ وحكمٍ معتمد» (وثيقة المرجعية).
 *
 * الضمانةُ في `DomainPolicy::hasStatedSource` من قبل، ومحروسةٌ وحدها في
 * `DomainPolicyTest`. وهذا يحرسها **من طرفها إلى طرفها** بصفٍّ من المدوّنة
 * المحكومة نفسها: الموطّأ، وفيه ١٠٠٧ من ١٨٢٩ حكمُها «موقوف» أو «مقطوع» —
 * قولُ صحابيٍّ أو تابعيّ لا حديثٌ مرفوع. **فلا تُقرأ درجةً ولا يُنشر.**
 */

/** صفٌّ من الموطّأ المحفوظ برقمه، بحكمه كما يحسبه `khulasah:seed-hadith`. */
function seedMuwatta(string $number): void
{
    $row = collect(json_decode((string) gzdecode((string) file_get_contents(database_path('data/hadith/malik.json.gz'))), true))
        ->firstWhere('number', $number);

    [$grade, $raw] = GradeVocabulary::strictest($row['grades']);

    Hadith::query()->create([
        'book' => HadithBook::Malik->value,
        'hadith_number' => $number,
        'text' => $row['text'],
        'text_plain' => Arabic::stripDiacritics($row['text']),
        'text_matn' => MatnExtractor::extract($row['text']),
        'text_normalized' => Arabic::normalize($row['text']),
        'grade' => $grade->value,
        'grade_raw' => $raw,
        'graders_json' => $row['grades'],
        'takhrij' => Takhrij::forBook(HadithBook::Malik),
    ]);
}

beforeEach(function (): void {
    $this->seed(HadithTestSeeder::class);
});

it('finds a mauquf report in a graded book and removes it, since it carries no hadith grade', function (): void {
    // الموطّأ ١٢ — «موقوف صحيح» عند الهلالي: قولُ القاسم بن محمد.
    seedMuwatta('12');

    $r = app(VerifierRegistry::class)
        ->for(DomainPolicy::DEFAULT, 'hadith')
        ->verify(new EvidenceInput(kind: 'hadith', rawText: 'ما أدركت الناس إلا وهم يصلون الظهر بعشي'));

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['book'])->toBe('موطّأ مالك')
        ->and($r->sourceMeta['grade'])->toBe(HadithGrade::Unknown->value)
        ->and($r->sourceMeta['has_stated_grade'])->toBeFalse()
        ->and(ReviewStatus::decide($r, DomainPolicy::for(DomainPolicy::DEFAULT)))->toBe(ReviewStatus::Removed);
});
