<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * Strips the proxy's username and password from text bound for a log — T-227.
 *
 * yt-dlp يكتب عنوانَ الوكيل في stderr حين يتعذّر الاتّصال به، **بنصّه كما
 * أُعطي**: `http://user:pass@host:port`. وstderr يصير رسالةَ الاستثناء، ومنها
 * إلى السجلّ وإلى `summary_jobs` — فيُمحى قبل ذلك كلّه، لا عند العرض.
 *
 * وجهان معاً:
 *   - **كلمةُ مرور الوكيل المُعدّ بنصّها**، حيثما وردت ولو خارج رابط.
 *   - **كلُّ `scheme://user:pass@`** ولو لم يطابق الإعداد: الوكيلُ الدوّار
 *     يُلحق باسم المستخدم جلسةً تتبدّل، فلا يُعتمد على المطابقة الحرفية وحدها.
 */
final class ProxyCredentials
{
    private const MASK = '***';

    private function __construct() {}

    public static function redact(string $text): string
    {
        // كلمةُ المرور وحدها لا اسمُ المستخدم: اسمٌ قصير مثل «user» يمحو
        // كلماتٍ من نصّ الخطأ نفسه، والرابطُ يُمحى بالنمط بعده على كلّ حال.
        $password = parse_url(trim((string) config('khulasah.transcript.ytdlp_proxy')), PHP_URL_PASS);

        if (is_string($password) && $password !== '') {
            $text = str_replace([$password, rawurldecode($password)], self::MASK, $text);
        }

        return (string) preg_replace('~(\b[a-z][a-z0-9+.-]*://)[^\s/@]+@~i', '$1'.self::MASK.'@', $text);
    }
}
