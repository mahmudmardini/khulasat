<?php

declare(strict_types=1);

use App\Services\Model\ModelCallRecorder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تفصيل استدعاء نموذجٍ واحد — T-22.
     *
     * **لا تبديل لـ`usage_ledger` ولا لـ`summary_jobs.cost_breakdown`.**
     * ذانك يبقيان «مصدر الحقيقة للحصص والفوترة» (§4) — {@see ModelCallRecorder}.
     * وهذا الجدول إضافةٌ للتحليل وحده: أيّ نموذجٍ ومزوّدٍ خدم أيّ مرحلةٍ من
     * أيّ ملخّص، وبكم توكن. فقدانُه لا يكسر حصّةً ولا فوترة، وفقدانُ ذينك
     * يكسرهما — فبقيا مصدرَ الحقيقة وحدهما.
     */
    public function up(): void
    {
        Schema::create('model_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('summary_job_id')->constrained()->cascadeOnDelete();

            // App\Enums\Stage — قيمةً لا مفتاح ترجمة، فلا يُترجَم عربياً هنا.
            $table->string('stage');

            $table->string('provider');
            $table->string('model_id');

            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->decimal('cost_usd', 8, 4)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedTinyInteger('attempt')->default(1);

            $table->timestamp('occurred_at');

            $table->index(['occurred_at']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['summary_job_id', 'stage']);
            $table->index(['provider', 'model_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_calls');
    }
};
