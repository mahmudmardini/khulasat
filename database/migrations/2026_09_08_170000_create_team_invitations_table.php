<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `team_invitations` — SCREENS.md §10، والمهمّة T-33.
 *
 * ★ **والدعوة صفٌّ لا رسالة.** فلو كانت رسالةً وحدها لكانت الدعوة تضيع
 * بضياعها: بريدٌ لم يصل، أو مجلّدُ مهملات، ولا أثر عندنا يُراجَع ولا رابطٌ
 * يُعاد إرساله. **والصفُّ يُرى في الشاشة معلَّقاً**، فيُعرف من دُعي ولم يدخل.
 *
 * والبريد ليس فريداً في الجدول: دعوةٌ ألغيت ثمّ أُعيدت صفّان، **والقبول
 * هو ما يوحّد** — ويحرسه فحصٌ على `users.email` وهو فريد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invitations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('email');
            // owner | editor | viewer — App\Enums\Role.
            $table->string('role');

            /*
             * **مُعمّى لا خامّاً.** فمن قرأ الجدول — نسخةً احتياطية أو
             * سجلَّ استعلامات — يستطيع بالرابط الخامّ **أن يدخل جهةً بدور
             * مالكها**. والمقارنة بـ`hash_equals` عند القبول.
             */
            $table->string('token_hash', 64)->unique();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
    }
};
