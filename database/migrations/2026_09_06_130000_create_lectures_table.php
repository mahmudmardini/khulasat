<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lectures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('title_ar');
            $table->string('subtitle_ar')->nullable();

            $table->string('speaker_name');
            $table->string('speaker_title')->nullable();

            $table->string('source_url')->nullable();
            $table->string('source_platform')->nullable();

            // الهجري نصّ لا تاريخ: يُكتب كما يُعرض («١٢ رجب ١٤٤٧»)، ولا تحويل
            // آلي بين التقويمين لأنّ بداية الشهر تختلف باختلاف الجهة.
            $table->string('hijri_date')->nullable();
            $table->date('gregorian_date')->nullable();
            $table->string('weekday')->nullable();
            $table->string('time_note')->nullable();

            // institution | speaker_only | publisher_only — App\Enums\VenueMode.
            $table->string('venue_mode')->default('institution');

            // من yt-dlp قبل البدء — المواصفة §11: يُرفض الطول الزائد قبل صرف توكن.
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // المواصفة §4 تذكر created_at وحده لهذا الجدول.
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lectures');
    }
};
