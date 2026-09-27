<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اختيار النموذج لكل مرحلة — المواصفة §4 و§1.
     *
     * **من قاعدة البيانات لا من الشيفرة**: «الأسعار والنماذج تتبدّل كلّ
     * أشهر» (§1)، وتبديلُ نموذجٍ لا يجوز أن يكون نشراً.
     *
     * **ولا مفتاح واجهة هنا.** المفاتيح في `.env` وحدها — المواصفة §12
     * وCLAUDE.md §2 القاعدة السادسة. هذا الجدول يُقرأ من لوحة المشرف
     * ويُصدَّر في النسخ الاحتياطي، ومفتاحٌ فيه مفتاحٌ مسرَّب.
     */
    public function up(): void
    {
        Schema::create('model_config', function (Blueprint $table): void {
            $table->id();

            // App\Enums\Stage — ستّ مراحل. والرابعة (التحقّق) ليست منها:
            // لا نموذج فيها البتّة — CLAUDE.md §2 القاعدة الثالثة.
            $table->string('stage');

            $table->string('provider');
            $table->string('model_id');

            $table->unsignedInteger('max_tokens')->default(4_096);

            // none | low | medium | high — تكتب المتنَ بتفكير عالٍ (§6-أ).
            $table->string('thinking_level')->default('none');

            /*
             * السلسلة البديلة — المواصفة §6-أ. الأعمدة تُنشأ هنا لأنّ §4
             * تذكرها في الجدول، **والسلوك** يُنفَّذ في T-10ب.
             *
             * والبديل من **مزوّد مختلف**: انقطاع المزوّد يصيب نماذجه كلّها،
             * فالبديل داخل العائلة نفسها لا يقي.
             */
            $table->string('fallback_provider')->nullable();
            $table->string('fallback_model_id')->nullable();

            // مهلة لكل مرحلة لا مهلة عامّة — §6-أ: كتابةُ المتن بتفكير عالٍ
            // تحتاج أضعاف ما يحتاجه التنظيف، والمهلة تشمل زمن التفكير.
            $table->unsignedSmallInteger('timeout_seconds')->default(120);
            $table->unsignedTinyInteger('max_retries')->default(1);

            // fail | degrade — §4: fail لما لا يحتمل جودة أدنى (البنية
            // والمتن)، وdegrade للمراحل الميكانيكية.
            $table->string('on_exhausted')->default('fail');

            // الأسعار لكلّ مليون توكن، ومنها تُحسب الكلفة. decimal لا float:
            // الفوترة لا تُبنى على حسابٍ عائم.
            $table->decimal('input_price_per_m', 10, 4)->default(0);
            $table->decimal('output_price_per_m', 10, 4)->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('updated_at')->nullable();

            // صفٌّ فعّال واحد لكل مرحلة. والفهرس الفريد يمنع أن يُفعَّل صفّان
            // فيصير اختيار النموذج رهن ترتيب الصفوف.
            $table->index(['stage', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_config');
    }
};
