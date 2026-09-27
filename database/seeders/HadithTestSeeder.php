<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HadithBook;
use App\Models\Hadith;
use App\Support\Arabic;
use App\Support\Hadith\Takhrij;
use Illuminate\Database\Seeder;

/**
 * A minimal slice of the seeded corpus for tests — T-06ب.
 *
 * الاختبارات لا تبذر ستّةً وثلاثين ألف حديث. وهذه الصفوف **حقيقية منسوخة
 * من المدوّنة**، لا مصنوعة: هي مرشّحو حالات `H-*` كما يعيدهم
 * `LocalCorpusProvider` على المدوّنة كاملةً.
 *
 * **ولذلك يتطابق ما يقيسه CI وما يقيسه `khulasah:verify-fixtures`.** ومزوّدٌ
 * وهميّ بمتونٍ مجرّدة كان يقيس شيئاً آخر: صفوف المدوّنة تحمل الإسناد مع
 * المتن، وهذا وحده غيّر أربع حالات في العيّنة (T-05ب).
 */
class HadithTestSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{rows: list<array<string, mixed>>} $data */
        $data = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/hadith/corpus-sample.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($data['rows'] as $row) {
            $book = HadithBook::from($row['book']);

            Hadith::query()->updateOrCreate(
                ['book' => $row['book'], 'hadith_number' => $row['hadith_number']],
                [
                    'text' => $row['text'],
                    'text_plain' => Arabic::stripDiacritics($row['text']),
                    'text_normalized' => Arabic::normalize($row['text']),
                    'grade' => $row['grade'],
                    'grade_raw' => $row['grade_raw'],
                    // **بلا `json_encode`**: الحقل مصبوبٌ `array` في النموذج،
                    // فترميزُه هنا يخزّن نصّاً داخل `jsonb` ويعود نصّاً لا مصفوفة.
                    'graders_json' => $row['graders'],
                    'takhrij' => Takhrij::forBook($book),
                ],
            );
        }
    }
}
