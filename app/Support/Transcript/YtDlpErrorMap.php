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

    /**
     * أعطالُ الطريق لا الفيديو — T-227. لا رمزَ لها في الجدول فتسقط إلى
     * `transcription_failed`، وهي مع الوكيل الدوّار عنوانٌ رديء لا رابطٌ رديء:
     * النداءُ التالي يخرج من عنوانٍ آخر.
     *
     * @var list<string>
     */
    private const NETWORK = [
        'ssl',
        'handshake',
        'proxy',
        'tunnel connection failed',
        'connection reset',
        'connection refused',
        'connection aborted',
        'remote end closed connection',
        'remotedisconnected',
        'unable to connect',
        'failed to connect',
        'network is unreachable',
        'temporary failure in name resolution',
        'name or service not known',
        'http error 502',
        'http error 503',
        'http error 504',
    ];

    private function __construct() {}

    /**
     * Whether another attempt could succeed where this one failed — T-227.
     *
     * **الفيديو لا يتغيّر بين محاولتين، والعنوانُ يتغيّر.** فما يقوله يوتيوب
     * عن الفيديو (خاصّ، محذوف، محجوب في البلد) دائم، وما يقوله عن العنوان
     * (روبوت، 429، 403) أو ما يقطع الطريقَ إليه (SSL، الوكيل، المهلة) عابر.
     * والتصنيف من {@see self::forStderr()} نفسه، فلا يُعاد ما رمزُه دائم ولو
     * حوى نصُّه كلمةً من أعطال الطريق.
     */
    public static function isTransient(string $stderr): bool
    {
        $haystack = str_replace("\u{2019}", "'", mb_strtolower($stderr));

        return match (self::forStderr($stderr)) {
            // «confirm your age» في قائمة الروبوت بنصّها، وهو قيدُ عمرٍ على
            // الفيديو لا حكمٌ على العنوان: لا تنفعه إعادة.
            TranscriptErrorCode::BotCheck => ! str_contains($haystack, 'confirm your age'),
            TranscriptErrorCode::YtdlpTimeout => true,
            TranscriptErrorCode::TranscriptionFailed => self::containsAny($haystack, self::NETWORK),
            default => false,
        };
    }

    /** @param  list<string>  $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

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
