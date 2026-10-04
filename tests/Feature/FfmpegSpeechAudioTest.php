<?php

declare(strict_types=1);

use App\Exceptions\TranscriptFailed;
use App\Services\Transcript\Ffmpeg;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

// الصوت الموحَّد قبل التقطيع — {@see Ffmpeg::toSpeechAudio()}. ولا عملية
// حقيقية: CLAUDE.md §2 القاعدة السابعة.

it('turns any upload into one mono 16 kHz speech track without video', function (): void {
    Process::fake();

    $output = app(Ffmpeg::class)->toSpeechAudio('/uploads/درس; rm -rf.mp4', '/tmp/work');

    expect($output)->toBe('/tmp/work/speech.m4a');

    Process::assertRan(function (PendingProcess $process): bool {
        $command = $process->command;

        return is_array($command)
            // الاسم وسيطٌ واحد لا يمرّ على صدفة — حقنُ الأوامر لا يقع.
            && in_array('/uploads/درس; rm -rf.mp4', $command, true)
            && array_slice($command, (int) array_search('-map', $command, true), 2) === ['-map', '0:a:0']
            && array_slice($command, (int) array_search('-ac', $command, true), 2) === ['-ac', '1']
            && array_slice($command, (int) array_search('-ar', $command, true), 2) === ['-ar', '16000']
            && end($command) === '/tmp/work/speech.m4a';
    });
});

// فيديو بلا صوت: ffmpeg يُخفق، فيُخفق التفريغ — لا ملفّ فارغ يُرسَل.
it('fails on a file with no audio track', function (): void {
    Process::fake(['*' => Process::result(errorOutput: "Stream map '0:a:0' matches no streams.", exitCode: 234)]);

    expect(fn () => app(Ffmpeg::class)->toSpeechAudio('/uploads/silent.mp4', '/tmp/work'))
        ->toThrow(TranscriptFailed::class);
});

/**
 * ffmpeg مُزيَّف يجيب عن المدّة والكشفين بما يُعطى، ويعدّ كلّ كشفٍ ليّن.
 *
 * @param  array{strict: string, soft: string}  $reports
 */
function fakeSilenceReports(float $duration, array $reports): object
{
    $calls = new class
    {
        public int $soft = 0;
    };

    Process::fake(function (PendingProcess $process) use ($duration, $reports, $calls) {
        $command = implode(' ', (array) $process->command);

        if (str_contains($command, 'format=duration')) {
            return Process::result(output: (string) $duration);
        }

        if (str_contains($command, 'silencedetect=noise=-20dB')) {
            $calls->soft++;

            return Process::result(errorOutput: $reports['soft']);
        }

        if (str_contains($command, 'silencedetect')) {
            return Process::result(errorOutput: $reports['strict']);
        }

        return Process::result();
    });

    return $calls;
}

// السكتاتُ الصريحة كفت: لا يُفكّ الصوتُ مرّةً ثانية بلا داعٍ.
it('does not look for soft pauses when clear silences already cover every cut', function (): void {
    $calls = fakeSilenceReports(1_000.0, [
        'strict' => "silence_start: 598.0\nsilence_end: 602.0 | silence_duration: 4.0\n",
        'soft' => '',
    ]);

    $chunks = app(Ffmpeg::class)->splitAtSilence('/tmp/speech.m4a', '/tmp/work', 600);

    expect($chunks)->toHaveCount(2)
        ->and($calls->soft)->toBe(0);
});

// قاعةٌ لا تهبط إلى -30dB: الكشفُ الصارم لا يجد شيئاً، فيُطلب الليّن ويُقطع عنده.
it('looks for soft pauses when no clear silence is within reach of a cut', function (): void {
    $calls = fakeSilenceReports(1_000.0, [
        'strict' => '',
        'soft' => "silence_start: 610.0\nsilence_end: 610.4 | silence_duration: 0.4\n",
    ]);

    app(Ffmpeg::class)->splitAtSilence('/tmp/speech.m4a', '/tmp/work', 600);

    expect($calls->soft)->toBe(1);

    Process::assertRan(fn (PendingProcess $process): bool => in_array('610.2', (array) $process->command, true));
});
