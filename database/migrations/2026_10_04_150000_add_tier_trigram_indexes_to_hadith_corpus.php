<?php

declare(strict_types=1);

use App\Enums\HadithBook;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * فهرسُ ثلاثيّاتٍ لكلّ طبقةٍ من المدوّنة — T-170.
     *
     * ★ **الفهرسُ الواحد يُرشّح من الطبقتين معاً**: «الله» و«رسول» في عشرات الآلاف
     * من الصفوف، فيُحسب التشابه على صفوف المسند ثمّ تُستبعد بالكتاب بعد الحساب.
     * فلمّا دخل المسندُ تضاعف زمنُ البحث في الكتب المحكومة نفسِها. وفهرسٌ جزئيّ
     * لكلّ طبقة يُبقي بحثَ كلٍّ منها على صفوفها وحدها.
     *
     * **وقائمةُ الكتب هنا بترتيب `HadithBook` نفسه**، كما يكتبها `LocalCorpusProvider`
     * في استعلامه — فيرى المخطِّطُ أنّ شرطَ الاستعلام هو شرطُ الفهرس فيستعمله.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['primary' => HadithBook::primary(), 'secondary' => HadithBook::secondary()] as $tier => $books) {
            $list = implode(', ', array_map(static fn (HadithBook $book): string => "'{$book->value}'", $books));

            DB::statement("CREATE INDEX IF NOT EXISTS hadith_corpus_trgm_{$tier} ON hadith_corpus USING gin (text_normalized gin_trgm_ops) WHERE book IN ({$list})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS hadith_corpus_trgm_primary');
        DB::statement('DROP INDEX IF EXISTS hadith_corpus_trgm_secondary');
    }
};
