<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إغلاق حساب الجهة — T-24.
 *
 * **والحذف مؤجَّلٌ بمهلةٍ معلنة، والتراجع متاحٌ خلالها.** فالإغلاق قرارٌ
 * يُتّخذ في ساعة غضبٍ أو خطأ، والبيانات لا تعود بعد حذفها. والمهلة ليست
 * تسويفاً، بل هي الفرق بين حذفٍ يُنقذ وحذفٍ لا يُنقذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestamp('closure_requested_at')->nullable();

            // الوقت المعلن الذي يقع فيه الحذف. ويُخزَّن محسوباً لا مشتقّاً،
            // فتغييرُ المهلة في الإعدادات لا يقدّم حذفاً وُعد به في تاريخ.
            $table->timestamp('purge_after')->nullable();

            // الأرشيف الإجباري قبل الإغلاق — لا يُغلق حسابٌ بلا تصدير.
            $table->string('closure_export_path')->nullable();

            $table->index('purge_after');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex(['purge_after']);
            $table->dropColumn(['closure_requested_at', 'purge_after', 'closure_export_path']);
        });
    }
};
