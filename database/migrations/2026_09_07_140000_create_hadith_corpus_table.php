<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hadith_corpus', function (Blueprint $table): void {
            $table->id();

            $table->string('book', 32);

            // نصّاً لا رقماً: أرقام المدوّنة فيها الكسور («٣٩٠.٢») للحديث
            // يُروى بإسنادين تحت رقم واحد. وتخزينُه رقماً يفقد التمييز.
            $table->string('hadith_number', 16);

            // ثلاثة أعمدة للنصّ كما في `quran_ayat` — المواصفة §4:
            //   المشكَّل هو ما يُنشر، والمجرَّد ما يُقرأ، والمطبَّع ما يُطابَق.
            $table->text('text');
            $table->text('text_plain');
            $table->text('text_normalized');

            // الحكم محسوباً وقت البذر من `GradeVocabulary`، لا محفوظاً من
            // المصدر: الجدول قد يُراجَع، وإعادة البذر تُعيد حسابه كلّه.
            $table->string('grade', 16);
            $table->string('grade_raw')->nullable();

            // **وكلّ المحكِّمين يُحفَظون** — المواصفة §7-3 البند ٤. المراجع
            // يرى اختلافهم كما هو، ولا يُخفى عنه أنّ في الصفّ خلافاً.
            $table->jsonb('graders_json')->nullable();

            // «رواه أبو داود» — مبنيّةً من الفهرس عندنا لا منقولةً.
            $table->string('takhrij');

            $table->unique(['book', 'hadith_number']);
            $table->index('grade');
        });

        // المطابقة بحثٌ عن نافذةٍ داخل نصّ، وPostgres لا يستعمل فهرس B-tree
        // مع LIKE '%…%'. وفهرس الثلاثيّات يخدم `<%` و`word_similarity`،
        // وهما المصمَّمان لإيجاد اقتباسٍ قصير داخل متنٍ طويل بإسناده.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX hadith_corpus_normalized_trgm ON hadith_corpus USING gin (text_normalized gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('hadith_corpus');
    }
};
