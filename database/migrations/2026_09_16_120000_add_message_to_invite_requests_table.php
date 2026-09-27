<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رسالةُ صاحب الطلب — T-142، بلاغُ مالك المنتج.
 *
 * **وكان النموذجُ تصنيفاً بلا كلام:** اسمٌ وصفةٌ ووسيلةُ تواصلٍ ورابط —
 * أربعةُ حقولٍ تقول **من** أرسل ولا تقول **ماذا يريد**. فمن جاء بحاجةٍ
 * بعينها كتبها في حقل «وسيلة التواصل» أو لم يُرسل، والطلبُ يُقرأ يدوياً في
 * لوحة المشرف فلا يُعرف ما أراد صاحبُه.
 *
 * **و`text` لا `string`:** حدٌّ في المحرّك يقصّ كلاماً كتبه إنسان، والحدُّ
 * الحاكمُ في التحقّق ({@see InviteRequestController}) حيث يُرى خطؤه ويُصحَّح.
 *
 * **ونُسقِطُ العمود في `down` ولا نُفرّغه:** رسائلُ من أرسلوا قبله لا توجد
 * أصلاً، فالنزولُ لا يُفقد شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_requests', function (Blueprint $table): void {
            $table->text('message')->nullable()->after('link');
        });
    }

    public function down(): void
    {
        Schema::table('invite_requests', function (Blueprint $table): void {
            $table->dropColumn('message');
        });
    }
};
