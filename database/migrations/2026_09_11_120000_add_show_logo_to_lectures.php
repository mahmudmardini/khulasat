<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * شعارُ الجهة لكلّ محاضرة — T-99، بقرار مالك المنتج في ١١ أيلول ٢٠٢٦.
 *
 * رسمت T-98 شعارَ الجهة في رأس الصفحة وبصمتها، فظهر في كلّ ملخّصٍ قديم
 * أُعيدت معاينته أو نشره — وتلك صفحاتٌ رآها الناس بلا شعار. **فالقديمُ يبقى
 * بلا شعار**: افتراضُ العمود `true` لما يُنشأ بعد اليوم، ويُكتب `false` لكلّ
 * محاضرةٍ قبله.
 *
 * وهو علَمٌ لا نسخةٌ من الشعار: من بدّل شعاره تبدّل في ملخّصاته الجديدة كلّها،
 * ولا تُحفظ نصفُ ميغابايت في كلّ صفّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->boolean('show_logo')->default(true)->after('palette');
        });

        DB::table('lectures')->update(['show_logo' => false]);
    }

    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropColumn('show_logo');
        });
    }
};
