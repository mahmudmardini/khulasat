<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * لغةُ المخرَج — T-38.
 *
 * ★ **القيد القديم كان يمنع الميزة بنيوياً.** `UNIQUE (summary_job_id, type)`
 * يعني مخرَجاً واحداً من كل نوعٍ لكلّ مهمّة، فصفحتان بلغتين مستحيلتان
 * — لا صعبتان. ومعيارُ القبول الأوّل في T-38 «ملخّصٌ واحد قد يُنشر بأربع
 * لغات من تحقّقٍ واحد» يقتضي نقضَه.
 *
 * **والافتراض `ar`**: كلُّ ما نُشر قبل اليوم عربيّ، فلا صفَّ يحتاج تخميناً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outputs', function (Blueprint $table): void {
            $table->string('locale', 5)->default('ar')->after('type');
        });

        Schema::table('outputs', function (Blueprint $table): void {
            $table->dropUnique('outputs_summary_job_id_type_unique');
            $table->unique(['summary_job_id', 'type', 'locale']);
        });
    }

    public function down(): void
    {
        /*
         * **الرجوع يُتلف بياناً** — مهمّةٌ لها صفحتان بلغتين لا يقبلهما
         * القيدُ القديم. فتُحذف غيرُ العربية أوّلاً، وإلّا أخفقت الهجرة
         * بتعارضٍ غامض بدل أن تقول ما فعلت.
         */
        Schema::table('outputs', function (Blueprint $table): void {
            $table->dropUnique(['summary_job_id', 'type', 'locale']);
        });

        DB::table('outputs')->where('locale', '!=', 'ar')->delete();

        Schema::table('outputs', function (Blueprint $table): void {
            $table->unique(['summary_job_id', 'type']);
            $table->dropColumn('locale');
        });
    }
};
