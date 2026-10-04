<?php

declare(strict_types=1);

use App\Models\MediaUpload;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملفٌّ يُرفع على أجزاء — {@see MediaUpload}.
 *
 * **صفٌّ لكلّ رفعٍ لم يصر مهمّةً بعد.** يُنشأ عند أوّل جزء، ويُحذف حين يُربط
 * بمهمّة (فالمهمّة تملك الملفّ من بعدُ)، أو حين يُلغى، أو حين يتقادم. فما بقي
 * في الجدول بعد يومٍ رفعٌ متروك، والكنسُ يقرأ منه لا من القرص.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // اسمُه كما رفعه المستخدم — للعرض وحده، والنوعُ يُفحص بالمحتوى.
            $table->string('original_name');
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedInteger('chunk_count');

            // receiving: الأجزاء تصل · ready: جُمع وفُحص، وينتظر الإرسال.
            $table->string('status', 16);
            // مسارُ الملفّ المجموع على القرص، متى صار `ready`.
            $table->string('path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->timestamps();

            // الكنسُ يسأل «ما تقادم؟» كلّ ساعة.
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
