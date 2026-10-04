<?php

declare(strict_types=1);

use App\Contracts\SpeechToText;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Enums\UsageEvent;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Transcript\Ffmpeg;
use App\Services\Transcript\WhisperAudio;
use App\Services\Transcript\YtDlp;
use App\Support\Transcript\TranscriptRequest;
use Illuminate\Support\Facades\Process;

// المواصفة §5-أ-4 و§5-أ-4-ب. ولا نداء تفريغ حقيقي ولا عملية شاردة:
// CLAUDE.md §2 القاعدة السابعة.

beforeEach(function (): void {
    Process::preventStrayProcesses();

    $this->tenant = Tenant::factory()->create(['max_lecture_minutes' => 180]);
    $this->scratch = sys_get_temp_dir().'/khulasah-audio-'.bin2hex(random_bytes(6));
    mkdir($this->scratch, 0700);
});

afterEach(function (): void {
    foreach (glob($this->scratch.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->scratch);
});

function audioRequest(?string $url = 'https://www.youtube.com/watch?v=abc123'): TranscriptRequest
{
    return TranscriptRequest::for(Lecture::factory()->create([
        'tenant_id' => test()->tenant->id,
        'source_url' => (string) $url,
    ]));
}

/** يُزيَّف {@see SpeechToText} بنصّ معلوم لكلّ مقطع. */
function fakeSpeech(?callable $onCall = null): object
{
    $spy = new class implements SpeechToText
    {
        /** @var list<array{path: string, language: string, glossary: list<string>}> */
        public array $calls = [];

        public string $reply = '';

        public function name(): string
        {
            return 'spy';
        }

        public function maxBytes(): int
        {
            return (int) config('khulasah.transcript.whisper.max_bytes');
        }

        public function pricePerMinute(): float
        {
            return (float) config('khulasah.transcript.whisper.price_per_minute');
        }

        public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
        {
            $this->calls[] = [
                'path' => $audioPath,
                'language' => $languageCode,
                'glossary' => $glossary,
            ];

            return str_replace('{n}', (string) count($this->calls), $this->reply);
        }
    };

    $spy->reply = implode(' ', array_fill(0, 600, 'كلمة')).' مقطع{n}';

    if ($onCall !== null) {
        $onCall($spy);
    }

    app()->instance(SpeechToText::class, $spy);

    return $spy;
}

/**
 * ffmpeg مُزيَّف: مدّة معلومة، وتقطيعٌ يُنتج مقاطع حقيقية على القرص.
 *
 * والتوحيدُ يُعيد الملفّ نفسه، فيبقى حجمه حجمَ ما نزّله الاختبار — وعليه
 * يُقرَّر التقطيع — ويُسجَّل ما وُحِّد في `normalized`.
 */
function fakeFfmpeg(float $duration, int $chunks = 1): Ffmpeg
{
    $fake = new class($duration, $chunks) extends Ffmpeg
    {
        /** @var list<string> */
        public array $normalized = [];

        public function __construct(private float $duration, private int $chunks) {}

        public function durationSeconds(string $path): float
        {
            return $this->duration;
        }

        public function toSpeechAudio(string $path, string $directory): string
        {
            $this->normalized[] = $path;

            return $path;
        }

        public function silences(string $path): array
        {
            return [];
        }

        public function splitAtSilence(string $path, string $directory, int $chunkSeconds): array
        {
            $paths = [];

            for ($i = 0; $i < $this->chunks; $i++) {
                $chunk = $directory.'/'.sprintf('chunk-%03d.m4a', $i);
                file_put_contents($chunk, 'audio');
                $paths[] = $chunk;
            }

            return $paths;
        }
    };

    app()->instance(Ffmpeg::class, $fake);

    return $fake;
}

/** yt-dlp مُزيَّف يكتب ملفّ صوت في مجلّد العملية. */
function fakeAudioDownload(int $bytes = 1_024): void
{
    app()->instance(YtDlp::class, new class($bytes) extends YtDlp
    {
        public function __construct(private int $bytes) {}

        public function extractAudio(string $url, string $directory): string
        {
            $path = $directory.'/abc123.m4a';
            file_put_contents($path, str_repeat('a', $this->bytes));

            return $path;
        }
    });
}

function whisper(): WhisperAudio
{
    return app(WhisperAudio::class);
}

// ── المسار الثالث: رابط بلا ترجمة عربية ──────────────────────────

it('transcribes audio pulled from the link', function (): void {
    fakeAudioDownload();
    fakeFfmpeg(duration: 1_800.0);
    $speech = fakeSpeech();

    $result = whisper()->fetch(audioRequest());

    expect($result->source)->toBe(TranscriptSource::Whisper)
        ->and($speech->calls)->toHaveCount(1);
});

// **صوتٌ واحدُ الشكل لكلّ مصدر** — يوتيوب بلا ترجمة والملفّ المرفوع سواء،
// فلا يبلغ المزوّدَ فيديو ولا `wav` ضخم بعد التقطيع.
it('normalizes the audio before it reaches the speech service', function (): void {
    fakeAudioDownload();
    $ffmpeg = fakeFfmpeg(duration: 1_800.0);
    $speech = fakeSpeech();

    whisper()->fetch(audioRequest());

    expect($ffmpeg->normalized)->toHaveCount(1)
        ->and($ffmpeg->normalized[0])->toEndWith('/abc123.m4a')
        ->and($speech->calls[0]['path'])->toBe($ffmpeg->normalized[0]);
});

// **`language=ar` إلزامياً** — §5-أ-4. وبلا تصريح باللغة قد يُخمّنها المزوّد
// فيقرأ العربية نقلاً لاتينياً أو يُترجمها، وكلاهما يُفسد ألفاظ الشواهد.
it('always asks for Arabic, and sends the glossary with it', function (): void {
    fakeAudioDownload();
    fakeFfmpeg(duration: 1_800.0);
    $speech = fakeSpeech();

    whisper()->fetch(audioRequest());

    expect($speech->calls[0]['language'])->toBe('ar')
        ->and($speech->calls[0]['glossary'])->not->toBeEmpty()
        // المسرد من ملفّ الإعدادات القابل للتوسيع — §5-أ-4.
        ->and($speech->calls[0]['glossary'])->toContain('صحيح البخاري')
        ->and($speech->calls[0]['glossary'])->toContain('شعيب الأرناؤوط');
});

// ── التقطيع — §5-أ-4 ─────────────────────────────────────────────

// «إن تجاوز الملفّ حدّ المزوّد» — والحدّ حجمٌ لا مدّة.
it('does not split a file within the provider limit', function (): void {
    config()->set('khulasah.transcript.whisper.max_bytes', 10_000);
    fakeAudioDownload(bytes: 1_000);
    fakeFfmpeg(duration: 3_600.0, chunks: 4);
    $speech = fakeSpeech();

    whisper()->fetch(audioRequest());

    expect($speech->calls)->toHaveCount(1);
});

it('splits a file past the provider limit and transcribes each chunk', function (): void {
    config()->set('khulasah.transcript.whisper.max_bytes', 500);
    fakeAudioDownload(bytes: 5_000);
    fakeFfmpeg(duration: 3_600.0, chunks: 4);
    $speech = fakeSpeech();

    whisper()->fetch(audioRequest());

    expect($speech->calls)->toHaveCount(4);
});

// **«تُجمع المقاطع بترتيبها»** — §5-أ-4. ومقطعان مقلوبان يُخرجان نصّاً
// مفهوم الجُمل مقلوب المعنى، وهو أسوأ من نصٍّ ناقص لأنّه لا يُرى.
it('joins the chunks in order', function (): void {
    config()->set('khulasah.transcript.whisper.max_bytes', 500);
    fakeAudioDownload(bytes: 5_000);
    fakeFfmpeg(duration: 3_600.0, chunks: 3);
    fakeSpeech();

    $text = whisper()->fetch(audioRequest())->text;

    expect(mb_strpos($text, 'مقطع1'))->toBeLessThan(mb_strpos($text, 'مقطع2'))
        ->and(mb_strpos($text, 'مقطع2'))->toBeLessThan(mb_strpos($text, 'مقطع3'));
});

// ── الدقائق في السجلّ — §5-أ-4 و§11 ──────────────────────────────

it('charges the transcription minutes to the ledger', function (): void {
    fakeAudioDownload();
    fakeFfmpeg(duration: 1_800.0);
    fakeSpeech();

    whisper()->fetch(audioRequest());

    $row = UsageRecord::query()->where('tenant_id', $this->tenant->id)->sole();

    expect($row->event)->toBe(UsageEvent::Transcribe)
        ->and($row->units)->toBe(30);
});

/*
 * ★ T-22: كانت `cost_usd` تُترك صفرها الافتراضي دوماً — سقفُ الإنفاق (T-13)
 * لا يرى كلفة التفريغ قطّ. صارت تُحسب من `price_per_minute` في الإعداد.
 */
it('charges the real cost of transcription, not zero', function (): void {
    config()->set('khulasah.transcript.whisper.price_per_minute', 0.006);

    fakeAudioDownload();
    fakeFfmpeg(duration: 1_800.0); // ٣٠ دقيقة
    fakeSpeech();

    whisper()->fetch(audioRequest());

    $row = UsageRecord::query()->where('tenant_id', $this->tenant->id)->sole();

    expect((float) $row->cost_usd)->toBe(0.18); // ٣٠ × ٠٫٠٠٦
});

// تُقرَّب الدقيقة إلى أعلى: المزوّدون يحاسبون كذلك.
it('rounds a part minute up', function (): void {
    fakeAudioDownload();
    fakeFfmpeg(duration: 61.0);
    fakeSpeech();

    whisper()->fetch(audioRequest());

    expect(UsageRecord::query()->sole()->units)->toBe(2);
});

// والتفريغ الصوتي وحده يُحتسب من دقائق التفريغ — §11.
it('is the path that consumes transcription minutes', function (): void {
    expect(TranscriptSource::Whisper->consumesTranscriptionMinutes())->toBeTrue()
        ->and(TranscriptSource::Manual->consumesTranscriptionMinutes())->toBeFalse()
        ->and(TranscriptSource::Captions->consumesTranscriptionMinutes())->toBeFalse();
});

// ── المدّة قبل أيّ معالجة — §5-أ-4-ب و§11 ────────────────────────

it('refuses a recording longer than the tenant limit before transcribing', function (): void {
    $strict = Tenant::factory()->create(['max_lecture_minutes' => 10]);

    fakeAudioDownload();
    fakeFfmpeg(duration: 3_600.0);
    $speech = fakeSpeech();

    $request = TranscriptRequest::for(Lecture::factory()->create([
        'tenant_id' => $strict->id,
        'source_url' => 'https://www.youtube.com/watch?v=abc123',
    ]));

    try {
        whisper()->fetch($request);
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::DurationExceeded)
            // ولم يُفرَّغ شيء، ولم تُخصم دقيقة.
            ->and($speech->calls)->toBeEmpty()
            ->and(UsageRecord::query()->count())->toBe(0);

        return;
    }

    $this->fail('كان يجب أن تُرفض المدّة.');
});

// ── المسار الرابع: رفع ملفّ من الجهاز — §5-أ-4-ب ─────────────────

// «درس مسجَّل بالجوال لم يُرفع إلى يوتيوب» — حالة حقيقية متكرّرة.
it('transcribes an uploaded recording without touching yt-dlp', function (): void {
    Process::fake();

    $path = $this->scratch.'/lesson.wav';
    // ترويسة WAV صحيحة، فيقرأها فاحص المحتوى صوتاً.
    file_put_contents($path, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x40\x1f\x00\x00\x80>\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00");

    fakeFfmpeg(duration: 900.0);
    fakeSpeech();

    $result = whisper()->fetch(audioRequest(url: null)->withUploadedFile($path));

    expect($result->source)->toBe(TranscriptSource::Whisper);

    Process::assertNothingRan();
});

// النوع بالمحتوى لا بالامتداد — الامتداد يكتبه المستخدم.
it('refuses a file that only claims to be audio', function (): void {
    $path = $this->scratch.'/lesson.mp3';
    file_put_contents($path, 'هذا نصّ لا صوت');

    expect(whisper()->supports(audioRequest(url: null)->withUploadedFile($path)))->toBeFalse();
});

it('refuses an upload past five hundred megabytes', function (): void {
    config()->set('khulasah.transcript.upload.max_bytes', 8);

    $path = $this->scratch.'/lesson.wav';
    file_put_contents($path, "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x40\x1f\x00\x00\x80>\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00");

    fakeFfmpeg(duration: 900.0);
    fakeSpeech();

    expect(fn () => whisper()->fetch(audioRequest(url: null)->withUploadedFile($path)))
        ->toThrow(TranscriptFailed::class);
});

// ── الحدّ الأدنى — §5-أ-7 ────────────────────────────────────────

it('stops a transcript shorter than five hundred words', function (): void {
    fakeAudioDownload();
    fakeFfmpeg(duration: 900.0);
    fakeSpeech(function (object $spy): void {
        $spy->reply = 'بسم الله الرحمن الرحيم';
    });

    try {
        whisper()->fetch(audioRequest());
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::TranscriptTooShort);

        return;
    }

    $this->fail('كان يجب أن يُرفض النصّ القصير.');
});

// ── متى يُدعَم ───────────────────────────────────────────────────

it('is not tried when the user pasted text', function (): void {
    expect(whisper()->supports(audioRequest()->withPastedText('نصّ')))->toBeFalse()
        ->and(whisper()->supports(audioRequest()))->toBeTrue()
        ->and(whisper()->supports(audioRequest(url: 'https://vimeo.com/1')))->toBeFalse()
        ->and(whisper()->name())->toBe('whisper_audio');
});
