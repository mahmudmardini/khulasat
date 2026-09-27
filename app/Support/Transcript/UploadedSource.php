<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * Classifies a file the user uploaded — المواصفة §5-أ-4-ب و§5-أ-5.
 *
 * **النوع يُفحص بالمحتوى لا بالامتداد.** الامتداد يكتبه المستخدم، فيصير
 * `درس.mp3` على ملفٍّ ليس صوتاً أصلاً — إمّا خطأً وإمّا قصداً. ونحن نمرّر
 * الملفّ إلى `ffmpeg` وإلى خدمة خارجية، فالاتّكال على الامتداد اتّكالٌ على
 * كلام المستخدم في موضع يُنفَّذ فيه.
 *
 * والامتداد يبقى شرطاً **زائداً** لملفّات الترجمة وحدها، لأنّ `.srt` و`.vtt`
 * و`.txt` كلّها نصٌّ عند فاحص المحتوى، ولا يميّزها إلا الامتداد.
 *
 * @see khulasah-build-spec.md §5-أ-4-ب
 */
final class UploadedSource
{
    /** كم يُقرأ من أوّل الملفّ لمعرفة نوعه — لا يُقرأ كلّه. */
    private const PROBE_BYTES = 8_192;

    private function __construct() {}

    /** صوتٌ أو فيديو يدخل مسار التفريغ — §5-أ-4-ب. */
    public static function isMedia(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        /** @var list<string> $allowed */
        $allowed = config('khulasah.transcript.upload.media_mimes', []);

        return in_array(self::mimeType($path), $allowed, strict: true);
    }

    /** نصٌّ أو ترجمة تدخل المسار اليدوي — §5-أ-5. */
    public static function isTextual(string $path, ?string $originalName = null): bool
    {
        if (! is_file($path)) {
            return false;
        }

        /** @var list<string> $extensions */
        $extensions = config('khulasah.transcript.upload.text_extensions', []);

        $extension = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));

        if (! in_array($extension, $extensions, strict: true)) {
            return false;
        }

        // والمحتوى نصٌّ فعلاً: امتدادٌ صحيح على ملفٍّ ثنائيّ لا يُقبل.
        return self::looksLikeText($path);
    }

    public static function sizeBytes(string $path): int
    {
        return is_file($path) ? (int) filesize($path) : 0;
    }

    public static function mimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return '';
        }

        // ولا `finfo_close`: مهجورة منذ PHP 8.4، والكائن يُغلق بجمع القمامة.
        $mime = finfo_file($finfo, $path);

        return $mime === false ? '' : $mime;
    }

    /**
     * نصٌّ لا ثنائيّ: يُقرأ أوّله ويُفحص أنّه UTF-8 بلا محرف نُلّي.
     *
     * ويُقرأ الأوّل وحده لا الملفّ كلّه: ملفُّ ترجمة قد يبلغ ميغابايتات،
     * ولا حاجة إلى قراءته كلّه لمعرفة أنّه نصّ.
     */
    private static function looksLikeText(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = fread($handle, self::PROBE_BYTES);
        fclose($handle);

        if ($head === false || $head === '') {
            return false;
        }

        if (str_contains($head, "\0")) {
            return false;
        }

        // **المحرف المبتور على حدّ القراءة.** الحرف العربي بايتان في UTF-8،
        // والقطعُ عند بايتٍ ثابت يشطره نصفين، فيسقط فحص الترميز على ملفٍّ
        // عربيّ سليم لمجرّد أنّه تجاوز حجم القراءة. وكلّ ملفّ ترجمة لدرسٍ
        // يتجاوزه. فتُسقَط بقايا المحرف الأخير — وأطولُ محرف أربعة بايتات،
        // فثلاثُ محاولات تكفي، ولا تُصحّح ملفّاً ثنائياً حقاً.
        if (self::sizeBytes($path) > strlen($head)) {
            for ($dropped = 0; $dropped < 3 && ! mb_check_encoding($head, 'UTF-8'); $dropped++) {
                $head = substr($head, 0, -1);
            }
        }

        return mb_check_encoding($head, 'UTF-8');
    }
}
