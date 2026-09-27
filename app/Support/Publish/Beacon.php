<?php

declare(strict_types=1);

namespace App\Support\Publish;

use App\Enums\Locale;
use App\Enums\OutputType;

/**
 * رابط شاهدة العدّ — SCREENS.md §7، والمهمّة T-31.
 *
 * **ومطلقٌ لا نسبيّ**: الصفحة تُخدَم من `{tenant}.khulasat.io` أو من
 * الحافّة، ولوحتُنا على نطاقٍ آخر. فرابطٌ نسبيّ يطلب الشاهدةَ من نطاق
 * الصفحة نفسه، فلا يبلغنا ويترك في صفحة الجهة صورةً مكسورة.
 *
 * ★ **واللسانُ في المسار — T-140.** وكان الرابطُ من رقم المهمّة والنوع
 * وحدهما، فصفحاتُ اللغات الثلاث تطلب الشاهدةَ نفسَها ويكتبها العدّادُ في
 * صفٍّ واحد: **المجموعُ صحيح وتوزيعُه معدوم**. ومن أنفق على ثلاث لغاتٍ لا
 * يعرف أنفعَها ولا أيَّها يُلغي في الملخّص القادم.
 */
final class Beacon
{
    /** ويعود `null` فلا تُكتب الشاهدة أصلاً: بلا مهمّة، أو والعدّ مُطفأ. */
    public static function for(?int $summaryJobId, OutputType $type, ?Locale $locale = null): ?string
    {
        $base = (string) config('khulasah.analytics.beacon_base');

        if ($summaryJobId === null || $base === '' || config('khulasah.analytics.enabled') !== true) {
            return null;
        }

        /*
         * **ويُحتفظ بالصيغة القديمة حين لا لسان.** فملفّاتٌ منشورةٌ قبل
         * T-140 تحمل `/v/49/page.gif` على الأقراص وفي أيدي الناس، ومسارُها
         * قائمٌ يُخدَم — وما تكتبه يقع في «غير مبيَّنة» لا في لسانٍ مخمَّن.
         */
        return $locale === null
            ? "{$base}/v/{$summaryJobId}/{$type->value}.gif"
            : "{$base}/v/{$summaryJobId}/{$type->value}/{$locale->value}.gif";
    }
}
