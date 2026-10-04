<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اختبارُ الفهم برابطٍ يُشارَك — T-195، وتقاريرُه في T-201.
     *
     * **أربعة جداول لا عمود JSON**: كلُّ سؤالٍ صفٌّ بمعرّفٍ ثابت، وكلُّ جوابٍ
     * صفٌّ يشير إليه. فنسبةُ صواب السؤال وأكثرُ خياراته الخاطئة استعلامٌ
     * مجمَّع، لا فكُّ JSON لكلّ محاولةٍ في الذاكرة.
     *
     * **وIP يُحفظ كاملاً** — قرار @HasanSiwi، ٤ أكتوبر ٢٠٢٦: يراه صاحبُ
     * الملخّص في التقارير، وبه تُعرف إعادةُ المحاولة من الجهاز نفسه. وصفحةُ
     * البداية تقول ذلك للمشارك قبل أن يكتب اسمه.
     */
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->boolean('want_quiz')->default(false)->after('want_carousel');
        });

        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('summary_job_id')->unique()->constrained()->cascadeOnDelete();

            // رمزُ الرابط العامّ — عشوائيٌّ لا يُعدّ بالتسلسل، فلا يُخمَّن اختبارُ جهةٍ أخرى.
            $table->string('token', 32)->unique();

            // ready | failed — والإخفاقُ صفٌّ بسببه، فيُعرض في اللوحة ويُعاد.
            $table->string('state')->default('ready');
            $table->string('failure_reason')->nullable();

            // open | closed — المفتوحُ وحده يقبل المحاولات ويظهر زرُّه في الصفحة.
            $table->string('status')->default('open');

            // end | immediate — متى يرى المشارك الجوابَ الصحيح. والافتراضُ في الآخر.
            $table->string('feedback')->default('end');

            $table->unsignedInteger('opens_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            // single | true_false | evidence
            $table->string('kind');
            // recall | understanding | application
            $table->string('level')->nullable();

            $table->text('prompt');

            // [{text, evidence_item_id}] **مخلوطةً كما تُعرض**. ولفظُ الشاهد لا
            // يُحفظ هنا: يُقرأ عند العرض من `evidence_items.matched_text`.
            $table->jsonb('options');
            $table->unsignedSmallInteger('correct_index');

            $table->text('explanation');
            $table->unsignedSmallInteger('axis_index')->nullable();
            $table->jsonb('evidence_item_ids')->nullable();

            $table->timestamps();

            $table->index(['quiz_id', 'position']);
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();

            // رمزُ المحاولة في رابطها — فلا كوكي ولا حساب.
            $table->string('token', 40)->unique();

            $table->string('participant_name', 60);
            // الاسمُ بعد التطبيع: به وبـIP تُعرف إعادةُ المحاولة.
            $table->string('name_key', 60);
            $table->string('ip', 45);
            $table->unsignedSmallInteger('attempt_number')->default(1);

            $table->unsignedSmallInteger('total');
            $table->unsignedSmallInteger('score')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->index(['quiz_id', 'finished_at']);
            $table->index(['quiz_id', 'name_key', 'ip']);
            $table->index(['quiz_id', 'ip', 'started_at']);
        });

        Schema::create('quiz_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('option_index');
            $table->boolean('is_correct');
            $table->timestamp('answered_at');

            $table->unique(['quiz_attempt_id', 'quiz_question_id']);
            $table->index(['quiz_question_id', 'option_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');

        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropColumn('want_quiz');
        });
    }
};
