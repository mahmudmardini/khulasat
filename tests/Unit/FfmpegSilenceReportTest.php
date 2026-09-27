<?php

declare(strict_types=1);

use App\Services\Transcript\Ffmpeg;

// المواصفة §5-أ-4. والتقرير هنا بنصّه كما يُخرجه ffmpeg، لا مصوغاً.

it('reads the silence pairs out of a real ffmpeg report', function (): void {
    $report = <<<'REPORT'
        ffmpeg version 6.1.1 Copyright (c) 2000-2023 the FFmpeg developers
        Input #0, mov,mp4,m4a, from 'lesson.m4a':
          Duration: 00:41:12.34, start: 0.000000, bitrate: 128 kb/s
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_start: 12.345
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_end: 13.001 | silence_duration: 0.656
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_start: 597.882
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_end: 599.114 | silence_duration: 1.232
        size=N/A time=00:41:12.34 bitrate=N/A speed= 412x
        REPORT;

    expect(Ffmpeg::parseSilences($report))->toBe([
        ['start' => 12.345, 'end' => 13.001],
        ['start' => 597.882, 'end' => 599.114],
    ]);
});

// آخرُ سكتةٍ قد تبقى مفتوحةً إلى نهاية الملفّ بلا `silence_end`. وسكتةٌ لا
// نعرف نهايتها لا يُحسب لها وسط، فتُترك ولا تُخمَّن لها نهاية.
it('drops a silence that never closed', function (): void {
    $report = <<<'REPORT'
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_start: 10.0
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_end: 11.0 | silence_duration: 1.0
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_start: 2400.0
        REPORT;

    expect(Ffmpeg::parseSilences($report))->toBe([
        ['start' => 10.0, 'end' => 11.0],
    ]);
});

it('returns nothing for a report with no silence at all', function (): void {
    expect(Ffmpeg::parseSilences('ffmpeg version 6.1.1'))->toBe([])
        ->and(Ffmpeg::parseSilences(''))->toBe([]);
});

it('reads a silence that starts at the very beginning', function (): void {
    $report = "silence_start: 0\nsilence_end: 2.5 | silence_duration: 2.5";

    expect(Ffmpeg::parseSilences($report))->toBe([['start' => 0.0, 'end' => 2.5]]);
});

// مخرَجٌ ملتقَط من ffmpeg 8.1.2 داخل صورة المشروع، على ملفّ صُنع نغمةً
// ٣ ثوانٍ ثمّ صمتاً ٢ ثمّ نغمة ٣. **العيّنة منقولة لا مصوغة**، فالقارئ
// يُقاس على ما سيصله فعلاً — واللاحقة `Parsed_` جزءٌ من الشكل الحقيقي.
it('reads the exact output ffmpeg produced for a known recording', function (): void {
    $report = <<<'REPORT'
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_start: 2.999909
        [Parsed_silencedetect_0 @ 0xffffba345e60] silence_end: 5.000068 | silence_duration: 2.000159
        REPORT;

    $silences = Ffmpeg::parseSilences($report);

    expect($silences)->toBe([['start' => 2.999909, 'end' => 5.000068]]);

    // ووسطُ السكتة ≈ ٤ ثوانٍ، وهو منتصف الصمت الحقيقي بين النغمتين.
    expect(($silences[0]['start'] + $silences[0]['end']) / 2)->toBeGreaterThan(3.9)
        ->toBeLessThan(4.1);
});
