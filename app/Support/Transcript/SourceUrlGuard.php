<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;

/**
 * Guards the user-supplied lecture URL before it reaches yt-dlp — المواصفة §12.
 *
 * الرابط يأتي من المستخدم ويُمرَّر إلى عملية خارجية تُجري نداءً شبكياً، وهذا
 * بعينه SSRF. **والحاجز الأوّل قائمةُ السماح لا فحصُ العنوان**: القائمة تُجيز
 * مضيفين معلومين، فكلّ صور التمويه — العنوان العشري و`user:pass@` والنطاق
 * المتشابه — تسقط بها قبل أن تُفحص واحدةً واحدةً.
 *
 * وما بعدها فحوصٌ للتعمّق لا للاعتماد، ولأنّ الخطأ الواضح خيرٌ من الرفض الغامض.
 *
 * **ما ليس هنا:** إعادةُ ربط DNS — مضيفٌ مُجاز يعود بعنوان داخلي. وقائمةُ
 * السماح اليوم مضيفان معلومان لا يُتوقّع منهما ذلك، والعلاج الصحيح فحصُ
 * العنوان بعد الحلّ داخل العملية نفسها، وهو غير متاح لنا من خارج yt-dlp.
 *
 * @see khulasah-build-spec.md §12 المخطر الثالث
 * @see khulasah-build-spec.md §5-أ-1 الفحص الأوّل
 */
final class SourceUrlGuard
{
    /** أسماء ولواحق تدلّ على الشبكة الداخلية. */
    private const LOCAL_SUFFIXES = [
        'localhost',
        '.localhost',
        '.local',
        '.internal',
        '.localdomain',
        '.home.arpa',
    ];

    private function __construct() {}

    /**
     * @throws TranscriptFailed برمز `host_not_allowed`.
     */
    public static function assertAllowed(string $url): void
    {
        $url = trim($url);

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::HostNotAllowed,
                'رابط غير قابل للتحليل.',
            );
        }

        // مخطّط غير http(s) يفتح `file://` و`gopher://` و`dict://` وغيرها،
        // وهي طرق SSRF معروفة تقرأ ملفّات الخادم أو تُخاطب خدماته.
        $scheme = strtolower($parts['scheme'] ?? '');

        if (! in_array($scheme, ['http', 'https'], strict: true)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::HostNotAllowed,
                "مخطّط غير مسموح: {$scheme}.",
            );
        }

        // `https://youtube.com@127.0.0.1/` — المضيف الحقيقي ما بعد @. يقرأه
        // parse_url صحيحاً، ويُرفض هنا زيادةً لأنّ رابط درسٍ لا يحمل اعتماداً.
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::HostNotAllowed,
                'الرابط يحمل اعتماد دخول.',
            );
        }

        $host = self::normalizeHost($parts['host']);

        self::assertNotInternal($host);
        self::assertOnAllowList($host);
    }

    /**
     * Whether the link points at a playlist rather than one video.
     *
     * المواصفة §5-أ-1 الفحص الثالث. وليس من الأكواد العشرة: هذا خطأ إدخال
     * يُصلحه المستخدم في النموذج نفسه قبل أن تُنشأ مهمّة، فلا يُكتب في
     * `summary_jobs.error_code` أصلاً.
     *
     * و`--no-playlist` في أمر yt-dlp لا يكفي وحده: يأخذ الفيديو الأوّل صامتاً،
     * فيُلخَّص درسٌ غير الذي قصده المستخدم.
     */
    public static function isPlaylist(string $url): bool
    {
        $parts = parse_url(trim($url));

        if ($parts === false) {
            return false;
        }

        $path = strtolower($parts['path'] ?? '');

        if (in_array($path, ['/playlist', '/playlist/'], strict: true)) {
            return true;
        }

        parse_str($parts['query'] ?? '', $query);

        // `?list=` مع `?v=` رابطُ فيديو داخل قائمة، وهو مقبول: الفيديو معلوم.
        // أمّا `?list=` وحدها فقائمةٌ لا فيديو فيها.
        return isset($query['list']) && ! isset($query['v']);
    }

    /**
     * @throws TranscriptFailed
     */
    private static function assertNotInternal(string $host): void
    {
        foreach (self::LOCAL_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, $suffix)) {
                throw TranscriptFailed::because(
                    TranscriptErrorCode::HostNotAllowed,
                    "مضيف داخلي: {$host}.",
                );
            }
        }

        // **كلّ عنوان رقميّ مرفوض**، لا الداخليّ وحده: قائمة السماح أسماءٌ
        // لا عناوين، فعنوانٌ رقميّ لا يكون مُجازاً بحال. والتمييز بين الداخليّ
        // والعامّ للرسالة في السجلّ وحدها.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $isPublic = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            ) !== false;

            throw TranscriptFailed::because(
                TranscriptErrorCode::HostNotAllowed,
                $isPublic
                    ? "عنوان رقميّ لا اسم مضيف: {$host}."
                    : "عنوان داخلي أو محجوز: {$host}.",
            );
        }

        // صور العنوان المموّهة: `2130706433` و`0x7f000001` و`0177.1` كلّها
        // 127.0.0.1، ويقبلها كثير من المحلّلين. لا تُحلَّل هنا ولا تُقارَن —
        // **تُرفض بشكلها**، فالمضيف الرقميّ لا مكان له في قائمة أسماء.
        if (preg_match('/^(?:0x[0-9a-f]+|\d+)(?:\.(?:0x[0-9a-f]+|\d+))*$/', $host) === 1) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::HostNotAllowed,
                "صورة عنوان رقميّ مموّهة: {$host}.",
            );
        }
    }

    /**
     * @throws TranscriptFailed
     */
    private static function assertOnAllowList(string $host): void
    {
        /** @var list<string> $allowed */
        $allowed = config('khulasah.transcript.allowed_hosts', []);

        foreach ($allowed as $candidate) {
            $candidate = self::normalizeHost($candidate);

            // المطابقة تامّة أو على حدّ نقطة. ولو استُعمل `str_contains` أو
            // `str_ends_with` وحده لمرّ `youtube.com.evil.com` و`notyoutube.com`.
            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return;
            }
        }

        throw TranscriptFailed::because(
            TranscriptErrorCode::HostNotAllowed,
            "مضيف خارج قائمة السماح: {$host}.",
        );
    }

    /**
     * صورة المضيف التي تُقارَن: صغيرة الأحرف، بلا نقطة أخيرة، بلا أقواس IPv6.
     *
     * والنقطة الأخيرة تُحذف لأنّ `youtube.com.` مضيفٌ صحيح يُطابق `youtube.com`
     * في DNS، ولا يُطابقه في المقارنة النصّية — فيمرّ لو تُرك.
     */
    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = rtrim($host, '.');

        return trim($host, '[]');
    }
}
