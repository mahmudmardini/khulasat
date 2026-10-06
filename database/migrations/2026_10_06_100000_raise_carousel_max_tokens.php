<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * سقفُ توكنز الكاروسيل من ٤٠٠٠ إلى ٨٠٠٠ — T-217.
     *
     * **ولا يُمسّ صفٌّ غيّره المشرف**: يُرفع السقفُ إن بقي على قيمة البذر
     * وحدها، فقيمةٌ اختارها أحدٌ من شاشة النماذج تبقى كما هي — نظيرُ T-195.
     */
    public function up(): void
    {
        DB::table('model_config')
            ->where('stage', 'carousel')
            ->where('max_tokens', 4_000)
            ->update(['max_tokens' => 8_000, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('model_config')
            ->where('stage', 'carousel')
            ->where('max_tokens', 8_000)
            ->update(['max_tokens' => 4_000, 'updated_at' => now()]);
    }
};
