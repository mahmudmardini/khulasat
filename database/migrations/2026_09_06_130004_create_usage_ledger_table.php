<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * مصدر الحقيقة الوحيد للحصص والفوترة — المواصفة §4 و§11.
     *
     * **لا تُحسب الحصص بعدّ صفوف `summary_jobs` في أيّ مكان.** المهمّة الملغاة
     * والمهمّة الفاشلة صفّان في `summary_jobs` ولا يُحتسبان، وإعادة التوليد
     * تُحتسب ولا صفّ لها. فعدّ الصفوف يخطئ في الاتجاهين.
     */
    public function up(): void
    {
        Schema::create('usage_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('summary_job_id')->nullable()->constrained()->nullOnDelete();

            // generate | regenerate | transcribe — App\Enums\UsageEvent.
            $table->string('event');

            // الوحدة بحسب الحدث: ملخّص واحد، أو دقيقة تفريغ واحدة.
            $table->unsignedInteger('units')->default(1);
            $table->decimal('cost_usd', 8, 4)->default(0);

            $table->timestamp('occurred_at');

            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_ledger');
    }
};
