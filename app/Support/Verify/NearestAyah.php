<?php

declare(strict_types=1);

namespace App\Support\Verify;

use App\Models\QuranAyah;
use App\Support\Arabic;
use Illuminate\Support\Facades\DB;

/**
 * أقربُ آيةٍ إلى نصٍّ لم يطابق المصحف — **لأداة «تحقّق» وحدها**، T-182.
 *
 * ★ **ليست من طبقة التحقّق ولا تغيّر حكمها.** `QuranVerifier` قال إنّ اللفظ
 * لا يطابق، وهذا يبقى: لا يُنشر شيءٌ بها، ولا يُستبدل بها لفظُ النصّ في ملخّص.
 * وإنّما تُعرض للباحث مقارنةً، بموضعها في المصحف وبالكلمة المختلفة مظلَّلة،
 * كما تطلب حالةُ «آيةٌ منقولة بخطأ» في وثيقة المرجعية: «التنبيه على النصّ
 * الصحيح بلطف، وإظهار السورة والآية».
 *
 * **وحتميّةٌ بلا نموذج:** قائمةٌ قصيرة من فهرس التشابه في المصحف، ثمّ مسافةُ
 * تحريرٍ على الكلمات تُقاس على أفضل نافذةٍ في الآية. وما لم يبلغ الحدَّين لا
 * يُعرض، فلا يُقال عن كلامٍ ليس قرآناً إنّه «قريبٌ من آية».
 */
final class NearestAyah
{
    /** أقلّ كلمات النصّ ليُبحث له عن آية: ما دونها يشبه آياتٍ كثيرة صدفةً. */
    private const MIN_WORDS = 4;

    /** أقلّ التشابه على الكلمات ليُعرض أقربَ آية. */
    private const MIN_SIMILARITY = 0.6;

    /** حدُّ القائمة القصيرة في فهرس التشابه، وعددُ مرشّحيها. */
    private const SHORTLIST_THRESHOLD = 0.4;

    private const CANDIDATES = 10;

    /** @return array{ayah: QuranAyah, similarity: float}|null */
    public static function find(string $text): ?array
    {
        $needle = Arabic::normalize($text);
        $quote = $needle === '' ? [] : explode(' ', $needle);

        if (count($quote) < self::MIN_WORDS) {
            return null;
        }

        DB::statement('SET pg_trgm.word_similarity_threshold = '.self::SHORTLIST_THRESHOLD);

        $candidates = QuranAyah::query()
            ->selectRaw('*, word_similarity(?, text_normalized) as trgm_similarity', [$needle])
            ->whereRaw('? <% text_normalized', [$needle])
            ->orderByDesc('trgm_similarity')
            ->orderBy('surah')
            ->orderBy('ayah')
            ->limit(self::CANDIDATES)
            ->get();

        $best = null;

        foreach ($candidates as $ayah) {
            $source = explode(' ', (string) $ayah->text_normalized);
            $similarity = max(0.0, 1 - (self::windowDistance($quote, $source) / count($quote)));

            if ($best === null || $similarity > $best['similarity']) {
                $best = ['ayah' => $ayah, 'similarity' => $similarity];
            }
        }

        return $best !== null && $best['similarity'] >= self::MIN_SIMILARITY ? $best : null;
    }

    /**
     * مسافةُ تحريرٍ على الكلمات، يُتخطّى فيها صدرُ الآية وعجزُها بلا كلفة —
     * فالنصّ يقتبس بعض الآية. كما يقيس `HadithVerifier` الاقتباس.
     *
     * @param  list<string>  $quote
     * @param  list<string>  $source
     */
    private static function windowDistance(array $quote, array $source): int
    {
        $previous = array_fill(0, count($source) + 1, 0);

        foreach ($quote as $i => $word) {
            $current = [$i + 1];

            foreach ($source as $j => $sourceWord) {
                $current[$j + 1] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($word === $sourceWord ? 0 : 1),
                );
            }

            $previous = $current;
        }

        return min($previous);
    }
}
