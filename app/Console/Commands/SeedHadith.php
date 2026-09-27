<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Models\Hadith;
use App\Support\Arabic;
use App\Support\Hadith\GradeVocabulary;
use App\Support\Hadith\MatnExtractor;
use App\Support\Hadith\Takhrij;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Seeds the hadith corpus from the vendored copy — T-05ب والمواصفة §7-3.
 *
 * **من ملفّاتٍ في المستودع لا من الشبكة.** قرار مالك المنتج ٦ أيلول ٢٠٢٦:
 * مصدرٌ واحد مفتوح برخصة Unlicense، **يُبذر عندنا مرّة ولا يُنادى وقت
 * التشغيل**. وهذا يُسقط أخطر اعتمادية في المشروع بحسب الدراسة: لا واجهة
 * خارجية، ولا حدّ استعمال، ولا حجب، ولا إذن من أحد.
 *
 * **والبذر يجب أن يُعيد النتيجة نفسها بعد سنة.** ولذلك لا CDN ولا فرعٌ
 * متحرّك: الملفّات محفوظة في `database/data/hadith/`، وبصماتها في
 * `manifest.json`، **وتُفحص قبل أن يُكتب صفٌّ واحد**. فإن تبدّل بايت واحد
 * وقف الأمر ولم يبذر نصفَ مدوّنة.
 *
 * وما يُشتقّ يُشتقّ هنا لا في الملفّ: التطبيع من {@see Arabic}، والحكم من
 * {@see GradeVocabulary}، والتخريج من {@see Takhrij}. فتصحيحُ أيّها لا
 * يحتاج إلّا إعادة البذر.
 */
class SeedHadith extends Command
{
    protected $signature = 'khulasah:seed-hadith
                            {--book=* : كتب بعينها بدل الكلّ}
                            {--force : يعيد بذر ما بُذر}';

    protected $description = 'بذر مدوّنة الحديث والأحكام من النسخة المحفوظة في المستودع';

    /** صفوفٌ في الإدراج الواحد — توازنٌ بين عدد الدورات وحجم الاستعلام. */
    private const CHUNK = 500;

    public function handle(): int
    {
        try {
            $manifest = $this->manifest();
        } catch (JsonException) {
            $this->error('تعذّرت قراءة manifest.json — الملفّ ليس JSON صالحاً.');

            return self::FAILURE;
        }

        $books = $this->selectedBooks();

        if ($books === null) {
            return self::FAILURE;
        }

        $this->line('المصدر: '.$manifest['source']['repository']);
        $this->line('الإصدار المثبَّت: '.$manifest['source']['commit']);
        $this->line('الرخصة: '.$manifest['source']['license']);
        $this->newLine();

        // ★ تُفحص البصمات كلّها **قبل** أن يُكتب صفٌّ واحد. والفحص أثناء
        //   البذر يترك المدوّنة نصفَ مبذورة عند أوّل ملفّ فاسد.
        foreach ($books as $book) {
            if (! $this->verifyChecksum($book, $manifest)) {
                return self::FAILURE;
            }
        }

        $unreadable = [];

        foreach ($books as $book) {
            $expected = (int) ($manifest['books'][$book->value]['rows'] ?? 0);

            if (! $this->option('force') && $this->seededCount($book) === $expected) {
                $this->line("  {$book->title()} — مبذور بالفعل ({$expected}). --force لإعادته.");

                continue;
            }

            $this->seedBook($book, $expected, $unreadable);
        }

        $this->newLine();
        $this->info('تمّ. المدوّنة تحمل '.Hadith::count().' حديثاً.');

        $this->reportUnreadableGrades($unreadable);

        return self::SUCCESS;
    }

    /**
     * الكتب المطلوبة — والاسم المجهول يوقف الأمر ولا يُتخطّى صامتاً.
     *
     * @return list<HadithBook>|null
     */
    private function selectedBooks(): ?array
    {
        /** @var list<string> $names */
        $names = $this->option('book');

        if ($names === []) {
            return HadithBook::all();
        }

        $books = [];

        foreach ($names as $name) {
            $book = HadithBook::tryFrom($name);

            if ($book === null) {
                $this->error("لا كتاب باسم «{$name}». والمتاح: ".implode('، ', array_column(HadithBook::all(), 'value')));

                return null;
            }

            $books[] = $book;
        }

        return $books;
    }

    /** @return array{source: array<string, string>, books: array<string, array<string, int|string>>} */
    private function manifest(): array
    {
        /** @var array{source: array<string, string>, books: array<string, array<string, int|string>>} $decoded */
        $decoded = json_decode(
            (string) file_get_contents($this->dataPath('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        return $decoded;
    }

    /** @param array{books: array<string, array<string, int|string>>} $manifest */
    private function verifyChecksum(HadithBook $book, array $manifest): bool
    {
        $path = $this->dataPath("{$book->value}.json.gz");
        $expected = (string) ($manifest['books'][$book->value]['sha256'] ?? '');

        if (! is_file($path)) {
            $this->error("ملفّ «{$book->value}» مفقود على المسار {$path}.");

            return false;
        }

        $actual = hash_file('sha256', $path);

        if ($actual !== $expected) {
            $this->error("بصمة «{$book->value}» لا تطابق manifest.json — لم يُبذر شيء.");
            $this->line("  المنتظر: {$expected}");
            $this->line("  الموجود: {$actual}");

            return false;
        }

        return true;
    }

    /** @param array<string, int> $unreadable */
    private function seedBook(HadithBook $book, int $expected, array &$unreadable): void
    {
        $rows = $this->read($book);

        // الحذف ثمّ الإدراج، لا التحديث: إعادة البذر تعني «اجعل الجدول صورةَ
        // الملفّ»، وصفٌّ حُذف من المصدر يجب أن يختفي لا أن يبقى يتيماً.
        Hadith::query()->where('book', $book->value)->delete();

        $bar = $this->output->createProgressBar(count($rows));
        $bar->setFormat("  {$book->title()}  %current%/%max% [%bar%] %percent:3s%%");
        $bar->start();

        $buffer = [];

        foreach ($rows as $row) {
            $buffer[] = $this->toRecord($book, $row, $unreadable);

            if (count($buffer) >= self::CHUNK) {
                DB::table('hadith_corpus')->insert($buffer);
                $bar->advance(count($buffer));
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('hadith_corpus')->insert($buffer);
            $bar->advance(count($buffer));
        }

        $bar->finish();
        $this->newLine();

        $seeded = $this->seededCount($book);

        if ($seeded !== $expected) {
            $this->warn("  تنبيه: بُذر {$seeded} والمنتظر {$expected}.");
        }
    }

    /**
     * @param  array{number: string, text: string, grades: list<array{name: string, grade: string}>}  $row
     * @param  array<string, int>  $unreadable
     * @return array<string, mixed>
     */
    private function toRecord(HadithBook $book, array $row, array &$unreadable): array
    {
        [$grade, $rawGrade] = $book->isSahihayn()
            // ★ إخراج الشيخين هو الحكم — الاستثناء الوحيد، ولا يُقاس عليه.
            ? [HadithGrade::Sahih, null]
            : GradeVocabulary::strictest($row['grades']);

        foreach ($row['grades'] as $entry) {
            if (! GradeVocabulary::knows($entry['grade'])) {
                $unreadable[GradeVocabulary::canonicalize($entry['grade'])] ??= 0;
                $unreadable[GradeVocabulary::canonicalize($entry['grade'])]++;
            }
        }

        return [
            'book' => $book->value,
            'hadith_number' => $row['number'],
            'text' => $row['text'],
            'text_plain' => Arabic::stripDiacritics($row['text']),
            // **المتنُ بلا سند** — T-53. والسندُ صناعةُ محدّثٍ لا مادّةُ
            // ملخّصٍ يُقرأ، و`text` يبقى كاملاً لمن أراده.
            'text_matn' => MatnExtractor::extract($row['text']),
            'text_normalized' => Arabic::normalize($row['text']),
            'grade' => $grade->value,
            'grade_raw' => $rawGrade,
            'graders_json' => $row['grades'] === []
                ? null
                : json_encode($row['grades'], JSON_UNESCAPED_UNICODE),
            'takhrij' => Takhrij::forBook($book),
        ];
    }

    /** @return list<array{number: string, text: string, grades: list<array{name: string, grade: string}>}> */
    private function read(HadithBook $book): array
    {
        $raw = gzdecode((string) file_get_contents($this->dataPath("{$book->value}.json.gz")));

        /** @var list<array{number: string, text: string, grades: list<array{name: string, grade: string}>}> $rows */
        $rows = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

        return $rows;
    }

    /**
     * **ما لم يُقرأ لفظه يُعرَض** — وهو الإنذار المبكّر إن تبدّل المصدر.
     *
     * فالمجهول لا يمرّ، فلا ضرر فيه على النشر. والضرر أن يصمت النظام عن
     * ألفاظٍ جديدة فتُحبس أحاديثُ صحيحة ولا يدري أحدٌ لماذا.
     *
     * @param  array<string, int>  $unreadable
     */
    private function reportUnreadableGrades(array $unreadable): void
    {
        if ($unreadable === []) {
            return;
        }

        arsort($unreadable);

        $this->newLine();
        $this->warn('ألفاظ أحكام خارج الجدول — تُقرأ «مجهولاً» ولا تمرّ:');

        foreach (array_slice($unreadable, 0, 20, true) as $token => $count) {
            $this->line("  {$token} × {$count}");
        }
    }

    private function seededCount(HadithBook $book): int
    {
        return Hadith::query()->where('book', $book->value)->count();
    }

    private function dataPath(string $file): string
    {
        return database_path("data/hadith/{$file}");
    }
}
