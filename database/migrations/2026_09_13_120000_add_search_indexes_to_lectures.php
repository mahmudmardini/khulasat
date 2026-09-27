<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * فهارسُ فهرس الملخّصات — T-124.
 *
 * `LectureController::filtered` يبحث بـ`ilike '%…%'` على العنوان واسم
 * الملقي، ويرشّح بالاسم تماماً، ويجمع أسماء الملقين بـ`distinct … order by`.
 * **والجدول بلا فهرسٍ لواحدةٍ منها** — والمصحفُ والمدوّنة يفعلانها صحيحةً
 * للنمط نفسه ({@see 2026_09_06_110000_create_quran_ayat_table}).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // B-tree لا يخدم `LIKE '%…%'`، والثلاثيّاتُ تخدمه وتخدم `ILIKE`
        // بالمثل — فلا يُمسح الجدول كلُّه على كلّ حرفٍ يُكتب في خانة البحث.
        DB::statement('CREATE INDEX IF NOT EXISTS lectures_title_ar_trgm ON lectures USING gin (title_ar gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS lectures_speaker_name_trgm ON lectures USING gin (speaker_name gin_trgm_ops)');

        // والترشيحُ بالاسم تامٌّ لا جزئيّ، وقائمةُ الملقين `distinct` مرتّبة
        // عليه — وكلاهما B-tree، فيبقى إلى جانب فهرس الثلاثيّات لا بدلاً منه.
        DB::statement('CREATE INDEX IF NOT EXISTS lectures_speaker_name_idx ON lectures (speaker_name)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS lectures_title_ar_trgm');
        DB::statement('DROP INDEX IF EXISTS lectures_speaker_name_trgm');
        DB::statement('DROP INDEX IF EXISTS lectures_speaker_name_idx');
    }
};
