<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجلّ الانتقالات — المواصفة §5: «كلّ انتقال يُسجَّل مع الزمن والكلفة الجزئية».
     *
     * **لماذا جدول لا عمود jsonb في summary_jobs:** المواصفة §15 تطلب «نسبة
     * الفشل بحسب المرحلة» و«متوسّط الكلفة»، وهما استعلامان تجميعيان عبر كلّ
     * المهامّ. وحشرُ السجلّ في jsonb داخل الصفّ يجعل كلّ لوحة مراقبة مسحاً
     * كاملاً للجدول.
     */
    public function up(): void
    {
        Schema::create('summary_job_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('from_state');
            $table->string('to_state');

            // رقم المحاولة عند الانتقال — يُميّز النجاح من أوّل مرّة عن النجاح بعد إعادة.
            $table->unsignedSmallInteger('attempt')->default(0);

            // كلفة المرحلة المنتهية وحدها، لا المجموع.
            $table->decimal('cost_usd', 8, 4)->default(0);

            $table->string('error_code')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['summary_job_id', 'occurred_at']);
            $table->index(['to_state', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_job_transitions');
    }
};
