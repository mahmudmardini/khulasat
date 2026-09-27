<?php

declare(strict_types=1);

use App\Support\Hadith\MatnExtractor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المتن بلا سند — T-53.
 *
 * **ويُملأ هنا لا بإعادة البذر وحدها.** المدوّنة ستّةٌ وثلاثون ألف صفٍّ
 * مبذورة، وإلزامُ كلّ نسخةٍ قائمة بإعادة بذرها لعمودٍ مشتقٍّ من عمودٍ عندها
 * هدرٌ. والاشتقاق حتميّ، فما يُحسب هنا هو ما يُحسب هناك حرفاً بحرف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hadith_corpus', function (Blueprint $table): void {
            // **يقبل `null`** — وقيمتُه الفارغة تعني «لم يُشتقّ»، فيسقط
            // القارئ إلى `text` كاملاً. ولا يُنشر فارغاً أبداً.
            $table->text('text_matn')->nullable()->after('text_plain');
        });

        DB::table('hadith_corpus')->select('id', 'text')->orderBy('id')
            ->chunk(1000, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('hadith_corpus')->where('id', $row->id)
                        ->update(['text_matn' => MatnExtractor::extract((string) $row->text)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('hadith_corpus', function (Blueprint $table): void {
            $table->dropColumn('text_matn');
        });
    }
};
