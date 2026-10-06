<?php

declare(strict_types=1);

namespace App\Services\Hadith;

use App\Contracts\HadithProvider;
use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Models\Hadith;
use App\Support\Verification\HadithMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The seeded corpus — **and the only provider** — المواصفة §7-3.
 *
 * كان خلف هذه الواجهة مزوّدان: نسخة محلّية وواجهة تخريج خارجية. **وأُلغي
 * الخارجي بالكلّية** بقرار مالك المنتج في ٦ أيلول ٢٠٢٦، لأنّ الاستقلال عن
 * إذن غيرنا شرطٌ في المنتج. وبقيت الواجهة على حالها لأنّها تصف علاقةً لا
 * عدداً: **سقوط المزوّد لا يُسقط النظام**، وهي تحمي المهمّة من عطبٍ في
 * قاعدة البيانات كما كانت تحميها من عطبٍ في الشبكة.
 *
 * والجدول فارغٌ إلى أن يُبذر بـ`khulasah:seed-hadith`، **وفراغه ليس إخفاقاً**:
 * يعود المزوّد بلا نتائج، فتعود الشواهد `none` وتُرفع للمراجعة، والنظام يعمل.
 */
class LocalCorpusProvider implements HadithProvider
{
    /**
     * ما يُعرَض على حساب المسافة الدقيق.
     *
     * فهرس الثلاثيّات يُرشّح ولا يحكم: يقرّب بسرعة، ثمّ يقيس `HadithVerifier`
     * المسافة على الكلمات. وعشرون مرشّحاً تكفي — والحديث الواحد قد يرد في
     * ستّة كتب بألفاظ متقاربة، فلا يُضيَّق العدد حتى يسقط الصحيحان معاً
     * فيضيع «متّفقٌ عليه».
     */
    private const CANDIDATES = 20;

    /**
     * عتبة الترشيح — **دون عتبة `partial` عمداً**.
     *
     * `word_similarity` يقيس الثلاثيّات، و`HadithVerifier` يقيس الكلمات،
     * وهما مقياسان مختلفان. فلو رُشّح على ٠٫٧٥ لسقط قبل الحساب اقتباسٌ
     * كان سيبلغها بالكلمات. والترشيح الواسع يُصحّحه الحساب بعده، والضيّق
     * لا يُصحّحه شيء.
     */
    private const SHORTLIST_THRESHOLD = 0.45;

    public function name(): string
    {
        return 'local_corpus';
    }

    /**
     * الكتبُ المحكومة — ستّةُ الكتب والموطّأ.
     *
     * @return list<HadithBook>
     */
    protected function books(): array
    {
        return HadithBook::primary();
    }

    /** @return list<HadithMatch> */
    public function search(string $normalized): array
    {
        if ($normalized === '' || ! Schema::hasTable('hadith_corpus')) {
            return [];
        }

        DB::statement('SET pg_trgm.word_similarity_threshold = '.self::SHORTLIST_THRESHOLD);

        return Hadith::query()
            // `word_similarity(a, b)` يقيس أفضل **امتدادٍ متّصل** من b يشبه a،
            // لا يقيس b كلّه. وهذا عين ما نحتاج: الاقتباس شذرةٌ داخل متنٍ
            // مصدَّرٍ بإسناده، و`similarity` العادية تغرقه بطول الإسناد.
            ->selectRaw('*, word_similarity(?, text_normalized) as trgm_similarity', [$normalized])
            ->whereRaw('? <% text_normalized', [$normalized])
            // كتبُ هذه الطبقة وحدها — T-170. والطبقةُ الثانية مزوّدٌ بعده.
            ->whereIn('book', array_map(static fn (HadithBook $book): string => $book->value, $this->books()))
            ->orderByDesc('trgm_similarity')
            // ★ **وترتيبُ المتساوين ثابت** — T-219. Postgres لا يضمن ترتيبَ
            // صفوفٍ تتساوى في التشابه، فكان المدخلُ الواحد يُعزى إلى كتابٍ مرّة
            // وإلى غيره أخرى، ويقطع الحدُّ أعلاها رتبةً. فالكتابُ برتبته، ثمّ الصفّ.
            ->orderByRaw('array_position(?::text[], book)', ['{'.implode(',', array_map(
                static fn (HadithBook $book): string => $book->value,
                HadithBook::cases(),
            )).'}'])
            ->orderBy('id')
            ->limit(self::CANDIDATES)
            ->get()
            ->map(static fn (Hadith $row): HadithMatch => new HadithMatch(
                /*
                 * **المتنُ لا النصُّ الكامل** — T-53. كان يُنشر بسنده كلِّه:
                 * «حَدَّثَنَا مَحْمُودُ بْنُ غَيْلاَنَ، قَالَ حَدَّثَنَا أَبُو
                 * عَاصِمٍ…» ثمّ المتن ثمّ «تَابَعَهُ يُونُسُ». وقارئُ صفحةٍ
                 * منشورة يريد المتن.
                 *
                 * **والمطابقةُ تبقى على `text_normalized` الكامل** أعلاه:
                 * الاقتباسُ قد يقع في موضعٍ يُدركه السند، وتضييقُ ما يُطابَق
                 * عليه يُسقط شواهدَ صحيحة. فما يُطابَق شيءٌ وما يُنشر شيء.
                 */
                text: trim((string) $row->text_matn) !== '' ? (string) $row->text_matn : $row->text,
                book: HadithBook::from($row->book)->title(),
                hadithNumber: $row->hadith_number,
                ruling: $row->grade_raw,
                takhrij: $row->takhrij,
                grade: $row->grade instanceof HadithGrade ? $row->grade : HadithGrade::Unknown,
                bookKey: HadithBook::from($row->book),
                graders: $row->graders_json ?? [],
            ))
            ->all();
    }
}
