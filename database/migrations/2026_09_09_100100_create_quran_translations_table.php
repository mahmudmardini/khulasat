<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترجمات القرآن المعتمدة — T-38، وقرار مالك المنتج في 8 أيلول 2026.
 *
 * ★★ **ولا يترجم نموذجٌ آيةً.** الترجمات المعتمدة منشورةٌ في المصدر الذي
 * نبذر منه النصّ أصلاً (`api.quran.com/api/v4`)، فتُبذر كما بُذر. و«نموذجٌ
 * يترجم آيةً وثمّ ترجمةٌ معتمدة منشورة، مخاطرةٌ بلا مقابل».
 *
 * وهذا يجعل ترجمة الآية **حتميّةً كمطابقتها**: تُقرأ من الجدول لا تُولَّد،
 * فلا تتبدّل بين تشغيلين ولا يمسّها اختيارُ نموذج.
 *
 * و`translation_id` محفوظٌ في كلّ صفّ لا في الإعداد وحده: من رأى صفّاً عرف
 * أيَّ ترجمةٍ هو، ولو تبدّل الاختيار في الكود بعدها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_translations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('ayah');
            $table->string('locale', 5);
            // معرّف الترجمة في المصدر — يُنسب به النصّ إلى مترجمه.
            $table->unsignedSmallInteger('translation_id');
            $table->text('text');
            $table->timestamps();

            // آيةٌ واحدة بترجمةٍ واحدة لكلّ لغة — وإعادةُ البذر تُحدّث ولا تُكرّر.
            $table->unique(['surah', 'ayah', 'locale']);
            $table->index(['locale', 'surah']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_translations');
    }
};
