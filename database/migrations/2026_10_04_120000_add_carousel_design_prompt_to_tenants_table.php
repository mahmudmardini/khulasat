<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * تعليماتُ توليد قوالب الكاروسيل الخاصّةُ بجهة — T-173.
 *
 * **عمودٌ لا مفتاحٌ في `brand_kit`**: تلك هويةُ الجهة تكتبها هي من شاشتها،
 * وهذه يكتبها المشرف وحده. وفارغُها يعني التعليمات الافتراضية في
 * `resources/prompts/shared/carousel_design.txt`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->text('carousel_design_prompt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('carousel_design_prompt');
        });
    }
};
