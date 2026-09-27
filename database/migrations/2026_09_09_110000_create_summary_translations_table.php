<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ترجمةُ ملخّصٍ إلى لغة — T-38.
 *
 * **جدولٌ مستقلّ لا أعمدةٌ على `summary_jobs`.** فاللغاتُ أربعٌ اليوم وقد
 * تزيد، وأعمدةٌ لكل لغةٍ تعني هجرةً لكلّ لغةٍ تُضاف. وصفٌّ لكل لغةٍ يعني
 * إضافتَها بصفٍّ لا بمخطّط.
 *
 * **والتحقّق لا يُعاد**: يجري على العربيّ مرّةً واحدة (معيار قبولٍ في
 * T-38)، وهذا الجدول **يحمل نقلاً لا حكماً** — لا حالةَ مراجعةٍ فيه ولا
 * درجةَ شاهد.
 *
 * و`meanings` ترجماتُ معاني الأحاديث بمفاتيح مواضعها — تُعرض بجانب اللفظ
 * العربي **موسومةً** «ترجمة معنى»، ولا تحلّ محلّه.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);

            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->jsonb('body_json')->nullable();
            $table->text('body_html')->nullable();
            // معاني الأحاديث — المفتاح موضعُ الشاهد في المتن.
            $table->jsonb('meanings')->default('{}');

            $table->timestamps();

            // لغةٌ واحدة لكل ملخّص — وإعادةُ الترجمة تُحدّث ولا تُكرّر.
            $table->unique(['summary_job_id', 'locale']);
            $table->index(['tenant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_translations');
    }
};
