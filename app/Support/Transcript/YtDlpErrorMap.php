<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Enums\TranscriptErrorCode;

/**
 * Reads yt-dlp's stderr into one of the ten codes — المواصفة §5-أ-6 و§5-أ-7.
 *
 * المواصفة §5-أ-6 البند ٣ صريحة: «أكواد خطأ مستقلّة ورسائل مفهومة، **لا فشل
 * التفريغ**». والفرق عمليّ لا تجميليّ: «الفيديو خاصّ» يُصلحه صاحبه في دقيقة،
 * و«فشل التفريغ» يُرجِع مدير المحتوى إلينا يسأل.
 *
 * **والترتيب هو المنطق كلّه.** رسائل يوتيوب متداخلة: رسالةُ الحجب الجغرافي
 * تحوي «Video unavailable» بنصّها، فلو فُحص التعذّر قبلها لصار كلّ حجب
 * جغرافيّ «فيديو غير موجود» — ولأرسلنا المستخدم يبحث عن رابط سليم بين يديه.
 * فتُفحَص الأخصّ قبل الأعمّ.
 *
 * @see khulasah-build-spec.md §5-أ-6
 * @see khulasah-build-spec.md §5-أ-7
 */
final class YtDlpErrorMap
{
    /**
     * الأنماط بترتيب الأولوية — الأخصّ أوّلاً. **لا تُعَد ترتيباً أبجدياً.**
     *
     * @var list<array{0: TranscriptErrorCode, 1: list<string>}>
     */
    private const PATTERNS = [
        // ١. فحص «لست روبوتاً» — §5-أ-6 البند ١: واقعٌ متكرّر على عناوين
        //    مراكز البيانات لا احتمال، وهو وحده ما يُحوّل إلى المسار اليدوي.
        //    ويُفحص أوّلاً لأنّ يوتيوب يُلحقه بنصوص تعذّرٍ عامّة.
        [TranscriptErrorCode::BotCheck, [
            "sign in to confirm you're not a bot",
            'sign in to confirm your age',
            'confirm you are not a bot',
            'http error 429',
            'too many requests',
            // حجب عنوان جهة أخرىات يظهر ٤٠٣ بلا نصّ أوضح.
            'http error 403',
            'unable to download api page',
        ]],

        // ٢. يحتاج حساباً: الخاصّ، والمقصور على المشتركين، والمقيَّد بالعمر.
        [TranscriptErrorCode::VideoPrivate, [
            'private video',
            'this video is private',
            'members-only',
            'members only',
            'join this channel',
            'age-restricted',
            'age restricted',
            'sign in if you',
        ]],

        // ٣. الحجب الجغرافي — **قبل التعذّر العامّ**، لأنّ نصّه يحويه.
        [TranscriptErrorCode::GeoBlocked, [
            'not made this video available in your country',
            'not available in your country',
            'blocked it in your country',
            'blocked in your country',
            'geo restricted',
            'geo-restricted',
            'available in your location',
        ]],

        // ٤. غير موجود أو محذوف.
        [TranscriptErrorCode::VideoUnavailable, [
            'video unavailable',
            'has been removed',
            'has been terminated',
            'no longer available',
            'does not exist',
            'incomplete youtube id',
            'unsupported url',
            'is not a valid url',
            'unable to extract',
        ]],

        // ٥. المهلة قد ترد من yt-dlp نفسه لا من قاطع العملية وحده.
        [TranscriptErrorCode::YtdlpTimeout, [
            'timed out',
            'timeout',
        ]],
    ];

    private function __construct() {}

    /**
     * والافتراضي `transcription_failed`: عطلٌ لم نعرفه بعدُ، ورسالتُه تقترح
     * إعادةً ثم المسار اليدوي. ولا يُترك المستخدم بلا إجراء.
     */
    public static function forStderr(string $stderr): TranscriptErrorCode
    {
        // يوتيوب يكتب الفاصلة العليا مقوَّسةً (’) لا مستقيمة (')، ونمط
        // «you're» هنا مستقيمٌ — فيُطبَّع الفارق قبل المطابقة، وإلا فشلت
        // مطابقة «bot_check» صامتةً وسقطت إلى `transcription_failed`.
        $haystack = str_replace("\u{2019}", "'", mb_strtolower($stderr));

        foreach (self::PATTERNS as [$code, $needles]) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $code;
                }
            }
        }

        return TranscriptErrorCode::TranscriptionFailed;
    }
}
