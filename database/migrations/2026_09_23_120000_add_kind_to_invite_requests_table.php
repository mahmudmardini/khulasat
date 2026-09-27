<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نوعُ الطلب — T-158، طلبُ مالك المنتج.
 *
 * **والصفوفُ القائمة `lecture`:** وصلت كلُّها من النموذج القديم، وهو طلبُ
 * تجربةٍ بصفةٍ ورابط. والافتراضيُّ في العمود لها وحدها، والمتحكّمُ يكتب
 * النوعَ صريحاً في كلّ صفٍّ جديد ({@see InviteRequestController}).
 *
 * **و`role` يقبل الفراغ:** رسالةُ التواصل لا تسأل عن الصفة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invite_requests', function (Blueprint $table): void {
            $table->string('kind', 20)->default('lecture')->after('id');
            $table->string('role', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invite_requests', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
