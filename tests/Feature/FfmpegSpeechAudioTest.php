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
