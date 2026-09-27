<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `quran_syncs` — آخرُ مزامنةٍ لكلّ موردٍ من Quran Foundation، T-161.
 *
 * شروطُهم لا تُجيز حفظ المحتوى أكثر من أسبوع بلا مزامنة، فيُحفظ وقتُ آخرها
 * لكلّ مورد: `core` (العثماني والإملائي وأسماء السور) و`translation:<id>`.
 * وبه ينبّه فحصُ الجاهزية إن فاتت المزامنةُ الأسبوعية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_syncs', function (Blueprint $table): void {
            $table->id();
            $table->string('resource', 32)->unique();
            $table->unsignedInteger('rows');
            $table->timestamp('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_syncs');
    }
};
