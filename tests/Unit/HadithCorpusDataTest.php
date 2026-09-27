<?php

declare(strict_types=1);

use App\Enums\HadithBook;
use App\Support\Hadith\GradeVocabulary;

/**
 * حراسةُ النسخة المحفوظة نفسها — T-05ب.
 *
 * البذر يقرأ من `database/data/hadith/`، وهذه الملفّات ثنائية لا تُراجَع
 * بالعين في فرق. فيحرسها هذا الاختبار: بصماتها، وعدد صفوفها، **وأن يكون
 * كلّ لفظ حكمٍ فيها معروفاً للجدول**.
 */
function hadithData(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $manifest = json_decode(
        (string) file_get_contents(database_path('data/hadith/manifest.json')),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $books = [];

    foreach (HadithBook::all() as $book) {
        $path = database_path("data/hadith/{$book->value}.json.gz");
        $books[$book->value] = json_decode(
            (string) gzdecode((string) file_get_contents($path)),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    return $cache = [$manifest, $books];
}

it('ships a data file for every book of the corpus', function (): void {
    [$manifest] = hadithData();

    foreach (HadithBook::all() as $book) {
        expect(is_file(database_path("data/hadith/{$book->value}.json.gz")))->toBeTrue()
            ->and($manifest['books'])->toHaveKey($book->value);
    }

    expect($manifest['source']['license'])->toContain('Unlicense')
        // **الإصدار مثبَّت ببصمة التزام لا بفرع.** والفرع `1` يتحرّك، فلو
        // بُذر منه لاختلف البذر بين تشغيلين — والمطلوب أن يتكرّر بعد سنة.
        ->and($manifest['source']['commit'])->toMatch('/^[0-9a-f]{40}$/');
});

it('matches every checksum in the manifest', function (): void {
    // ★ ما يفحصه أمر البذر قبل أن يكتب صفّاً — يُفحص هنا في CI كذلك،
    //   فلا يُكتشف تلفُ ملفٍّ على خادمٍ وقت النشر.
    [$manifest] = hadithData();

    foreach (HadithBook::all() as $book) {
        expect(hash_file('sha256', database_path("data/hadith/{$book->value}.json.gz")))
            ->toBe($manifest['books'][$book->value]['sha256']);
    }
});

it('holds exactly the rows the manifest counts', function (): void {
    [$manifest, $books] = hadithData();

    foreach (HadithBook::all() as $book) {
        expect($books[$book->value])->toHaveCount($manifest['books'][$book->value]['rows']);
    }
});

it('carries no row without a matn', function (): void {
    // صفٌّ بلا نصّ لا يُطابَق، ووجودُه يُضخّم العدد ولا يفيد. وفي المصدر
    // ٤٠٩ صفوف كذلك، تُتخطّى وقت التحضير لا وقت البذر.
    [, $books] = hadithData();

    $empty = 0;

    foreach ($books as $rows) {
        foreach ($rows as $row) {
            $empty += trim($row['text']) === '' ? 1 : 0;
        }
    }

    expect($empty)->toBe(0);
});

it('knows every ruling word the corpus actually uses', function (): void {
    // ★ الحارس الذي يمنع أن يمرّ لفظُ حكمٍ صامتاً فيُقرأ «مجهولاً» ويُحبس
    //   حديثٌ صحيح بلا سبب ظاهر. وقد أمسك فعلاً «Hasan Maqtu» ساقطاً من
    //   الجدول عند أوّل بذر.
    [, $books] = hadithData();

    $unknown = [];

    foreach ($books as $rows) {
        foreach ($rows as $row) {
            foreach ($row['grades'] as $entry) {
                if (! GradeVocabulary::knows($entry['grade'])) {
                    $unknown[GradeVocabulary::canonicalize($entry['grade'])] = true;
                }
            }
        }
    }

    expect(array_keys($unknown))->toBe([]);
});

it('leaves the two Sahihs ungraded, as their source does', function (): void {
    // **وهذا صواب لا نقص:** لا مصدر يحكم على أحاديثهما حديثاً حديثاً،
    // لأنّ إخراجهما لها هو الحكم. والبذر هو من يضع «صحيح».
    [, $books] = hadithData();

    foreach ([HadithBook::Bukhari, HadithBook::Muslim] as $book) {
        $graded = array_filter($books[$book->value], fn (array $r): bool => $r['grades'] !== []);

        expect($graded)->toBe([]);
    }
});
