<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_ayat', function (Blueprint $table): void {
            $table->id();

            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('ayah');
            $table->string('surah_name_ar');

            // العثماني للعرض، والإملائي للمطابقة — المواصفة §4 و§7-2 وT-03ب.
            // رواية حفص عن عاصم وحدها. لا تُخلط روايتان في هذا الجدول.
            $table->text('text_uthmani');
            $table->text('text_imlaei');

            // محسوب من text_imlaei بـ Arabic::normalize. عليه تجري كل مطابقة.
            $table->text('text_normalized');

            $table->unique(['surah', 'ayah']);

            // المطابقة بحثُ سلسلة فرعية، وPostgres لا يستعمل فهرس B-tree
            // مع LIKE '%…%'. فيُستعمل فهرس ثلاثيّات (trigram) وهو المصمَّم لهذا.
            $table->index('surah');
        });

        // pg_trgm يأتي مع Postgres، ويُفعَّل مرّة واحدة.
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX quran_ayat_normalized_trgm ON quran_ayat USING gin (text_normalized gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_ayat');
    }
};
