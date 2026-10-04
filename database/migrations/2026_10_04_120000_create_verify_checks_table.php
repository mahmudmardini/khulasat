<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أداة «تحقّق» المستقلّة — T-181.
     *
     * **طلبٌ لا ينتمي إلى جهة.** يلصق الباحث نصّاً فيُستخرج ما فيه من شواهد
     * ويُطابَق بمصادره، بلا محاضرة ولا ملخّص ولا حساب. فله جدوله، ولنداءات
     * نموذجه صفوفٌ في `model_calls` بلا جهة ولا مهمّة.
     *
     * **والنصّ لا يبقى أكثر من سبعة أيام**، ومعه التقرير لأنّ فيه ألفاظه.
     * ويبقى الصفّ بعدهما بكلفته وأعداده، فتُقرأ منه الكلفة والاستعمال ولا
     * يُقرأ منه شيءٌ ممّا كتبه أحد.
     */
    public function up(): void
    {
        Schema::create('verify_checks', function (Blueprint $table): void {
            // معرّفٌ لا يُخمَّن: رابطُ التقرير يُشارَك، فلا يُعدّ بالتسلسل.
            $table->uuid('id')->primary();

            // queued | extracting | verifying | done | failed — App\Enums\VerifyCheckStatus.
            $table->string('status')->default('queued');

            $table->text('text')->nullable();
            $table->unsignedInteger('char_count');

            $table->jsonb('report')->nullable();
            $table->unsignedSmallInteger('evidence_count')->nullable();

            $table->string('error_code')->nullable();
            $table->decimal('cost_usd', 8, 4)->default(0);

            // بصمةُ العنوان لا العنوانُ نفسه — يكفي لحدّ المعدّل وللتحليل.
            $table->string('ip_hash', 64);

            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('purged_at')->nullable();

            $table->index('created_at');
        });

        Schema::table('model_calls', function (Blueprint $table): void {
            // نداءٌ بلا جهة ولا مهمّة: نداءُ أداة التحقّق.
            $table->foreignId('tenant_id')->nullable()->change();
            $table->foreignId('summary_job_id')->nullable()->change();

            $table->foreignUuid('verify_check_id')->nullable()->after('summary_job_id')
                ->constrained('verify_checks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('model_calls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verify_check_id');
        });

        Schema::dropIfExists('verify_checks');
    }
};
