<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المخرجات المطلوبة عند الإنشاء — SCREENS.md §3-ب، وT-23.
 *
 * **وعمودٌ لا جدول:** المخرجات ثلاثة معروفة (§8-أ)، وخانةُ اختيارٍ واحدة
 * لا تستحقّ جدولاً بمفتاحين. ومتى صارت أكثر من ذلك فجدولُها حينئذٍ.
 *
 * **ولا عمود لحزمة الصور:** عارضُها مؤجَّل (T-20)، وعمودٌ يُملأ ولا يقرؤه
 * أحد يَعِد بما ليس في المنتج — والخانة تبقى معطَّلةً حتى يوجد عارضها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->boolean('want_carousel')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropColumn('want_carousel');
        });
    }
};
