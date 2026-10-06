<?php

declare(strict_types=1);

use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\PlaylistUrlGiven;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Services\Transcript\YoutubeCaptions;
use App\Support\Transcript\TranscriptRequest;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

// المواصفة §5-أ، المساران الأوّل والثاني. ولا نداء شبكة حقيقياً هنا:
// yt-dlp عمليةٌ مُزيَّفة، فالاختبار يقيس منطقنا لا صحّة يوتيوب اليوم.

beforeEach(function (): void {
    Process::preventStrayProcesses();

    $this->tenant = Tenant::factory()->create(['max_lecture_minutes' => 180]);
    $this->provider = app(YoutubeCaptions::class);
});

function lectureAt(string $url, ?Tenant $tenant = null): TranscriptRequest
{
    return TranscriptRequest::for(Lecture::factory()->create([
        'tenant_id' => ($tenant ?? test()->tenant)->id,
        'source_url' => $url,
    ]));
}

/** @param  array<string, mixed>  $overrides */
function fakeDump(array $overrides = []): string
{
    return (string) json_encode([
        'title' => 'شرح كتاب الطهارة',
        'duration' => 3_600,
        'channel' => 'قناة الدروس',
        'upload_date' => '20260901',
        'language' => 'ar',
        'subtitles' => [],
        'automatic_captions' => [],
        ...$overrides,
    ]);
}

/** ترجمةٌ عربية أصلية: عنوانها بلا معامل الترجمة `tlang=`. */
function arabicTrackJson(): array
{
    return ['ar' => [['ext' => 'vtt', 'url' => 'https://www.youtube.com/api/timedtext?v=abc&lang=ar']]];
}

/**
 * ملفّ SRT متداخل يبلغ الحدّ الأدنى للطول، ليُختبر ما بعد التحويل.
 * كلّ كتلة تُعيد آخرَ ما قبلها — وهي نافذة يوتيوب المتحرّكة.
 */
function longInterleavedSrt(int $cues = 200): string
{
    $blocks = [];
    $previousTail = '';

    for ($i = 0; $i < $cues; $i++) {
        $fresh = "الكلمة {$i} والتي بعدها وثالثة معها";
        $start = sprintf('00:%02d:%02d,000', intdiv($i, 60), $i % 60);
        $end = sprintf('00:%02d:%02d,000', intdiv($i + 1, 60), ($i + 1) % 60);
        $blocks[] = ($i + 1)."\n{$start} --> {$end}\n".trim($previousTail.' '.$fresh);
        $previousTail = 'وثالثة معها';
    }

    return implode("\n\n", $blocks);
}

/** مُطابِقٌ لـ Process::assertRan — يقرأ الوسائط التي شُغّلت بها العملية. */
function ranWith(string $argument): Closure
{
    return function (PendingProcess $process) use ($argument): bool {
        $command = $process->command;

        return str_contains(is_array($command) ? implode(' ', $command) : (string) $command, $argument);
    };
}

/**
 * تُزيَّف عمليتان بالترتيب: الفحص المسبق، ثم تنزيل الترجمة الذي يكتب
 * الملفّ في مجلّد العملية المؤقّت.
 */
function fakeYtDlp(string $dump, ?string $srt = null): void
{
    Process::fake([
        '*--dump-json*' => Process::result(output: $dump),
        '*--sub-langs*' => function (PendingProcess $process) use ($srt) {
            if ($srt !== null) {
                // yt-dlp يكتب الملفّ في مجلّد العملية، فتُحاكي التزييفةُ ذلك.
                file_put_contents($process->path.'/abc123.ar.srt', $srt);
            }

            return Process::result(output: '');
        },
    ]);
}

// ── المسار السعيد ────────────────────────────────────────────────

it('reads a lecture from its Arabic captions', function (): void {
    fakeYtDlp(fakeDump(['subtitles' => arabicTrackJson()]), longInterleavedSrt());

    $result = $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));

    expect($result->source)->toBe(TranscriptSource::Captions)
        ->and($result->track->isManual())->toBeTrue()
        ->and($result->wordCount())->toBeGreaterThanOrEqual(TranscriptErrorCode::MINIMUM_WORDS)
        // والتداخل أُزيل: النافذة المتحرّكة لم تُضاعف النصّ.
        ->and($result->text)->not->toContain('وثالثة معها وثالثة معها');
});

// المسار الثاني: آليّة عند غياب اليدويّة — §5-أ-2.
it('falls to the automatic caption when there is no manual one', function (): void {
    fakeYtDlp(fakeDump(['automatic_captions' => arabicTrackJson()]), longInterleavedSrt());

    $result = $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));

    expect($result->track->isAutomatic)->toBeTrue()
        ->and($result->source)->toBe(TranscriptSource::Captions);
});

// ── الفحص المسبق: يُرفض قبل صرف أيّ مورد — §5-أ-1 و§11 ───────────

// **أهمّ اختبار في هذا الملفّ.** المواصفة §5-أ-1: «يُرفض هنا قبل تنزيل
// بايت واحد». والدليل أنّ عملية التنزيل لم تُشغَّل أصلاً.
it('rejects an over-long lecture before downloading a single byte', function (): void {
    fakeYtDlp(fakeDump(['duration' => 14_400, 'subtitles' => arabicTrackJson()]), longInterleavedSrt());

    expect(fn () => $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123')))
        ->toThrow(TranscriptFailed::class);

    Process::assertRan(ranWith('--dump-json'));

    // ولم يُنزَّل شيء.
    Process::assertNotRan(ranWith('--sub-langs'));
});

it('carries the duration_exceeded code when the lecture is too long', function (): void {
    fakeYtDlp(fakeDump(['duration' => 14_400]));

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::DurationExceeded)
            ->and($failure->userMessage())->toContain('تتجاوز الحدّ المسموح');

        return;
    }

    $this->fail('كان يجب أن تُرفض المدّة.');
});

it('measures the limit against the tenant of the lecture', function (): void {
    $strict = Tenant::factory()->create(['max_lecture_minutes' => 30]);

    fakeYtDlp(fakeDump(['duration' => 3_600, 'subtitles' => arabicTrackJson()]), longInterleavedSrt());

    expect(fn () => $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123', $strict)))
        ->toThrow(TranscriptFailed::class);
});

// المضيف يُرفض قبل أيّ نداء — §12.
it('rejects a host outside the allow list without calling yt-dlp', function (): void {
    Process::fake();

    expect(fn () => $this->provider->fetch(lectureAt('https://vimeo.com/123456')))
        ->toThrow(TranscriptFailed::class);

    Process::assertNothingRan();
});

// §5-أ-1 الفحص الثالث: يُطلب رابط الفيديو المفرد.
it('asks for a single video when given a playlist', function (): void {
    Process::fake();

    expect(fn () => $this->provider->fetch(lectureAt('https://www.youtube.com/playlist?list=PL1')))
        ->toThrow(PlaylistUrlGiven::class);

    Process::assertNothingRan();
});

it('refuses a playlist that only the dump reveals', function (): void {
    fakeYtDlp(fakeDump(['_type' => 'playlist']));

    expect(fn () => $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123')))
        ->toThrow(PlaylistUrlGiven::class);
});

// ── أولوية المصادر ورفض المترجَم — §5-أ-2 ────────────────────────

it('reports no Arabic source when the video has none', function (): void {
    fakeYtDlp(fakeDump(['subtitles' => ['en' => [['ext' => 'vtt', 'url' => 'https://x.test/a.vtt']]]]));

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::NoArabicSource)
            // ورسالتها تُخبر أنّ التفريغ الصوتي هو التالي، لا أنّ العملية فشلت.
            ->and($failure->userMessage())->toContain('سنفرّغ صوته');

        return;
    }

    $this->fail('كان يجب أن يُبلَّغ بغياب المصدر العربي.');
});

// **حاجزٌ جوهريّ.** يوتيوب يعرض ترجمةً عربيةً آليّةً لدرسٍ إنجليزي، وهي
// ترجمةُ نصّ التعرّف الآلي. ولو أُخذت خرجت ألفاظ الأحاديث مترجمةً عن ترجمة.
it('never takes a machine-translated Arabic caption', function (): void {
    fakeYtDlp(fakeDump([
        'language' => 'en',
        'automatic_captions' => [
            'en' => [['ext' => 'vtt', 'url' => 'https://www.youtube.com/api/timedtext?v=abc&lang=en']],
            'ar' => [['ext' => 'vtt', 'url' => 'https://www.youtube.com/api/timedtext?v=abc&lang=en&tlang=ar']],
        ],
    ]));

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::NoArabicSource);

        return;
    }

    $this->fail('كان يجب أن تُرفض الترجمة المترجَمة آلياً.');
});

// ── الأعطال الخارجية — §5-أ-6 ────────────────────────────────────

it('reads a bot check and offers the manual path', function (): void {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: "ERROR: [youtube] abc: Sign in to confirm you're not a bot.",
            exitCode: 1,
        ),
    ]);

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::BotCheck)
            ->and($failure->errorCode->fallsBackToManualPath())->toBeTrue()
            ->and($failure->userMessage())->toContain('الإدخال اليدوي')
            // ولا يظهر الرمز للمستخدم — §5-أ-7.
            ->and($failure->userMessage())->not->toContain('bot_check');

        return;
    }

    $this->fail('كان يجب أن يُقرأ فحص الروبوت.');
});

it('tells each external failure apart', function (string $stderr, TranscriptErrorCode $expected): void {
    Process::fake(['*' => Process::result(output: '', errorOutput: $stderr, exitCode: 1)]);

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe($expected);

        return;
    }

    $this->fail('كان يجب أن يُرفع عطل.');
})->with([
    ['ERROR: [youtube] abc: Private video. Sign in if you have been granted access', TranscriptErrorCode::VideoPrivate],
    ['ERROR: [youtube] abc: The uploader has not made this video available in your country', TranscriptErrorCode::GeoBlocked],
    ['ERROR: [youtube] abc: Video unavailable', TranscriptErrorCode::VideoUnavailable],
]);

it('fails cleanly when the dump is not readable json', function (): void {
    Process::fake(['*--dump-json*' => Process::result(output: 'not json at all')]);

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::TranscriptionFailed);

        return;
    }

    $this->fail('كان يجب أن يُرفع عطل.');
});

// المسار كان معروضاً في الفحص المسبق ثم لم يصل ملفّه: لا يُخمَّن نصّ.
it('does not invent a transcript when the caption file never arrives', function (): void {
    fakeYtDlp(fakeDump(['subtitles' => arabicTrackJson()]), srt: null);

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::NoArabicSource);

        return;
    }

    $this->fail('كان يجب أن يُرفع عطل.');
});

// ── الطول الأدنى: الحاجز قبل النماذج — §5-أ-7 ────────────────────

// المواصفة: «يُرفع لمدير المحتوى **قبل صرف أيّ توكن على النماذج**».
it('stops a transcript shorter than five hundred words', function (): void {
    fakeYtDlp(fakeDump(['subtitles' => arabicTrackJson()]), longInterleavedSrt(cues: 5));

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::TranscriptTooShort)
            ->and($failure->userMessage())->toContain('دون ٥٠٠ كلمة')
            // ★ T-222: والوحدةُ تعود حين تقف المهمّة هنا، فالجملة صادقة.
            ->and($failure->userMessage())->toContain('لم نصرف من حصّتكم شيئاً');

        return;
    }

    $this->fail('كان يجب أن يُرفض النصّ القصير.');
});

// ── العقد: ما يُدعَم يُجرَّب، وما لا يُدعَم يُترك للتالي — §5-أ-5 ──

it('is a transcript provider', function (): void {
    expect($this->provider)->toBeInstanceOf(TranscriptProvider::class)
        ->and($this->provider->name())->toBe('youtube_captions');
});

it('says whether it can be tried at all, without touching the network', function (): void {
    Process::fake();

    expect($this->provider->supports(lectureAt('https://www.youtube.com/watch?v=abc123')))->toBeTrue()
        ->and($this->provider->supports(lectureAt('https://youtu.be/abc123')))->toBeTrue()
        // ما لا يُدعَم يُترك للتالي في الصفّ بلا إخفاق ولا رمز خطأ.
        ->and($this->provider->supports(lectureAt('https://vimeo.com/1')))->toBeFalse()
        ->and($this->provider->supports(lectureAt('https://www.youtube.com/playlist?list=PL1')))->toBeFalse()
        ->and($this->provider->supports(lectureAt('')))->toBeFalse();

    Process::assertNothingRan();
});

// ── النظافة: المجلّد المؤقّت يُنظَّف في كلّ حال — §5-أ-6 البند ٤ ──

it('leaves no temporary directory behind, and not only when it succeeds', function (): void {
    $before = glob(sys_get_temp_dir().'/khulasah-transcript-*') ?: [];

    fakeYtDlp(fakeDump(['subtitles' => arabicTrackJson()]), longInterleavedSrt());
    $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc123'));

    // ثم في الإخفاق، وهي الحالة التي تتكرّر.
    Process::fake([
        '*--dump-json*' => Process::result(output: fakeDump(['subtitles' => arabicTrackJson()])),
        '*--sub-langs*' => Process::result(output: '', errorOutput: 'ERROR: boom', exitCode: 1),
    ]);

    try {
        $this->provider->fetch(lectureAt('https://www.youtube.com/watch?v=abc124'));
    } catch (TranscriptFailed) {
        // متوقّع.
    }

    expect(glob(sys_get_temp_dir().'/khulasah-transcript-*') ?: [])->toBe($before);
});
