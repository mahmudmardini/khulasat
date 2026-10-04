<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\SilenceCutPoints;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * ffmpeg and ffprobe, and the only place they are invoked — المواصفة §5-أ-4.
 *
 * والوسائط قائمةٌ لا سطرُ أوامر، كما في {@see YtDlp}: المسارات تأتي من رفعِ
 * مستخدم، واسمُ ملفٍّ فيه `;` أو `$(…)` يصير حقنَ أوامر لو مرّ على صدفة.
 *
 * @see khulasah-build-spec.md §5-أ-4
 */
class Ffmpeg
{
    /**
     * Read a media file's duration — المواصفة §5-أ-4-ب.
     *
     * **تُقرأ قبل أيّ معالجة**، فحدّ الاشتراك يُفحص عليها قبل أن يُصرف مورد.
     *
     * @throws TranscriptFailed
     */
    public function durationSeconds(string $path): float
    {
        $output = $this->run([
            $this->ffprobe(),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $path,
        ]);

        $duration = (float) trim($output);

        if ($duration <= 0.0) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تعذّرت قراءة مدّة الملفّ.',
            );
        }

        return $duration;
    }

    /**
     * Does the file carry a sound track at all?
     *
     * فيديو شاشةٍ بلا صوت تُقرأ مدّتُه سليمةً، ولا يظهر عيبُه إلّا في الطابور
     * بعد أن خُصمت الحصّة. فيُسأل عند الرفع.
     *
     * @throws TranscriptFailed إن لم يكن الملفّ وسائطَ مقروءة أصلاً.
     */
    public function hasAudioTrack(string $path): bool
    {
        $output = $this->run([
            $this->ffprobe(),
            '-v', 'error',
            '-select_streams', 'a:0',
            '-show_entries', 'stream=index',
            '-of', 'csv=p=0',
            $path,
        ]);

        return trim($output) !== '';
    }

    /**
     * Detect the silent stretches — المواصفة §5-أ-4.
     *
     * ‏`silencedetect` يكتب على `stderr` لا `stdout`، وهذا سلوكُ ffmpeg في
     * كلّ مرشّحاته التقريرية. ومن قرأ `stdout` عاد بقائمة فارغة **فقطع عند
     * زمنٍ ثابت وهو يحسب أنّه يقطع عند الصمت** — إخفاقٌ صامت لا يظهر إلّا
     * في النصّ النهائي.
     *
     * @return list<array{start: float, end: float}>
     */
    public function silences(string $path): array
    {
        return $this->detectSilences(
            $path,
            (string) config('khulasah.transcript.silence_noise_db', '-30dB'),
            (string) config('khulasah.transcript.silence_min_seconds', '0.5'),
        );
    }

    /**
     * Shorter, less quiet pauses — the fallback when no clear silence is near a cut.
     *
     * قاعةٌ فيها مروحةٌ أو صدى لا يهبط صوتُها إلى -30dB أبداً، فلا يجد الكشفُ
     * الصارم سكتةً واحدة ويقع كلّ قطعٍ عند الدقيقة العاشرة بالضبط. **لكنّ بين
     * الجملتين نَفَساً** يهبط عن صوت الكلام، وهذا ما يلتقطه هذا الكشف.
     *
     * @return list<array{start: float, end: float}>
     */
    public function softSilences(string $path): array
    {
        return $this->detectSilences(
            $path,
            (string) config('khulasah.transcript.silence_soft_noise_db', '-20dB'),
            (string) config('khulasah.transcript.silence_soft_min_seconds', '0.2'),
        );
    }

    /** @return list<array{start: float, end: float}> */
    private function detectSilences(string $path, string $noise, string $minimum): array
    {
        $stderr = $this->run([
            $this->ffmpeg(),
            '-i', $path,
            '-af', "silencedetect=noise={$noise}:d={$minimum}",
            '-f', 'null',
            '-',
        ], readErrorOutput: true);

        return self::parseSilences($stderr);
    }

    /**
     * One compact speech track for every source — mono, 16 kHz, AAC.
     *
     * **التقطيع نسخٌ بلا إعادة ترميز** ({@see splitAtSilence()})، فيحمل كلُّ
     * مقطعٍ شكلَ أصله: فيديو مرفوع يخرج مقطعُه بصورته، و`wav` يخرج بحجمه، وكلاهما
     * يتجاوز حدّ المزوّد بعد التقطيع نفسه. فيُوحَّد الشكل هنا مرّةً قبل التقطيع.
     *
     * **ولا يُفقد ما يُسمع**: Whisper وGemini كلاهما يُنزلان الصوت إلى قناةٍ
     * واحدة بـ16 كيلوهرتز قبل أن يسمعاه. و48kbps تجعل الدقيقة نحو 360KB، فالحجم
     * يُنبئ بالمدّة — وحدُّ المزوّد بالبايت يصير حدّاً بالدقائق تقريباً.
     *
     * @throws TranscriptFailed
     */
    public function toSpeechAudio(string $path, string $directory): string
    {
        $output = $directory.'/speech.m4a';

        $this->run([
            $this->ffmpeg(),
            '-nostdin',
            '-loglevel', 'error',
            '-i', $path,
            // أوّلُ مسار صوت وحده: لا صورة، ولا مسار تعليقٍ ثانٍ.
            '-map', '0:a:0',
            '-ac', '1',
            '-ar', '16000',
            '-c:a', 'aac',
            '-b:a', '48k',
            '-y',
            $output,
        ]);

        return $output;
    }

    /**
     * Cut the recording into ordered chunks at silence — المواصفة §5-أ-4.
     *
     * @return list<string> مسارات المقاطع **بترتيبها**، فالترتيب هو الدرس.
     *
     * @throws TranscriptFailed
     */
    public function splitAtSilence(string $path, string $directory, int $chunkSeconds): array
    {
        $duration = $this->durationSeconds($path);
        $silences = $this->silences($path);

        // السكتاتُ الليّنة لا تُطلب إلّا إن بقي قطعٌ أعمى، فلا يُفكّ الصوتُ مرّتين بلا داعٍ.
        $soft = SilenceCutPoints::blindCuts($duration, $silences, $chunkSeconds) > 0
            ? $this->softSilences($path)
            : [];

        $segments = SilenceCutPoints::segments($duration, $silences, $chunkSeconds, $soft);

        if (count($segments) <= 1) {
            return [$path];
        }

        $chunks = [];

        foreach ($segments as $index => $segment) {
            // رقمٌ مصفوفٌ بالأصفار: الترتيب المعجمي هو الترتيب الزمني، فلا
            // يعتمد الجمعُ على ترتيب نظام الملفّات.
            $chunk = $directory.'/'.sprintf('chunk-%03d.m4a', $index);

            $this->run([
                $this->ffmpeg(),
                '-nostdin',
                '-i', $path,
                '-ss', (string) $segment['start'],
                '-to', (string) $segment['end'],
                // نسخٌ بلا إعادة ترميز: أسرع، ولا يُفقد جودةً تُضعف التفريغ.
                '-c', 'copy',
                '-y',
                $chunk,
            ], readErrorOutput: true);

            $chunks[] = $chunk;
        }

        return $chunks;
    }

    /**
     * @param  list<string>  $command
     *
     * @throws TranscriptFailed
     */
    protected function run(array $command, bool $readErrorOutput = false): string
    {
        try {
            $result = Process::timeout($this->timeout())->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'تجاوزت معالجة الصوت المهلة.',
                $exception,
            );
        }

        if ($result->failed() && ! $readErrorOutput) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                trim($result->errorOutput()),
            );
        }

        return $readErrorOutput ? $result->errorOutput() : $result->output();
    }

    /**
     * أزواج `silence_start` و`silence_end` من تقرير ffmpeg.
     *
     * @return list<array{start: float, end: float}>
     */
    public static function parseSilences(string $report): array
    {
        preg_match_all('/silence_start:\s*(-?[\d.]+)/', $report, $starts);
        preg_match_all('/silence_end:\s*(-?[\d.]+)/', $report, $ends);

        $silences = [];

        foreach ($starts[1] as $index => $start) {
            // آخرُ سكتةٍ قد تبقى مفتوحةً إلى نهاية الملفّ بلا `silence_end`،
            // فتُترك: سكتةٌ لا نعرف نهايتها لا يُحسب لها وسط.
            if (! isset($ends[1][$index])) {
                continue;
            }

            $silences[] = [
                'start' => (float) $start,
                'end' => (float) $ends[1][$index],
            ];
        }

        return $silences;
    }

    protected function ffmpeg(): string
    {
        return (string) config('khulasah.transcript.ffmpeg_bin', 'ffmpeg');
    }

    protected function ffprobe(): string
    {
        return (string) config('khulasah.transcript.ffprobe_bin', 'ffprobe');
    }

    protected function timeout(): int
    {
        return (int) config('khulasah.transcript.ffmpeg_timeout', 900);
    }
}
