<?php

declare(strict_types=1);

use App\Support\Hadith\MatnExtractor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * إعادةُ اشتقاق المتن بعد T-116 — المدوّنةُ وشواهدُ المهامّ القائمة.
 *
 * **وأثرُ إصلاح المستخرِج لا يبلغ القائمَ من تلقاء نفسه**: `text_matn`
 * عمودٌ مشتقٌّ مرّةً عند البذر، و`evidence_items.matched_text` لفظٌ مثبَّتٌ
 * وقتَ التحقّق. فمهمّةٌ أُنشئت أمسِ تبقى بإسنادها وإن أُصلح المستخرِج اليوم.
 *
 * **والأثرُ يمتدّ إلى كلّ شيء — قرارُ مالك المنتج، ١٢ أيلول ٢٠٢٦.**
 *
 * ★ **ولفظُ المراجع لا يُنقض**: الاستخراجُ قطعٌ لا تبديل، فما قطعه المستخرِج
 * من الإسناد يبقى ما بعده حرفاً بحرف كما أقرّه المراجع. ولا يُمسّ إلّا
 * الحديث: الآيةُ لفظُها لفظُ المصحف، ولا سندَ لها يُقطع.
 *
 * @see app/Support/Hadith/MatnExtractor.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // ١. المدوّنة — ٣٥٩٨٢ صفّاً، ومنها يُبنى كلُّ استخراجٍ جديد.
        DB::table('hadith_corpus')->select('id', 'text')->orderBy('id')
            ->chunk(1000, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hadith_corpus')->where('id', $row->id)
                        ->update(['text_matn' => MatnExtractor::extract((string) $row->text)]);
                }
            });

        // ٢. شواهدُ المهامّ القائمة — وما لم يتغيّر لفظُه لا يُكتب.
        DB::table('evidence_items')
            ->where('kind', 'hadith')
            ->whereNotNull('matched_text')
            ->select('id', 'matched_text')
            ->orderBy('id')
            ->chunk(500, static function ($rows): void {
                foreach ($rows as $row) {
                    $matn = MatnExtractor::extract((string) $row->matched_text);

                    if ($matn === (string) $row->matched_text || trim($matn) === '') {
                        continue;
                    }

                    DB::table('evidence_items')->where('id', $row->id)
                        ->update(['matched_text' => $matn]);
                }
            });
    }

    /**
     * **ولا رجعةَ لها.** الإسنادُ المقطوع لا يُستعاد من المتن — ولو استُعيد
     * لعاد الخطأُ الذي أُصلح. ومن أرادها فالمدوّنةُ تُبذر من مصدرها.
     */
    public function down(): void {}
};
