<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summary_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lecture_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            // آلة الحالات في App\Domain\Summary\JobState — المواصفة §5.
            // نصّ لا enum في القاعدة: إضافة حالة لا يجوز أن تكون ترحيلاً.
            $table->string('state')->default('queued');

            // attempt: عدّاد إعادة المحاولة الآلية داخل الحالة الواحدة، يُصفَّر
            // عند كلّ انتقال. regeneration_count: قرار بشري، ولا يُصفَّر أبداً.
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->unsignedSmallInteger('regeneration_count')->default(0);

            // captions | whisper | manual — App\Enums\TranscriptSource.
            $table->string('transcript_source')->nullable();
            $table->text('transcript_text')->nullable();
            $table->unsignedInteger('transcript_word_count')->nullable();

            $table->jsonb('structure_json')->nullable();
            $table->jsonb('evidence_json')->nullable();
            $table->text('body_html')->nullable();

            // الكلفة موزّعة على المراحل، ومجموعها في total_cost_usd. يقرأ
            // سقفُ الإنفاق العامّ المجموعَ — المواصفة §11.
            $table->jsonb('cost_breakdown')->default('{}');
            $table->decimal('total_cost_usd', 8, 4)->default(0);

            $table->string('error_code')->nullable();
            $table->text('error_detail')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->index(['tenant_id', 'state']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summary_jobs');
    }
};
