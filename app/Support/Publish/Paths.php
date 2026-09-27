<?php

declare(strict_types=1);

namespace App\Support\Publish;

use App\Enums\Locale;
use App\Enums\OutputType;

/**
 * مسارات النشر — المواصفة §9.
 *
 * `{tenant.slug}.khulasat.io/{summary.slug}`، والمخرجات تحتها.
 */
final class Paths
{
    /** الملفّ داخل التخزين. والصفحة `index.html` كي يخدمها CDN على المجلّد. */
    /**
     * @param  Locale|null  $locale  لغةُ المخرَج — T-38.
     *
     * ★ **واللغةُ الأولى تبقى بلا مقطع** — T-51.
     *
     * ولكلّ ملخّصٍ منشورٍ رابطٌ جذر لا مقطعَ فيه: هو ما يُشارَك ويُفهرَس.
     * فلو نُشرت اللغاتُ كلُّها في مقاطع لبقي الجذرُ فارغاً، ولانكسر كلُّ
     * رابطٍ منشورٍ قبل تعدّد اللغات.
     *
     * **و`$primary` هو ما يُقرّر ذلك لا لغةُ المصدر**: من نشر بالإنجليزية
     * وحدها فجذرُه إنجليزيّ، ولا يبقى جذرٌ فارغ ينتظر عربيّةً لم تُطلب.
     * وترتيبُ الحالات يجعل العربية أولى متى اختيرت، فما نُشر قبل اليوم
     * يبقى حيث هو.
     */
    public static function forOutput(
        string $tenantSlug,
        string $summarySlug,
        OutputType $type,
        ?Locale $locale = null,
        ?Locale $primary = null,
    ): string {
        $segment = self::segment($locale, $primary);

        return trim("{$tenantSlug}/{$summarySlug}{$segment}{$type->pathSuffix()}", '/').'/index.html';
    }

    /** شاهدة الحذف — تبقى مكان الصفحة وتُخدَم بـ 410 (§9). */
    public static function tombstone(string $tenantSlug, string $summarySlug): string
    {
        return "{$tenantSlug}/{$summarySlug}/index.html";
    }

    /** الرابط العلني كما يراه القارئ. */
    public static function publicUrl(
        string $tenantSlug,
        string $summarySlug,
        OutputType $type,
        ?Locale $locale = null,
        ?Locale $primary = null,
    ): string {
        $domain = (string) config('khulasah.publish.domain');
        $segment = self::segment($locale, $primary);

        return "https://{$tenantSlug}.{$domain}/{$summarySlug}{$segment}{$type->pathSuffix()}";
    }

    /**
     * مقطعُ اللغة في المسار — فارغٌ للأولى.
     *
     * **والافتراض عند غياب `$primary` هو لغةُ المصدر**، فمستدعٍ لم يُحدَّث
     * بعد يرى ما كان يراه: العربيةُ على الجذر.
     */
    private static function segment(?Locale $locale, ?Locale $primary): string
    {
        if ($locale === null) {
            return '';
        }

        $root = $primary ?? Locale::source();

        return $locale === $root ? '' : '/'.$locale->value;
    }

    /** فهرس الجهة العامّ — يقرؤه `embed.js`. */
    public static function index(string $tenantSlug): string
    {
        return "{$tenantSlug}/index.json";
    }
}
