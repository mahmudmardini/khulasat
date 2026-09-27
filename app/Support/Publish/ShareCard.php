<?php

declare(strict_types=1);

namespace App\Support\Publish;

use App\Enums\Locale;

/**
 * Where a published summary's share card lives, and the URL crawlers fetch — T-144.
 *
 * ★ **الرابطُ على المنصّة لا على CDN الجهة، ومعروفٌ ساعةَ الرسم.** الصفحةُ
 * تُرسم قبل أن تُنشر، والبطاقةُ تُولَّد بعد النشر — فرابطٌ إلى ملفٍّ بجانب
 * الصفحة لا يُعرف حين يُكتب الوسم. أمّا `/share/{job}/{locale}.png` فثابتٌ
 * من رقم المهمّة ولغتها، **ويُجيب بصورةٍ دائماً**: المولَّدةِ إن وُجدت،
 * وبطاقةِ المنصّة إن لم توجد (`ShareCardController`).
 *
 * **و`?v=` بصمةُ ما في البطاقة**: واتساب وفيسبوك يحفظان الصورةَ برابطها،
 * فعنوانٌ تغيّر بإعادة النشر وبقي رابطُه يُعرض بصورته القديمة أبداً.
 */
final class ShareCard
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private function __construct() {}

    /**
     * @param  list<string|null>  $fingerprint  ما يُرسم في البطاقة — يتبدّل الرابطُ بتبدّله.
     */
    public static function url(?int $summaryJobId, Locale $locale, array $fingerprint = []): string
    {
        $base = rtrim((string) config('app.url'), '/');

        // المعاينةُ والملفُّ المنزَّل بلا رقم مهمّة: بطاقةُ المنصّة مباشرةً.
        if ($summaryJobId === null) {
            return $base.'/landing/og-'.$locale->value.'.png';
        }

        $version = substr(hash('sha256', implode("\u{1F}", array_map('strval', $fingerprint))), 0, 12);

        return "{$base}/share/{$summaryJobId}/{$locale->value}.png?v={$version}";
    }

    public static function path(int $summaryJobId, Locale $locale): string
    {
        return "share-cards/{$summaryJobId}/{$locale->value}.png";
    }

    public static function directory(int $summaryJobId): string
    {
        return "share-cards/{$summaryJobId}";
    }

    /** بطاقةُ المنصّة بلسان الخلاصة — ما يُسلَّم حين لا بطاقةَ مولَّدة. */
    public static function fallbackFile(Locale $locale): string
    {
        return public_path('landing/og-'.$locale->value.'.png');
    }
}
