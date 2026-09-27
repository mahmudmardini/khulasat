<?php

declare(strict_types=1);

use App\Actions\Summary\AddOutputLocale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالُ ترجمةٍ طُلبت بعد إنشاء الملخّص — T-166.
 *
 * ★ **وعلّتُها أنّ الترجمة صارت تُطلب من الشاشة لا من الخطّ وحده.**
 *
 * فـ{@see AddOutputLocale} يضع ترجمةَ لغةٍ واحدة في الطابور، والشاشةُ تنتظرها.
 * **وبلا حالٍ محفوظة لا تفرق الشاشةُ بين «تُترجَم الآن» و«سقطت»**: كلتاهما
 * لغةٌ مختارةٌ بلا متن. فتنتظر الأولى أبداً، أو تُري الثانية «جارٍ» وقد ماتت.
 *
 * `requested_at` — طُلبت ولم تنتهِ. `failed_at` — آخرُ طلبٍ سقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summary_translations', function (Blueprint $table): void {
            $table->timestamp('requested_at')->nullable()->after('source_hash');
            $table->timestamp('failed_at')->nullable()->after('requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('summary_translations', function (Blueprint $table): void {
            $table->dropColumn(['requested_at', 'failed_at']);
        });
    }
};
