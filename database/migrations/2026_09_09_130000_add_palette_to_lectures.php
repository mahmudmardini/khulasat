<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لوحةُ ألوانٍ لكل محاضرة — T-60، نظير `template` و`locales` (T-50).
 *
 * كانت اللوحة في إعدادات الجهة وحدها. وجهةٌ واحدة قد ترغب بلوحةٍ مختلفة
 * لمناسبةٍ أو نوع محتوًى، دون أن تُبدّل هوية الجهة كلَّها.
 *
 * **و`null` تعني «كما في إعدادات الجهة»** لا قيمةً منسوخة — نظيرُ علّة
 * `template` بحرفها: من بدّل افتراضَ الجهة تبدّل معه كلُّ محاضرةٍ لم
 * تختر لنفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->string('palette', 32)->nullable()->after('locales');
        });
    }

    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropColumn('palette');
        });
    }
};
