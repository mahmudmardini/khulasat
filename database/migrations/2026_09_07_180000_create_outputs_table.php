<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `outputs` — المواصفة §4 و§8-أ.
 *
 * **ولماذا جدولٌ لا عمود:** المحتوى المتحقَّق منه أصلٌ واحد غالٍ يُعاد
 * استعماله في مخرجاتٍ كثيرة — صفحة، وكاروسيل، وصور. والخطّ يعمل مرّة،
 * والعارضات ترسم منه بكلفةٍ قريبة من الصفر. **ومن ربط المخرَج بعمودٍ في
 * `summary_jobs` أعاد الهيكلة عند أوّل مخرَجٍ ثانٍ.**
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outputs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // نصٌّ بقائمة سماح لا enum — القاعدة نفسها التي في evidence_items.kind
            // (T-02ب): إضافةُ عارضٍ جديد لا يجوز أن تكون ترحيلاً.
            $table->string('type');
            $table->string('format');

            $table->string('storage_path')->nullable();
            $table->text('public_url')->nullable();

            $table->jsonb('meta')->default('{}');

            $table->timestamp('rendered_at')->nullable();

            // نسخة العارض — §8-أ: «كل عارض له renderer_version يُحفظ في
            // outputs لتتبّع التغيّرات». فصفحةٌ رُسمت بنسخةٍ قديمة تُعرف.
            $table->string('renderer_version', 32);

            $table->index('summary_job_id');
            $table->index(['tenant_id', 'type']);

            // مخرَجٌ واحد من كل نوع لكل مهمّة. وإعادة العرض تكتب فوقه ولا
            // تكدّس صفوفاً — §8-أ: «إعادة العرض لا تُعيد تشغيل الخطّ».
            $table->unique(['summary_job_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outputs');
    }
};
