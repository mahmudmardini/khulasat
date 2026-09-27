<?php

declare(strict_types=1);

use App\Support\Billing\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * القديم ← الجديد.
     *
     * وما ليس في الخريطة يُترك كما هو ولا يُخمَّن له بديل: جهةٌ على مفتاحٍ
     * لا نعرفه تبقى حدودُها في أعمدتها تعمل، وتظهر باسمها الخام في اللوحة
     * ({@see Plan::label()}) — وذلك أصدق من نقلها إلى
     * شريحةٍ لم يشترِها أحد.
     */
    private const MAP = [
        'trial' => 'free',
        'mosque' => 'starter',
        'institution' => 'business',
        'academy' => 'enterprise',
    ];

    /**
     * أسماءُ الشرائح عالميةٌ عامّة — T-104، طلبُ مالك المنتج 12 أيلول 2026.
     *
     * `mosque` و`academy` تصفان زبوناً بعينه، والأداة تُباع لفردٍ يصنع
     * محتوًى ولشركةٍ وجامعةٍ في أيّ بلد. **والحدود لا تُمسّ هنا**: هذه
     * تسميةٌ لا إعادةُ تسعير، والأرقام كما حُسمت في 8 أيلول 2026 —
     * فالترحيل يبدّل عمود `plan` وحده ولا يقترب من الأعمدة الخمسة.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('plan')->default('free')->change();
        });

        foreach (self::MAP as $old => $new) {
            DB::table('tenants')->where('plan', $old)->update(['plan' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $old => $new) {
            DB::table('tenants')->where('plan', $new)->update(['plan' => $old]);
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('plan')->default('trial')->change();
        });
    }
};
