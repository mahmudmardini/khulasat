<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hadith_cache', function (Blueprint $table): void {
            $table->id();

            // مفتاح البحث هو النصّ المطبَّع — لا الخام. فلفظان يختلفان
            // تشكيلاً وهمزاتٍ سؤالٌ واحد، ولا يُنادى المزوّد مرّتين.
            $table->text('query_normalized');
            $table->string('provider');

            $table->jsonb('response_json');
            $table->unsignedInteger('matched_count')->default(0);

            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();

            $table->unique(['query_normalized', 'provider'], 'hadith_cache_query_provider_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hadith_cache');
    }
};
