<?php

declare(strict_types=1);

namespace App\Support\Publish;

use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;

/**
 * لغاتُ الملخّص المنشورة، بروابطها — T-134.
 *
 * ★ **ومبنيّةٌ على ما نُشر فعلاً لا على ما طُلب.** فـ`lecture.locales` نيّةٌ،
 * و`RenderAndPublish` يلتقط إخفاقَ اللغة التالية ويمضي (T-38: «إخفاقُها لا
 * يُسقط الملخّص»). فلغةٌ قُصدت ولم تُنشر رابطُها **٤٠٤ في صفحةِ جهة** — وهو
 * أسوأ من غياب الرابط: الأوّل يقول «ليست موجودة»، والثاني يقول «موقعُهم
 * مكسور».
 *
 * **و`public_url` هو المصدر** لا `Paths::publicUrl()`: الأوّل ما كُتب ساعةَ
 * النشر فعلاً (وهو يتبع إعداد الحافّة والقرص)، والثاني صيغةٌ نظرية تفترق
 * عنه متى بُدّل الإعداد. والرابطُ يجب أن يُشير إلى حيث الملفُّ يُخدَم.
 */
final class PublishedLocales
{
    private function __construct() {}

    /**
     * @param  int|null  $summaryJobId  و`null` في المعاينة والملفّ المنزَّل.
     * @param  Locale  $current  لغةُ هذه الصفحة — تُستثنى من قائمتها.
     * @return list<array{locale: Locale, native: string, url: string}>
     */
    public static function for(?int $summaryJobId, Locale $current): array
    {
        /*
         * **والمعاينة بلا شريط** — كالشاهدة في T-31: `summaryJobId` تُفرَّغ
         * في {@see ContentObject::withoutBeacon()}. وروابطُ المعاينة تُشير
         * إلى صفحاتٍ منشورةٍ قد لا توجد بعد، ولشاشةِ المعاينة مبدّلُها
         * الخاصّ من T-84.
         */
        if ($summaryJobId === null) {
            return [];
        }

        /*
         * `acrossTenants` لا اتّكالاً على سياق الجهة: الرسمُ يجري في عاملِ
         * طابورٍ قد لا يحمله. و`summary_job_id` مقصورٌ على جهةٍ واحدة أصلاً،
         * فالعزلُ قائمٌ بالشرط نفسِه — نظيرَ {@see PageViewController}.
         */
        $rows = Output::acrossTenants()
            ->where('summary_job_id', $summaryJobId)
            ->where('type', OutputType::Page->value)
            // ورابطٌ بلا ملفٍّ لا يُعرض: `UnpublishSummary` تُفرّغ العمودين.
            ->whereNotNull('storage_path')
            ->whereNotNull('public_url')
            ->get(['locale', 'public_url']);

        // **ولا `pluck` بعمود `locale`**: مصبوبٌ إلى {@see Locale}، ومفتاحُ
        // مصفوفةٍ من كائنٍ يرمي. فتُبنى الخريطةُ بقيمة الحالة صريحةً.
        $published = [];

        foreach ($rows as $row) {
            $published[$row->locale->value] = $row->public_url;
        }

        $links = [];

        // **وترتيبُها ترتيبُ `Locale`** لا ترتيبَ الإدراج في القاعدة: صفٌّ
        // أُعيد رسمُه يتقدّم في `id`، فيتبدّل ترتيبُ الشريط بلا سبب يُرى.
        foreach (Locale::cases() as $locale) {
            if ($locale === $current) {
                continue;
            }

            $url = $published[$locale->value] ?? null;

            if (is_string($url) && trim($url) !== '') {
                $links[] = [
                    'locale' => $locale,
                    // اسمُها بلسانها: من يبحث عن لغته يعرفها بحرفها.
                    'native' => $locale->nativeName(),
                    'url' => $url,
                ];
            }
        }

        return $links;
    }
}
