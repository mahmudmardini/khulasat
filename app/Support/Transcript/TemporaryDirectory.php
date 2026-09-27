<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;

/**
 * A scratch directory for the transcription stage — المواصفة §5-أ-6 البند ٤.
 *
 * «مجلّد مؤقّت يُنظَّف بعدها». ويُحذف في `finally` لا بعد النجاح: المسار الذي
 * يُنظّف عند النجاح وحده يترك المخلّفات في **كلّ** إخفاق، وهي الحالة التي
 * تتكرّر. وملفّات الصوت مئاتُ الميغابايتات، فتمتلئ القرص في أيّام.
 *
 * والصلاحية `0700`: الملفّات تحوي درساً لجهةٍ بعينها، ولا شأن لبقيّة
 * مستخدمي الخادم بها.
 */
final class TemporaryDirectory
{
    private function __construct() {}

    /**
     * @throws TranscriptFailed
     */
    public static function make(string $prefix = 'khulasah-transcript-'): string
    {
        $directory = sys_get_temp_dir().'/'.$prefix.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّر إنشاء مجلّد مؤقّت.',
            );
        }

        return $directory;
    }

    public static function delete(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($directory);
    }
}
