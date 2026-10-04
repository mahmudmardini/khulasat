<?php

declare(strict_types=1);

use App\Contracts\SpeechToText;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Services\Transcript\Ffmpeg;
use App\Services\Transcript\Speech\FakeSpeechToText;
use App\Services\Transcript\Speech\GeminiSpeech;
use App\Services\Transcript\Speech\WhisperApi;
use App\Services\Transcript\WhisperAudio;
use App\Services\Transcript\YtDlp;
use App\Support\Transcript\TranscriptRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// Gemini مزوّداً للتفريغ — قرار الفريق، 4 أكتوبر 2026. ولا نداء حقيقي:
// CLAUDE.md §2 القاعدة السابعة، فكلّ ردٍّ هنا مُزيَّف.

beforeEach(function (): void {
    Http::preventStrayRequests();

    config()->set('khulasah.model.google.api_key', 'test-google-key');
    config()->set('khulasah.model.google.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models');
    config()->set('khulasah.transcript.gemini.model', 'gemini-3.7-flash');
    config()->set('khulasah.transcript.gemini.retry_sleep_ms', 0);

    $this->clip = tempnam(sys_get_temp_dir(), 'gemini-clip-').'.m4a';
    file_put_contents($this->clip, 'audio-bytes');
});

afterEach(function (): void {
    @unlink($this->clip);
});

/** ردُّ Gemini بنصٍّ ونهاية. */
function geminiReply(string $text, string $finish = 'STOP', array $extraParts = []): array
{
    return [
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [...$extraParts, ['text' => $text]]],
            'finishReason' => $finish,
        ]],
        'usageMetadata' => ['promptTokenCount' => 19_200, 'candidatesTokenCount' => 3_000],
    ];
}

function gemini(): GeminiSpeech
{
    return app(GeminiSpeech::class);
}

// ── الطلب ────────────────────────────────────────────────────────

it('sends the clip inline with the reviewed instructions, Arabic, and the glossary', function (): void {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiReply('قال رسول الله ﷺ'))]);

    $text = gemini()->transcribe($this->clip, 'ar', ['صحيح البخاري', 'ابن حجر العسقلاني']);

    expect($text)->toBe('قال رسول الله ﷺ');

    Http::assertSent(function (Request $request): bool {
        $system = (string) data_get($request->data(), 'systemInstruction.parts.0.text');
        $inline = data_get($request->data(), 'contents.0.parts.0.inlineData');

        return str_ends_with($request->url(), '/models/gemini-3.7-flash:generateContent')
            // **المفتاح ترويسةٌ لا في الرابط** — الرابط يبلغ السجلّ.
            && $request->hasHeader('x-goog-api-key', 'test-google-key')
            && ! str_contains($request->url(), 'test-google-key')
            && $inline['mimeType'] === 'audio/mp4'
            && base64_decode($inline['data'], true) === 'audio-bytes'
            // أهمّ القواعد: لا يصحّح ما نطقه المتكلّم محرَّفاً.
            && str_contains($system, 'كما نطقها المتكلّم بالضبط')
            && str_contains($system, 'لغة المقطع: ar.')
            && str_contains($system, 'صحيح البخاري، ابن حجر العسقلاني');
    });
});

it('refuses to call without the Google key', function (): void {
    config()->set('khulasah.model.google.api_key', '');
    Http::fake();

    expect(fn () => gemini()->transcribe($this->clip, 'ar'))->toThrow(TranscriptFailed::class);

    Http::assertNothingSent();
});

// ── الردّ: النصّ الكامل وحده يُقبل ───────────────────────────────

it('returns nothing for a clip it marks as having no speech', function (): void {
    Http::fake(['*' => Http::response(geminiReply(GeminiSpeech::NO_SPEECH))]);

    expect(gemini()->transcribe($this->clip, 'ar'))->toBe('');
});

// الفارغُ بلا علامة الصمت يُسقط عشر دقائق صامتاً — فهو إخفاق.
it('treats an empty reply as a failure, not as silence', function (): void {
    Http::fake(['*' => Http::response(geminiReply(''))]);

    expect(fn () => gemini()->transcribe($this->clip, 'ar'))->toThrow(TranscriptFailed::class);
});

it('refuses a transcript cut off by any finish other than STOP', function (string $finish): void {
    Http::fake(['*' => Http::response(geminiReply('نصفُ الدرس…', $finish))]);

    expect(fn () => gemini()->transcribe($this->clip, 'ar'))
        ->toThrow(TranscriptFailed::class, $finish);
})->with(['MAX_TOKENS', 'RECITATION', 'SAFETY', 'OTHER']);

it('refuses a clip the prompt filter blocked', function (): void {
    Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'OTHER']])]);

    expect(fn () => gemini()->transcribe($this->clip, 'ar'))->toThrow(TranscriptFailed::class, 'OTHER');
});

it('drops thought summaries and a stray markdown fence', function (): void {
    Http::fake(['*' => Http::response(geminiReply(
        "```text\nالحمد لله رب العالمين\n```",
        extraParts: [['text' => 'أفكّر في المقطع…', 'thought' => true]],
    ))]);

    expect(gemini()->transcribe($this->clip, 'ar'))->toBe('الحمد لله رب العالمين');
});

// ── الإعادة ──────────────────────────────────────────────────────

it('retries a busy model and keeps the transcript', function (): void {
    Http::fakeSequence()
        ->push(['error' => ['message' => 'overloaded']], 503)
        ->push(['error' => ['message' => 'rate']], 429)
        ->push(geminiReply('بسم الله'));

    expect(gemini()->transcribe($this->clip, 'ar'))->toBe('بسم الله');

    Http::assertSentCount(3);
});

// 400 لا يصلحه التكرار، وإعادتُه تحرق مالاً بلا فائدة — §6-أ.
it('does not retry a rejected request', function (): void {
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad audio']], 400)]);

    expect(fn () => gemini()->transcribe($this->clip, 'ar'))->toThrow(TranscriptFailed::class, '400');

    Http::assertSentCount(1);
});

// ── الربط ────────────────────────────────────────────────────────

it('is chosen only by WHISPER_PROVIDER=gemini, with its own limit and price', function (): void {
    config()->set('khulasah.transcript.whisper.provider', 'gemini');
    config()->set('khulasah.transcript.gemini.max_bytes', 5 * 1024 * 1024);
    config()->set('khulasah.transcript.gemini.price_per_minute', 0.003);

    $speech = app(SpeechToText::class);

    expect($speech)->toBeInstanceOf(GeminiSpeech::class)
        ->and($speech->maxBytes())->toBe(5 * 1024 * 1024)
        ->and($speech->pricePerMinute())->toBe(0.003);

    config()->set('khulasah.transcript.whisper.provider', 'openai');
    expect(app(SpeechToText::class))->toBeInstanceOf(WhisperApi::class);

    // **والوهميّ هو الافتراضي** — القاعدة السابعة.
    config()->set('khulasah.transcript.whisper.provider', null);
    expect(app(SpeechToText::class))->toBeInstanceOf(FakeSpeechToText::class);
});

// ── المسار كلّه: يوتيوب بلا ترجمة عربية ← صوت ← Gemini ─────────

it('transcribes a YouTube lesson that has no Arabic captions through Gemini', function (): void {
    config()->set('khulasah.transcript.whisper.provider', 'gemini');

    $tenant = Tenant::factory()->create(['max_lecture_minutes' => 180]);
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'source_url' => 'https://www.youtube.com/watch?v=nocaps1',
    ]);

    app()->instance(YtDlp::class, new class extends YtDlp
    {
        public function extractAudio(string $url, string $directory): string
        {
            file_put_contents($directory.'/nocaps1.m4a', 'audio');

            return $directory.'/nocaps1.m4a';
        }
    });

    app()->instance(Ffmpeg::class, new class extends Ffmpeg
    {
        public function durationSeconds(string $path): float
        {
            return 600.0;
        }

        public function toSpeechAudio(string $path, string $directory): string
        {
            return $path;
        }
    });

    Http::fake(['*' => Http::response(geminiReply(implode(' ', array_fill(0, 600, 'كلمة'))))]);

    $result = app(WhisperAudio::class)->fetch(TranscriptRequest::for($lecture));

    expect($result->source)->toBe(TranscriptSource::Whisper)
        ->and($result->wordCount())->toBe(600);

    Http::assertSentCount(1);
});
