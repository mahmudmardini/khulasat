<?php

declare(strict_types=1);

use App\Exceptions\TranscriptFailed;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Transcript\YtDlp;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/*
 * T-227: فحصُ الرابط يخفق مرّةً من ثلاث، و«أعد الفحص» ينجح. الوكيلُ الدوّار
 * يُخرج كلَّ نداءٍ من عنوانٍ آخر، وبعضُ العناوين محجوب. **ولا يوتيوب هنا**:
 * `preventStrayProcesses` يُسقط الاختبار لو شُغّل yt-dlp حقاً.
 */

beforeEach(function (): void {
    Process::preventStrayProcesses();
    config()->set('khulasah.transcript.ytdlp_proxy', null);
});

function videoDump(): string
{
    return (string) json_encode(['title' => 'عنوان الدرس', 'duration' => 1_800]);
}

function botCheck(): ProcessResult
{
    return Process::result(errorOutput: "ERROR: [youtube] abc: Sign in to confirm you\u{2019}re not a bot.", exitCode: 1);
}

it('tries again after a blocked address, and succeeds', function (): void {
    Process::fake(['*' => Process::sequence()
        ->push(botCheck())
        ->push(Process::result(output: videoDump()))]);

    $preflight = app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    expect($preflight->title)->toBe('عنوان الدرس');
    Process::assertRanTimes(fn (): bool => true, 2);
});

it('tries again after a failed SSL handshake', function (): void {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(errorOutput: 'ERROR: [youtube] abc: Unable to download webpage: [SSL: SSLV3_ALERT_HANDSHAKE_FAILURE] sslv3 alert handshake failure', exitCode: 1))
        ->push(Process::result(output: videoDump()))]);

    expect(app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc')->title)->toBe('عنوان الدرس');
});

it('stops after three attempts', function (): void {
    Process::fake(['*' => botCheck()]);

    expect(fn () => app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc'))
        ->toThrow(TranscriptFailed::class, 'bot_check');

    Process::assertRanTimes(fn (): bool => true, YtDlp::ATTEMPTS);
});

// الفيديو الخاصّ خاصٌّ من كلّ عنوان: إعادتُه تُطيل انتظار المستخدم وحده.
it('does not try again when the video itself is the problem', function (): void {
    Process::fake(['*' => Process::result(errorOutput: 'ERROR: [youtube] abc: Video unavailable', exitCode: 1)]);

    expect(fn () => app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc'))
        ->toThrow(TranscriptFailed::class, 'video_unavailable');

    Process::assertRanTimes(fn (): bool => true, 1);
});

// **المهلةُ للمحاولات كلّها.** الثانية تأخذ ما بقي، لا مهلةً كاملة لنفسها.
it('gives each attempt only what is left of the time', function (): void {
    config()->set('khulasah.transcript.ytdlp_preflight_timeout', 10);
    $timeouts = [];

    Process::fake(['*' => function (PendingProcess $process) use (&$timeouts) {
        $timeouts[] = $process->timeout;

        if (count($timeouts) === 1) {
            usleep(1_100_000);

            return botCheck();
        }

        return Process::result(output: videoDump());
    }]);

    app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc');

    expect($timeouts)->toBe([10, 9]);
});

it('does not start an attempt the time left cannot hold', function (): void {
    config()->set('khulasah.transcript.ytdlp_preflight_timeout', 5);

    Process::fake(['*' => function () {
        usleep(1_100_000);

        return botCheck();
    }]);

    expect(fn () => app(YtDlp::class)->preflight('https://www.youtube.com/watch?v=abc'))
        ->toThrow(TranscriptFailed::class);

    Process::assertRanTimes(fn (): bool => true, 1);
});

// والإعادةُ في `run()`، فتشمل تنزيلَ الترجمة والصوت في الطابور.
it('tries the queued audio download again too', function (): void {
    $directory = sys_get_temp_dir().'/khulasah-retry-test-'.bin2hex(random_bytes(4));
    mkdir($directory, 0700);
    $calls = 0;

    Process::fake(['*' => function () use ($directory, &$calls) {
        if (++$calls === 1) {
            return botCheck();
        }

        file_put_contents($directory.'/abc.m4a', 'audio');

        return Process::result(output: '');
    }]);

    expect(app(YtDlp::class)->extractAudio('https://www.youtube.com/watch?v=abc', $directory))
        ->toEndWith('abc.m4a');

    array_map('unlink', glob($directory.'/*') ?: []);
    rmdir($directory);
});

// ── من الطلب — PreflightController ───────────────────────────────

describe('the preflight request', function (): void {
    beforeEach(function (): void {
        $tenant = Tenant::factory()->create(['max_lecture_minutes' => 90]);
        $this->user = User::factory()->owner()->for_($tenant)->create();
    });

    it('answers after a retry as if nothing happened', function (): void {
        Process::fake(['*' => Process::sequence()
            ->push(botCheck())
            ->push(Process::result(output: videoDump()))]);

        $this->actingAs($this->user)
            ->postJson('/panel/lectures/preflight', ['source_url' => 'https://www.youtube.com/watch?v=abc12345678'])
            ->assertOk()
            ->assertJson(['ok' => true, 'title' => 'عنوان الدرس']);
    });

    it('keeps the general message when every attempt fails', function (): void {
        Process::fake(['*' => botCheck()]);

        $this->actingAs($this->user)
            ->postJson('/panel/lectures/preflight', ['source_url' => 'https://www.youtube.com/watch?v=abc12345678'])
            ->assertStatus(422)
            ->assertExactJson(['ok' => false, 'message' => trans('lectures.create.preflight.unavailable')]);

        Process::assertRanTimes(fn (): bool => true, YtDlp::ATTEMPTS);
    });

    // yt-dlp يكتب عنوانَ الوكيل بنصّه حين يتعذّر الاتّصال به.
    it('logs the code and the output, without the proxy password', function (): void {
        Log::spy();
        config()->set('khulasah.transcript.ytdlp_proxy', 'http://customer-a-session-9:p4ss%2Fw0rd@proxy.test:8080');

        Process::fake(['*' => Process::result(
            errorOutput: "ERROR: [youtube] abc: Unable to download webpage: ('Unable to connect to proxy http://customer-a-session-9:p4ss%2Fw0rd@proxy.test:8080', OSError('Tunnel connection failed: 407 Proxy Authentication Required')) password p4ss/w0rd",
            exitCode: 1,
        )]);

        $this->actingAs($this->user)
            ->postJson('/panel/lectures/preflight', ['source_url' => 'https://www.youtube.com/watch?v=abc12345678'])
            ->assertStatus(422)
            ->assertJson(['message' => trans('lectures.create.preflight.unavailable')]);

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context): bool {
                $logged = (string) json_encode($context, JSON_UNESCAPED_SLASHES);

                return $message === 'preflight_failed'
                    && $context['code'] === 'transcription_failed'
                    && str_contains($context['stderr'], 'Tunnel connection failed')
                    && str_contains($context['stderr'], 'http://***@proxy.test:8080')
                    && ! str_contains($logged, 'p4ss')
                    && ! str_contains($logged, 'customer-a-session-9');
            })
            ->once();
    });
});
