<?php

declare(strict_types=1);

use App\Support\Transcript\SourceKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاحُ المصدر — T-65.
 *
 * **مشتقٌّ لا مُدخَل**: يُحسب من `source_url` بـ{@see SourceKey}، وعليه
 * وحده تقع المقارنة. ويُفهرس مع الجهة، فالبحثُ عن مكرَّرٍ لمسةٌ لا مسح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->string('source_key')->nullable()->after('source_platform');
            // بالجهة أوّلاً: المقارنةُ داخلَها وحدها، ومصدرُ جهةٍ لا يحجب أخرى.
            $table->index(['tenant_id', 'source_key']);
        });

        DB::table('lectures')->select('id', 'source_url')->orderBy('id')
            ->chunk(500, static function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('lectures')->where('id', $row->id)
                        ->update(['source_key' => SourceKey::for($row->source_url)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('lectures', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'source_key']);
            $table->dropColumn('source_key');
        });
    }
};
