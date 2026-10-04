<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Actions\Usage\RecordUsage;
use App\Contracts\SpeechToText;
use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Enums\UsageEvent;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\SourceUrlGuard;
use App\Support\Transcript\TemporaryDirectory;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;
use App\Support\Transcript\UploadedSource;

/**
 * Lecture text from the audio itself — المواصفة §5-أ-4، المسار ٣ و٤.
 *
 * يخدم مصدرين ومسارُهما واحد بعد أوّل خطوة:
 *   ٣. رابطٌ بلا ترجمة عربية ← يُستخرج صوته بـ yt-dlp.
 *   ٤. ملفٌّ رُفع من الجهاز ← **بلا yt-dlp أصلاً** (§5-أ-4-ب)، وهي حالةٌ
 *      حقيقية متكرّرة: درسٌ مسجَّل بالجوال لم يُرفع إلى يوتيوب.
 *
 * وثلاثة قيود من المواصفة تُنفَّذ هنا:
 *   - **المدّة تُفحص قبل أيّ معالجة** — §5-أ-4-ب و§11.
 *   - **التقطيع عند الصمت** لا عند زمن ثابت — §5-أ-4.
 *   - **`language=ar` إلزامياً**، ومعه مسرد المصطلحات.
 *
 * وهذا المسار وحده **يُحتسب من `transcription_minutes_quota`**
 * ({@see TranscriptSource::consumesTranscriptionMinutes()}).
 *
 * @see khulasah-build-spec.md §5-أ-4
 */
class WhisperAudio implements TranscriptProvider
{
    public function __construct(
        private readonly YtDlp $ytDlp,
        private readonly Ffmpeg $ffmpeg,
        private readonly SpeechToText $speech,
        private readonly RecordUsage $recordUsage,
    ) {}

    public function name(): string
    {
        return 'whisper_audio';
    }

    public function supports(TranscriptRequest $request): bool
    {
        // نصٌّ ملصوق لا يُفرَّغ، وملفُّ ترجمة يمرّ بالمسار اليدوي أرخص وأدقّ.
        if ($request->hasPastedText()) {
            return false;
        }

        if ($request->hasUpload()) {
            return UploadedSource::isMedia((string) $request->uploadedPath);
        }

        if (! $request->hasSourceUrl()) {
            return false;
        }

        try {
            SourceUrlGuard::assertAllowed($request->sourceUrl());
        } catch (TranscriptFailed) {
            return false;
        }

        return ! SourceUrlGuard::isPlaylist($request->sourceUrl());
    }

    /**
     * @throws TranscriptFailed
     */
    public function fetch(TranscriptRequest $request): TranscriptResult
    {
        $directory = TemporaryDirectory::make();

        try {
            $audio = $this->resolveAudio($request, $directory);

            // **قبل أيّ معالجة** — §5-أ-4-ب. والفحص هنا لا في الفحص المسبق
            // وحده: الملفّ المرفوع لا يمرّ على yt-dlp فلا مدّة له قبل ffprobe.
            $duration = $this->ffmpeg->durationSeconds($audio);
            $this->assertWithinDuration($request, $duration);

            // **صوتٌ واحدُ الشكل لكلّ مصدر** — بعد فحص المدّة لا قبله. فيديو
            // مرفوع أو `wav` ضخم يُقطَّع بالنسخ فيخرج مقطعُه أكبر من حدّ المزوّد.
            $speechAudio = $this->ffmpeg->toSpeechAudio($audio, $directory);

            $text = $this->transcribeInOrder($speechAudio, $directory);

            $this->chargeMinutes($request, $duration);

            $result = new TranscriptResult($text, TranscriptSource::Whisper);

            if ($result->isTooShort()) {
                throw TranscriptFailed::because(
                    TranscriptErrorCode::TranscriptTooShort,
                    "النصّ {$result->wordCount()} كلمة، والحدّ ".TranscriptErrorCode::MINIMUM_WORDS.'.',
                );
            }

            return $result;
        } finally {
            TemporaryDirectory::delete($directory);
        }
    }

    /**
     * المسار الرابع بلا yt-dlp، والثالث به — §5-أ-4-ب.
     *
     * @throws TranscriptFailed
     */
    private function resolveAudio(TranscriptRequest $request, string $directory): string
    {
        if ($request->hasUpload()) {
            $path = (string) $request->uploadedPath;

            $this->assertUploadAcceptable($path);

            return $path;
        }

        return $this->ytDlp->extractAudio($request->sourceUrl(), $directory);
    }

    /**
     * @throws TranscriptFailed
     */
    private function assertUploadAcceptable(string $path): void
    {
        $limit = (int) config('khulasah.transcript.upload.max_bytes');

        if (UploadedSource::sizeBytes($path) > $limit) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'الملفّ المرفوع يتجاوز الحدّ المسموح.',
            );
        }

        // النوع بالمحتوى لا بالامتداد: الامتداد يكتبه المستخدم، والملفّ
        // يُمرَّر إلى ffmpeg وإلى خدمة خارجية.
        if (! UploadedSource::isMedia($path)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'الملفّ المرفوع ليس صوتاً ولا فيديو.',
            );
        }
    }

    /**
     * التقطيع عند الصمت ثم الجمع بالترتيب — §5-أ-4.
     *
     * @throws TranscriptFailed
     */
    private function transcribeInOrder(string $audio, string $directory): string
    {
        $chunks = $this->needsSplitting($audio)
            ? $this->ffmpeg->splitAtSilence(
                $audio,
                $directory,
                (int) config('khulasah.transcript.chunk_seconds', 600),
            )
            : [$audio];

        /** @var list<string> $glossary */
        $glossary = config('glossary.transcript', []);

        $parts = [];

        foreach ($chunks as $chunk) {
            // `language=ar` إلزامياً — §5-أ-4. وبلا تصريح باللغة قد يُخمّنها
            // المزوّد فيقرأ العربية نقلاً لاتينياً أو يُترجمها.
            $parts[] = trim($this->speech->transcribe($chunk, 'ar', $glossary));
        }

        // **بترتيبها** — §5-أ-4. والترتيب هو الدرس: مقطعان مقلوبان يُخرجان
        // نصّاً مفهوم الجُمل مقلوب المعنى، وهو أسوأ من نصٍّ ناقص لأنّه لا يُرى.
        return trim(implode("\n\n", array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        )));
    }

    /** «إن تجاوز الملفّ حدّ المزوّد» — §5-أ-4. والحدّ حجمٌ لا مدّة، ومن المزوّد نفسه. */
    private function needsSplitting(string $audio): bool
    {
        return UploadedSource::sizeBytes($audio) > $this->speech->maxBytes();
    }

    /**
     * @throws TranscriptFailed
     */
    private function assertWithinDuration(TranscriptRequest $request, float $duration): void
    {
        $maxMinutes = $request->maxLectureMinutes();

        if ($maxMinutes > 0 && $duration > $maxMinutes * 60) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::DurationExceeded,
                sprintf('مدّة التسجيل %.0f ثانية، والحدّ %d دقيقة.', $duration, $maxMinutes),
            );
        }
    }

    /**
     * «تُسجَّل الدقائق في `usage_ledger` بحدث `transcribe`» — §5-أ-4.
     *
     * وتُقرَّب الدقيقة إلى أعلى: المزوّدون يحاسبون كذلك، ودرسٌ من ٦١ ثانية
     * يُدفع عنه دقيقتان. والقيد هنا لا في مكان آخر — {@see RecordUsage}.
     *
     * **والكلفة تُقيَّد معها الآن** — T-22. كانت `costUsd` تُترك على صفرها
     * الافتراضي، فسقفُ الإنفاق (T-13) لا يرى كلفة التفريغ قطّ، وشاشةُ
     * الكلفة لا تجد لها رقماً. والسعر من المزوّد المضبوط لا مكتوباً هنا،
     * فيتبع تغيّر مزوّد التفريغ بلا مسّ الكود.
     */
    private function chargeMinutes(TranscriptRequest $request, float $duration): void
    {
        $minutes = max(1, (int) ceil($duration / 60));

        $this->recordUsage->handle(
            tenant: $request->lecture->tenant,
            event: UsageEvent::Transcribe,
            units: $minutes,
            costUsd: $minutes * $this->speech->pricePerMinute(),
        );
    }
}
