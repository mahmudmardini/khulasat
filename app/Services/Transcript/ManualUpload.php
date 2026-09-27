<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\SrtConverter;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;
use App\Support\Transcript\UploadedSource;

/**
 * Text the user supplies directly — المواصفة §5-أ-5، المسار الخامس.
 *
 * «**يبقى متاحاً دائماً، وليس حالة طوارئ فقط.** يقبل: لصق نصّ، أو رفع
 * `.srt` أو `.vtt` أو `.txt`. ويمرّ على تحويل §5-أ-3 نفسه. **وهذا المسار
 * هو ما يُنقذ الجهة حين يُخفق كلّ ما سبق**.»
 *
 * وهو آخرُ الصفّ ولا يُخفق من نفسه: كلّ ما قبله يعتمد على منصّةٍ تحجب أو
 * خدمةٍ تتعطّل، وهذا لا يعتمد إلا على إنسانٍ معه النصّ. ولذلك **لا يُحتسب من
 * دقائق التفريغ** ({@see TranscriptSource::consumesTranscriptionMinutes()}).
 *
 * وملفّ الترجمة يمرّ على {@see SrtConverter} نفسه لا على محوّلٍ ثانٍ: نسختان
 * من إزالة التداخل تتباعدان، فيخرج النصّ من مسارٍ نظيفاً ومن آخر مضاعفاً.
 *
 * @see khulasah-build-spec.md §5-أ-5
 */
class ManualUpload implements TranscriptProvider
{
    public function name(): string
    {
        return 'manual_upload';
    }

    public function supports(TranscriptRequest $request): bool
    {
        if ($request->hasPastedText()) {
            return true;
        }

        return $request->hasUpload()
            && UploadedSource::isTextual((string) $request->uploadedPath, $request->uploadedName);
    }

    /**
     * @throws TranscriptFailed
     */
    public function fetch(TranscriptRequest $request): TranscriptResult
    {
        $raw = $request->hasPastedText()
            ? (string) $request->pastedText
            : $this->readUploadedFile($request);

        $text = $this->toProse($raw);

        $result = new TranscriptResult($text, TranscriptSource::Manual);

        // الحاجز الأدنى يسري هنا أيضاً — §5-أ-7. ولصقُ فقرةٍ سهوًا بدل درسٍ
        // كامل حالةٌ متوقّعة، وكشفُها الآن أرخص من كشفها بعد التوليد.
        if ($result->isTooShort()) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptTooShort,
                "النصّ {$result->wordCount()} كلمة، والحدّ ".TranscriptErrorCode::MINIMUM_WORDS.'.',
            );
        }

        return $result;
    }

    /**
     * @throws TranscriptFailed
     */
    private function readUploadedFile(TranscriptRequest $request): string
    {
        $path = (string) $request->uploadedPath;

        if (! is_file($path)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'لا ملفّ ولا نصّ ملصوق.',
            );
        }

        $limit = (int) config('khulasah.transcript.upload.text_max_bytes');

        if (UploadedSource::sizeBytes($path) > $limit) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'ملفّ النصّ يتجاوز الحدّ المسموح.',
            );
        }

        if (! UploadedSource::isTextual($path, $request->uploadedName)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'الملفّ ليس نصّاً ولا ترجمة.',
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّرت قراءة الملفّ المرفوع.',
            );
        }

        return $contents;
    }

    /**
     * Subtitles go through the §5-أ-3 converter; plain prose is left alone.
     *
     * ونصٌّ ملصوق لا توقيت فيه يعود من المحوّل فارغاً — فكلّ كتلةٍ فيه بلا
     * سطر توقيت تُترك. فيُميَّز أوّلاً: ما فيه توقيتات يُحوَّل، وما سواه
     * تُضغط مسافاته وحدها ويُترك تشكيله.
     */
    private function toProse(string $raw): string
    {
        if (self::looksLikeSubtitles($raw)) {
            return SrtConverter::toText($raw);
        }

        // فقرات المستخدم تُصان: سطران فارغان فاصلُ فقرة، والباقي مسافة.
        $normalized = preg_replace('/\R{2,}/u', "\n\n", trim($raw)) ?? $raw;
        $normalized = preg_replace('/[^\S\n]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/ ?\n ?/u', "\n", $normalized) ?? $normalized);
    }

    /** سطرُ توقيت `-->` هو علامة SRT وVTT معاً. */
    private static function looksLikeSubtitles(string $raw): bool
    {
        return preg_match('/\d{1,2}:\d{2}:\d{2}[.,]\d{1,3}\s*-->/', $raw) === 1;
    }
}
