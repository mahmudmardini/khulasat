<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * أثر ما يفعله المشرف العامّ — المواصفة §10، والمهمّة T-21.
     *
     * **جدولٌ واحد لا ثلاثة.** فالمطلوب تسجيلُ ثلاثة أنواعٍ من الفعل:
     * تعديلُ حدود جهة، وتعديلُ `model_config`، والدخولُ بهوية جهة. وهي
     * تختلف في موضوعها وتتّفق في سؤالها — **من فعل، ومتى، وماذا صار** —
     * فثلاثةُ جداول تُجيب سؤالاً واحداً ثلاث مرّات، ولا تُقرأ معاً في
     * صفحةٍ واحدة إلّا بضمٍّ ثلاثيّ.
     *
     * **ولا يُحذف صفٌّ منه ولا يُعدَّل.** سجلٌّ يُعدَّل ليس سجلّاً، ولذلك
     * لا `updated_at` هنا: الصفّ يُكتب مرّةً ويبقى.
     */
    public function up(): void
    {
        Schema::create('admin_audit_log', function (Blueprint $table): void {
            $table->id();

            /*
             * `nullOnDelete` لا `cascade`: حذفُ حساب مشرفٍ ترك العمل **لا
             * يمحو ما فعله**. ويبقى الصفّ باسمه المحفوظ أدناه، فيُقرأ بعد
             * رحيله. والحذفُ المتتالي هنا يمحو الأثر عمّن يُراد أثرُه.
             */
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            // اسمُه وبريدُه ساعةَ الفعل — لقطةً لا مرجعاً. فالحساب يُحذف
            // أو يُعاد تسميته، والسجلّ يجب أن يبقى مقروءاً بعدهما.
            $table->string('admin_name');
            $table->string('admin_email');

            // App\Enums\AuditAction — `tenant.limits` وأخواتها.
            $table->string('action');

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            // اسمُ الموضوع لقطةً كذلك: جهةٌ تُحذف يبقى اسمُها في سجلّها.
            $table->string('subject_label')->nullable();

            /*
             * `{"monthly_quota": {"from": 3, "to": 40}}` — الحقلُ وقيمتاه.
             *
             * **ومن أيّ قيمةٍ إلى أيّ قيمة** لا القيمةَ الجديدة وحدها:
             * «صارت الحصّة أربعين» لا تُخبر أنّها كانت ثلاثاً، ومراجعةُ
             * السجلّ بعد أشهر تسأل عن الفرق لا عن الحاصل.
             */
            $table->jsonb('changes')->default('{}');

            /*
             * **ما يربط الفعل بسببه.** ورفعُ حصّةِ جهةٍ بعد تحويلٍ بنكيّ
             * حدثٌ ماليّ لا ضبطُ إعداد — قرار مالك المنتج 7 أيلول 2026 —
             * فيُقيَّد معه مرجعُ التحويل ومبلغُه وتاريخُه. وبلا هذا يقول
             * السجلّ «رُفعت الحصّة» ولا يقول لماذا.
             */
            $table->text('note')->nullable();

            $table->ipAddress('ip')->nullable();
            $table->timestamp('occurred_at');

            // القراءة الغالبة: سجلّ جهةٍ بعينها، وسجلّ اليوم كلِّه.
            $table->index(['subject_type', 'subject_id', 'occurred_at']);
            $table->index(['action', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_log');
    }
};
