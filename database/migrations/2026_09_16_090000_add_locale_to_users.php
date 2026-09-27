<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * لغةُ اللوحة — T-133.
 *
 * **واللغةُ صفةُ المستخدم لا صفةُ الجهة.** مركزٌ في برلين فيه من يقرأ
 * العربية ومن لا يقرؤها، وفرضُ لغةٍ واحدة على الفريق يُخرج أحدَهما.
 *
 * **وافتراضُها `ar` لا `null`**: كلُّ من في قاعدة البيانات اليوم دخل على
 * لوحةٍ عربية، فالصمتُ يعني العربية لا «لم يختر بعد».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->default('ar')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
