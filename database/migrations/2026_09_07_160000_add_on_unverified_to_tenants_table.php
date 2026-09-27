<?php

declare(strict_types=1);

use App\Enums\UnverifiedPolicy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tenants.on_unverified` — المواصفة §7-5، سياسة البيان.
 *
 * **والافتراض `disclose`**: يُنشر ما عُرف مصدره مقروناً بدرجته. والجهة التي
 * تريد عيناً بشرية على كلّ ما ليس صحيحاً تامّاً تضبطه `review` مرّة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('on_unverified', 16)->default(UnverifiedPolicy::Disclose->value);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('on_unverified');
        });
    }
};
