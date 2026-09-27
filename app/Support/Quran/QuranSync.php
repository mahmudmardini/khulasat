<?php

declare(strict_types=1);

namespace App\Support\Quran;

use App\Enums\Locale;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * متى زُومن كلُّ موردٍ من Quran Foundation آخرَ مرّة — T-161.
 *
 * شروطُهم لا تُجيز حفظ المحتوى أكثر من أسبوع بلا مزامنة. **فالمزامنةُ
 * الفائتة مخالفةٌ لا عطلٌ تقنيّ**: المصحف يعمل كما هو، ولا يراها أحد إلّا
 * فحصُ الجاهزية الذي يقرأ من هنا.
 */
final class QuranSync
{
    /** العثماني والإملائي وأسماء السور. */
    public const CORE = 'core';

    private function __construct() {}

    public static function translation(int $id): string
    {
        return "translation:{$id}";
    }

    /** @return list<string> كلُّ ما يجب أن يُزامَن: الأصل وترجمةُ كلّ لغة. */
    public static function resources(): array
    {
        $resources = [self::CORE];

        foreach (Locale::translatable() as $locale) {
            $id = $locale->quranTranslationId();

            if ($id !== null) {
                $resources[] = self::translation($id);
            }
        }

        return $resources;
    }

    public static function record(string $resource, int $rows): void
    {
        DB::table('quran_syncs')->upsert(
            [['resource' => $resource, 'rows' => $rows, 'synced_at' => now()]],
            ['resource'],
            ['rows', 'synced_at'],
        );
    }

    public static function lastSynced(string $resource): ?CarbonInterface
    {
        if (! Schema::hasTable('quran_syncs')) {
            return null;
        }

        $at = DB::table('quran_syncs')->where('resource', $resource)->value('synced_at');

        return $at === null ? null : Carbon::parse($at);
    }

    /**
     * الموارد التي لم تُزامَن قطّ أو فات موعدُها.
     *
     * @return list<string>
     */
    public static function stale(): array
    {
        $limit = now()->subDays((int) config('khulasah.quran.max_age_days'));

        return array_values(array_filter(
            self::resources(),
            static fn (string $resource): bool => self::lastSynced($resource)?->greaterThanOrEqualTo($limit) !== true,
        ));
    }
}
