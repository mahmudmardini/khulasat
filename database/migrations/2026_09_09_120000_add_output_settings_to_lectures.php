<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إعداداتُ مخرَجٍ لكل محاضرة — تتجاوز افتراضَ الجهة.
 *
 * **طلبُ مالك المنتج، ٩ أيلول ٢٠٢٦**: القالبُ واللغاتُ كانا في إعدادات
 * الجهة وحدها، فمن أراد درساً بقالبٍ غير قالبه اضطُرّ إلى تبديل إعدادات
 * الجهة كلِّها ثمّ ردِّها. وهذه جهةٌ واحدة تنشر أنواعَ محتوًى مختلفة.
 *
 * **و`null` تعني «كما في إعدادات الجهة»** — لا قيمةً منسوخة. فمن بدّل
 * افتراضَ الجهة تبدّل معه كلُّ محاضرةٍ لم تختر لنفسها، وهو المقصود: الافتراض
 * افتراضٌ حيّ لا لحظةُ نسخ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->string('template', 32)->nullable()->after('want_carousel');
            $table->jsonb('locales')->nullable()->after('template');
        });
    }

    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropColumn(['template', 'locales']);
        });
    }
};
