<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ الاعتراضات — دراسة المشروع، المادة 15.
 *
 * **والدراسة تَعِد بمسار حذفٍ يُنفَّذ خلال 48 ساعة، والوعد بلا تنفيذ أسوأ
 * من عدمه** لأنّه يُذكر في العرض التجاري. ولذلك يُسجَّل زمنُ الوصول وزمنُ
 * المعالجة معاً: **وعدٌ لا يُقاس ليس وعداً**.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();

            // الجهة تُستنتج من الملخّص المعترَض عليه، وقد يغيب إن كان الرابط خطأً.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('summary_job_id')->nullable()->constrained()->nullOnDelete();

            $table->string('kind');
            $table->text('url');
            $table->string('contact');
            $table->text('detail')->nullable();

            $table->string('status')->default('open');

            $table->timestamp('received_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
