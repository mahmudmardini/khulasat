<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات صفحة الملخّص — مخرَج المرحلة ٦، T-57.
 *
 * وكانت المرحلة تُبنى ولا تُنادى، فلا موضعَ لمخرَجها أصلاً. وهذا موضعُه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            // **يقبل `null`** — والمرحلة تسقط بـ`degrade` لا `fail`، فصفحةٌ
            // بلا وصفٍ تُنشر ولا تقف.
            $table->json('output_meta_json')->nullable()->after('body_json');
        });
    }

    public function down(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->dropColumn('output_meta_json');
        });
    }
};
