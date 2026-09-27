<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\CaptionTrack;
use App\Support\Transcript\Preflight;
use App\Support\Transcript\SourceUrlGuard;
use App\Support\Transcript\TemporaryDirectory;
use App\Support\Transcript\YtDlpErrorMap;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use JsonException;

/**
 * The yt-dlp process, and the only place it is invoked — المواصفة §5-أ.
 *
 * ثلاثة قيود تُبنى هنا لا في المُستدعي:
 *
 *   ١. **الوسائط قائمةٌ لا سطرُ أوامر.** الرابط مدخلُ مستخدم، ولو دُمج في
 *      نصٍّ يمرّ على صدفة لصار حقنَ أوامر. وقائمةُ السماح
 *      ({@see SourceUrlGuard}) حاجزٌ آخر لا بديل.
 *
 *   ٢. **مهلةٌ صارمة** من `YTDLP_TIMEOUT` — §5-أ-6 البند ٤: عمليةٌ معلّقة
 *      تحبس عاملاً في الطابور حتى يمتلئ الطابور بها.
 *
 *   ٣. **مجلّد مؤقّت يُنظَّف دائماً**، في `finally` لا بعد النجاح: المسار
 *      الذي يُنظّف عند النجاح وحده يترك المخلّفات في كلّ إخفاق، وهي الحالة
 *      التي تتكرّر.
 *
 * والمستخدم محدود الصلاحية يأتي من إعداد الخادم: يُشغَّل الطابور بمستخدم الموقع
 * غير الجذري. فلا يُرفع هنا امتياز.
 *
 * @see khulasah-build-spec.md §5-أ-1
 * @see khulasah-build-spec.md §5-أ-6
 */
class YtDlp
{
    /**
     * Read the cheap metadata call — المواصفة §5-أ-1.
     *
     * **لا يُنزّل شيئاً**، وعليه تُبنى قرارات الرفض قبل صرف أيّ مورد.
     *
     * @throws TranscriptFailed
     */
    public function preflight(string $url): Preflight
    {
        $result = $this->run(
            [
                $this->binary(),
                '--dump-json',
                '--no-download',
                '--no-playlist',
                '--',
                $url,
            ],
            // **بمهلته القصيرة** — T-124: هذه وحدها تجري في دورة الطلب،
            // فلا ترث مهلة التنزيل وتحبس عاملَ PHP-FPM خمس دقائق.
            timeout: $this->preflightTimeout(),
        );

        try {
            /** @var array<string, mixed> $json */
            $json = json_decode($result, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'مخرَج --dump-json غير قابل للقراءة.',
                $exception,
            );
        }

        if (! is_array($json)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'مخرَج --dump-json ليس كائناً.',
            );
        }

        return Preflight::fromDumpJson($json);
    }

    /**
     * Download one caption track and return its SRT text — المواصفة §5-أ-2.
     *
     * @throws TranscriptFailed
     */
    public function fetchCaptions(string $url, CaptionTrack $track): string
    {
        $directory = TemporaryDirectory::make();

        try {
            $this->run([
                $this->binary(),
                // **العلَم المطابق للمسار وحده.** لو مُرّر العلَمان معاً
                // لكتب yt-dlp المسارين بالاسم نفسه، فيغلب الآليّ اليدويَّ
                // وتنقلب أولوية §5-أ-2 صامتةً.
                $track->isManual() ? '--write-subs' : '--write-auto-subs',
                '--sub-langs',
                $track->languageCode,
                '--skip-download',
                // أيّ صيغة تصل تُحوَّل إلى srt، فيقرأ المحوّل شكلاً واحداً.
                '--convert-subs',
                'srt',
                '--no-playlist',
                '-o',
                '%(id)s',
                '--',
                $url,
            ], $directory);

            $files = glob($directory.'/*.srt') ?: [];

            if ($files === []) {
                // أخفق التنزيل بلا رسالة خطأ: المسار كان معروضاً في الفحص
                // المسبق ثم لم يصل. لا يُخمَّن نصٌّ، ولا يُقبل فراغ.
                throw TranscriptFailed::because(
                    TranscriptErrorCode::NoArabicSource,
                    "لم يُكتب ملفّ ترجمة للمسار {$track->languageCode}.",
                );
            }

            $contents = file_get_contents($files[0]);

            if ($contents === false || trim($contents) === '') {
                throw TranscriptFailed::because(
                    TranscriptErrorCode::NoArabicSource,
                    "ملفّ ترجمة فارغ للمسار {$track->languageCode}.",
                );
            }

            return $contents;
        } finally {
            TemporaryDirectory::delete($directory);
        }
    }

    /**
     * Pull the audio track only — المواصفة §5-أ-4، المسار الثالث.
     *
     * **الصوت وحده لا الفيديو**: التفريغ لا يحتاج الصورة، وتنزيلُها يستهلك
     * نطاقاً ووقتاً بلا فائدة، وقد يُخرج ملفّاً يتجاوز حدود المعالجة.
     *
     * @return string مسار ملفّ الصوت داخل `$directory`.
     *
     * @throws TranscriptFailed
     */
    public function extractAudio(string $url, string $directory): string
    {
        $this->run([
            $this->binary(),
            '-f', 'bestaudio',
            '-x',
            '--audio-format', 'm4a',
            '--audio-quality', '5',
            '--no-playlist',
            '-o', '%(id)s.%(ext)s',
            '--',
            $url,
        ], $directory);

        $files = glob($directory.'/*.m4a') ?: [];

        if ($files === []) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptionFailed,
                'لم يُكتب ملفّ صوت.',
            );
        }

        return $files[0];
    }

    /**
     * @param  list<string>  $command
     *
     * @throws TranscriptFailed
     */
    protected function run(array $command, ?string $directory = null, ?int $timeout = null): string
    {
        $command = $this->withProxy($command);
        $timeout ??= $this->timeout();

        $process = Process::timeout($timeout);

        if ($directory !== null) {
            $process = $process->path($directory);
        }

        try {
            $result = $process->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::YtdlpTimeout,
                "تجاوزت العملية {$timeout} ثانية.",
                $exception,
            );
        }

        if ($result->failed()) {
            // الرمز يُقرأ من stderr — §5-أ-6 البند ٣: عطلٌ مستقلّ برسالة
            // مفهومة، لا «فشل التفريغ» لكلّ شيء.
            throw TranscriptFailed::because(
                YtDlpErrorMap::forStderr($result->errorOutput()),
                trim($result->errorOutput()),
            );
        }

        return $result->output();
    }

    /**
     * Insert `--proxy` when one is configured — المواصفة §5-أ-6 البند ١.
     *
     * **وفارغاً لا يُمرَّر العلَم البتّة.** و`--proxy ''` ليس محايداً: يفهمه
     * yt-dlp أمراً بتعطيل الوكيل، فيخالف إعداد البيئة بدل أن يتركه.
     *
     * ويُدرَج بعد اسم البرنامج مباشرةً، قبل `--` والرابط.
     *
     * @param  list<string>  $command
     * @return list<string>
     */
    protected function withProxy(array $command): array
    {
        $proxy = trim((string) config('khulasah.transcript.ytdlp_proxy'));

        if ($proxy === '') {
            return $command;
        }

        return [$command[0], '--proxy', $proxy, ...array_slice($command, 1)];
    }

    protected function binary(): string
    {
        return (string) config('khulasah.transcript.ytdlp_bin');
    }

    protected function timeout(): int
    {
        return (int) config('khulasah.transcript.ytdlp_timeout');
    }

    protected function preflightTimeout(): int
    {
        return (int) config('khulasah.transcript.ytdlp_preflight_timeout');
    }
}
