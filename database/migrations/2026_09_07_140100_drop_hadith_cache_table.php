<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `hadith_cache` صار بلا وظيفة — قرار مالك المنتج، ٦ أيلول ٢٠٢٦.
 *
 * الجدول بُني ليخزّن ردود مزوّدٍ خارجي فلا يُنادى مرّتين. **ولم يبقَ مزوّد
 * خارجي**: المدوّنة تُبذر عندنا وتُقرأ من قاعدتنا، فالكاشُ طبقةٌ بين الشيء
 * ونفسه.
 *
 * **ولماذا يُسقَط بترحيلٍ لا بحذف ملفّ الإنشاء؟** لأنّ الملفّ نُفّذ فعلاً على
 * قواعدَ قائمة. وحذفُه يترك جدولاً يتيماً لا يعرف أحدٌ من أين جاء، ويجعل
 * `migrate:fresh` تخالف قاعدةً مهاجَرة. والإسقاط الصريح يصف ما حدث.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hadith_cache');
    }

    /**
     * يُعاد الجدول كما كان — والتراجع عن قرارٍ لا يُفقد بنيةً.
     */
    public function down(): void
    {
        Schema::create('hadith_cache', function (Blueprint $table): void {
            $table->id();
            $table->text('query_normalized');
            $table->string('provider');
            $table->jsonb('response_json');
            $table->unsignedInteger('matched_count')->default(0);
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();
            $table->unique(['query_normalized', 'provider'], 'hadith_cache_query_provider_unique');
            $table->index('expires_at');
        });
    }
};
