<?php

declare(strict_types=1);

use App\Services\Transcript\YtDlp;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

// المواصفة §5-أ-6 البندان ١ و٢: الوكيل السكنيّ، والفحص الأسبوعي بفيديو ثابت.

beforeEach(function (): void {
    Process::preventStrayProcesses();
});

/** @return list<string> وسائط أوّل عملية شُغّلت. */
function ranArguments(): array
{
    $captured = [];

    Process::assertRan(function (PendingProcess $process) use (&$captured): bool {
        $captured = $process->command;

        return true;
    });

    return is_array($captured) ? $captured : explode(' ', (string) $captured);
}

function dumpFor(string $title = 'درس الطهارة'): string
{
    return (string) json_encode(['title' => $title, 'duration' => 1_800]);
}

// ── الوكيل — §5-أ-6 البند ١ ──────────────────────────────────────

// **فارغاً لا يُمرَّر العلَم البتّة.** و`--proxy ''` ليس محايداً: يفهمه
// yt-dlp أمراً بتعطيل الوكيل، فيخالف إعداد البيئة بدل أن يتركه.
it('passes no proxy flag when none is configured', function (): void {
    config()->set('khulasah.transcript.ytdlp_proxy', null);
    Process::fake(['*' => Process::result(output: dumpFor())]);

    app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    expect(ranArguments())->not->toContain('--proxy');
});

it('passes no proxy flag when the setting is blank', function (): void {
    config()->set('khulasah.transcript.ytdlp_proxy', '   ');
    Process::fake(['*' => Process::result(output: dumpFor())]);

    app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    expect(ranArguments())->not->toContain('--proxy');
});

it('sends the proxy through when one is configured', function (): void {
    config()->set('khulasah.transcript.ytdlp_proxy', 'http://proxy.test:8080');
    Process::fake(['*' => Process::result(output: dumpFor())]);

    app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    $arguments = ranArguments();
    $position = array_search('--proxy', $arguments, strict: true);

    expect($position)->not->toBeFalse()
        ->and($arguments[$position + 1])->toBe('http://proxy.test:8080')
        // ويبقى الرابط آخراً بعد `--`، فلا يُقرأ وسيطاً.
        ->and(end($arguments))->toBe('https://www.youtube.com/watch?v=abc');
});

// ── الفحص الأسبوعي — §5-أ-6 البند ٢ ──────────────────────────────

it('passes when yt-dlp still reads the canary video', function (): void {
    config()->set('khulasah.transcript.canary_url', 'https://www.youtube.com/watch?v=canary');
    Process::fake(['*' => Process::result(output: dumpFor())]);

    $this->artisan('khulasah:check-ytdlp')->assertSuccessful();
});

it('fails and logs when yt-dlp cannot read the canary', function (): void {
    Log::spy();

    config()->set('khulasah.transcript.canary_url', 'https://www.youtube.com/watch?v=canary');
    Process::fake(['*' => Process::result(
        output: '',
        errorOutput: "ERROR: Sign in to confirm you're not a bot.",
        exitCode: 1,
    )]);

    $this->artisan('khulasah:check-ytdlp')->assertFailed();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'bot_check'));
});

// **yt-dlp قد ينجح ويعود بكائنٍ شبه فارغ حين يتغيّر يوتيوب**، فيُعدّ
// ناجحاً وهو أعمى. والعنوان دليلُ القراءة الحقيقية.
it('fails when yt-dlp returns success but reads nothing', function (): void {
    Log::spy();

    config()->set('khulasah.transcript.canary_url', 'https://www.youtube.com/watch?v=canary');
    Process::fake(['*' => Process::result(output: (string) json_encode(['id' => 'canary']))]);

    $this->artisan('khulasah:check-ytdlp')->assertFailed();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'قراءةٌ ناقصة'));
});

// **مدّةٌ صفرٌ لفيديو حقيقيّ دليل العطب نفسه كغياب المدّة** — T-48. ردٌّ
// «ناجحٌ ظاهراً» بعنوان صحيح ومدّة صفر كان يمرّ من هذا الحارس بلا تنبيه.
it('fails when yt-dlp returns a title but zero duration', function (): void {
    Log::spy();

    config()->set('khulasah.transcript.canary_url', 'https://www.youtube.com/watch?v=canary');
    Process::fake(['*' => Process::result(
        output: (string) json_encode(['title' => 'قناة سليمة', 'duration' => 0]),
    )]);

    $this->artisan('khulasah:check-ytdlp')->assertFailed();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'قراءةٌ ناقصة'));
});

it('refuses to run with no canary video configured', function (): void {
    config()->set('khulasah.transcript.canary_url', null);
    Process::fake();

    $this->artisan('khulasah:check-ytdlp')->assertFailed();

    Process::assertNothingRan();
});

it('takes a canary url from the command line', function (): void {
    config()->set('khulasah.transcript.canary_url', null);
    Process::fake(['*' => Process::result(output: dumpFor())]);

    $this->artisan('khulasah:check-ytdlp', ['--url' => 'https://www.youtube.com/watch?v=other'])
        ->assertSuccessful();
});

// ── استخراج الصوت — §5-أ-4 ───────────────────────────────────────

// **الصوت وحده لا الفيديو**: التفريغ لا يحتاج الصورة، وتنزيلُها يستهلك
// نطاقاً ووقتاً بلا فائدة.
it('asks yt-dlp for the audio track only', function (): void {
    $directory = sys_get_temp_dir().'/khulasah-audio-test-'.bin2hex(random_bytes(4));
    mkdir($directory, 0700);

    Process::fake(['*' => function (PendingProcess $process) use ($directory) {
        file_put_contents($directory.'/abc.m4a', 'audio');

        return Process::result(output: '');
    }]);

    app(YtDlp::class)->extractAudio('https://www.youtube.com/watch?v=abc', $directory);

    expect(ranArguments())->toContain('-f')
        ->toContain('bestaudio')
        ->toContain('-x')
        ->toContain('m4a')
        ->toContain('--no-playlist');

    array_map('unlink', glob($directory.'/*') ?: []);
    rmdir($directory);
});
