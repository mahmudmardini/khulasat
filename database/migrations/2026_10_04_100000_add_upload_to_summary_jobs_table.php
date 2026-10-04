<?php

declare(strict_types=1);

use App\Actions\Stages\ResolveTranscript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الملفّ المرفوع من الجهاز — المواصفة §5-أ-4-ب.
 *
 * **على المهمّة لا على المحاضرة**، كالنصّ الملصوق (`transcript_text`): كلاهما
 * مدخلُ مرحلة التفريغ لا وصفُ الدرس. والملفّ عابر: يُحذف متى صار نصّاً،
 * فيُفرَّغ العمود معه — {@see ResolveTranscript}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            // مسارُه على قرص `local`، لا مسارٌ مطلق: القرصُ يُبدَّل بإعداد.
            $table->string('upload_path')->nullable()->after('transcript_word_count');
            // اسمُه كما رفعه المستخدم، للاحقة وحدها — والنوع يُفحص بالمحتوى.
            $table->string('upload_name')->nullable()->after('upload_path');
        });
    }

    public function down(): void
    {
        Schema::table('summary_jobs', function (Blueprint $table): void {
            $table->dropColumn(['upload_path', 'upload_name']);
        });
    }
};
