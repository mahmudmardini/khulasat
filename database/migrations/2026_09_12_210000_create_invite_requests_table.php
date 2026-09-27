<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات الدعوة من صفحة التعريف العامّة — T-113.
 *
 * **جدولٌ مستقلّ لا `users` ولا `invitations`:** المرسِل ليس مستخدماً بعد
 * ولا مدعوّاً من مالك جهة، بل زائرٌ يطلب. و`invitations` (T-33) عقدٌ بين
 * جهةٍ قائمةٍ ومدعوٍّ باسمه، وحشرُ الغرباء فيه يخلط البابين.
 *
 * **وبلا `tenant_id`:** لا جهة له بعد، وهذا أصلُ الطلب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invite_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('role', 60);
            $table->string('contact', 200);
            $table->string('link', 500)->nullable();
            // للحدّ من الإغراق ولمعرفة مصدر الطلب — لا لتتبّع الزائر.
            $table->string('ip', 45)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_requests');
    }
};
