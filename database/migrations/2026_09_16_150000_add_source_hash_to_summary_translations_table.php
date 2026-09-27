<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use App\Actions\Stages\TranslateSummary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بصمةُ المصدر المترجَم — T-141.
 *
 * ★★ **وعلّتُها أنّ إعادةَ النشر كانت تُعيد ترجمةً مدفوعةً بلا سؤال.**
 *
 * فزرُّ «حدّثْ المنشور» (T-30) يمرّ على {@see RenderAndPublish}،
 * وهذه تنادي {@see TranslateSummary} لكلّ لغةٍ غير عربية
 * **بلا نظرٍ إلى ترجمةٍ محفوظة**. فكلُّ ضغطةٍ نداءٌ يُدفع ثمنُه على عملٍ
 * قائمٍ في الجدول — وقد صُرف على ملخّصين ~$0.65 في ضغطةٍ واحدة على الخادم.
 *
 * ★ **والتجاوزُ ببصمةٍ لا بوجود الصفّ.**
 *
 * فوجودُ الصفّ يقول «تُرجم مرّةً»، ولا يقول «تُرجم **هذا** المتن». وملخّصٌ
 * أُعيدت كتابتُه متنُه غيرُ متنِه، وترجمةٌ قديمةٌ تُعرض له **أسوأ من نداءٍ
 * يُدفع**: صفحةٌ بلغةٍ لا تطابق أصلَها، ولا أحدَ يُخبر بذلك.
 *
 * والبصمةُ بصمةُ **النصوص المرسَلة فعلاً** إلى النموذج، لا بصمةُ `body_json`
 * كلِّه: فيه مفاتيحُ عرضٍ وأصنافُ كتلٍ لا تُترجَم، وتبدُّلُها يُوجب نداءً
 * بلا سبب.
 *
 * **وتقبل الفراغ**: صفوفُ ما قبل هذه الهجرة لا بصمةَ لها، **فتُترجَم مرّةً
 * أخيرةً ثمّ تستقرّ**. ونسبتُها إلى بصمةٍ محسوبةٍ اليوم تفترض أنّ متنَها لم
 * يتبدّل قطّ — وهو ما لا نعلمه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_translations', function (Blueprint $table): void {
            $table->string('source_hash', 64)->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('summary_translations', function (Blueprint $table): void {
            $table->dropColumn('source_hash');
        });
    }
};
