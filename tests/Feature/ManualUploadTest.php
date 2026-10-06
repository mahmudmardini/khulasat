<?php

declare(strict_types=1);

use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Services\Transcript\ManualUpload;
use App\Support\Transcript\TranscriptRequest;

// المواصفة §5-أ-5: «يبقى متاحاً دائماً، وليس حالة طوارئ فقط… وهذا المسار
// هو ما يُنقذ الجهة حين يُخفق كلّ ما سبق».

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->manual = app(ManualUpload::class);
    $this->scratch = sys_get_temp_dir().'/khulasah-manual-'.bin2hex(random_bytes(6));
    mkdir($this->scratch, 0700);
});

afterEach(function (): void {
    foreach (glob($this->scratch.'/*') ?: [] as $file) {
        @unlink($file);
    }

    @rmdir($this->scratch);
});

function request_(): TranscriptRequest
{
    return TranscriptRequest::for(Lecture::factory()->create(['tenant_id' => test()->tenant->id]));
}

/** نصّ عربي يتجاوز الحدّ الأدنى (٥٠٠ كلمة). */
function longProse(int $words = 600): string
{
    return implode(' ', array_map(
        static fn (int $i): string => "كلمة{$i}",
        range(1, $words),
    ));
}

function writeScratch(string $name, string $contents): string
{
    $path = test()->scratch.'/'.$name;
    file_put_contents($path, $contents);

    return $path;
}

// ── اللصق — §5-أ-5 ───────────────────────────────────────────────

it('accepts pasted prose', function (): void {
    $result = $this->manual->fetch(request_()->withPastedText(longProse()));

    expect($result->source)->toBe(TranscriptSource::Manual)
        ->and($result->wordCount())->toBe(600);
});

// المسار اليدوي **لا يُحتسب من دقائق التفريغ** — §11 وTranscriptSource.
it('costs no transcription minutes', function (): void {
    $result = $this->manual->fetch(request_()->withPastedText(longProse()));

    expect($result->source->consumesTranscriptionMinutes())->toBeFalse();
});

it('keeps the paragraphs the user typed', function (): void {
    $text = longProse(300)."\n\n".longProse(300);

    expect($this->manual->fetch(request_()->withPastedText($text))->text)
        ->toContain("\n\n");
});

it('keeps diacritics in pasted text', function (): void {
    $text = 'مَنْ عَمِلَ صَالِحاً '.longProse();

    expect($this->manual->fetch(request_()->withPastedText($text))->text)
        ->toStartWith('مَنْ عَمِلَ صَالِحاً');
});

// ── ملفّات الترجمة تمرّ على محوّل T-08 نفسه — §5-أ-5 ──────────────

// **أهمّ اختبار هنا.** «ويمرّ على تحويل §5-أ-3 نفسه». ولو كُتب محوّلٌ ثانٍ
// لتباعدت النسختان، فخرج النصّ من مسارٍ نظيفاً ومن آخر مضاعفاً.
it('removes caption overlap from an uploaded srt, exactly as the captions path does', function (): void {
    $blocks = [];
    $tail = '';

    for ($i = 0; $i < 200; $i++) {
        $fresh = "الكلمة {$i} والتي بعدها وثالثة معها";
        $start = sprintf('00:%02d:%02d,000', intdiv($i, 60), $i % 60);
        $end = sprintf('00:%02d:%02d,000', intdiv($i + 1, 60), ($i + 1) % 60);
        $blocks[] = ($i + 1)."\n{$start} --> {$end}\n".trim($tail.' '.$fresh);
        $tail = 'وثالثة معها';
    }

    $path = writeScratch('lesson.srt', implode("\n\n", $blocks));

    $result = $this->manual->fetch(request_()->withUploadedFile($path));

    expect($result->source)->toBe(TranscriptSource::Manual)
        ->and($result->text)->not->toContain('وثالثة معها وثالثة معها')
        // ولا توقيتات ولا أرقام كتل في المخرَج.
        ->and($result->text)->not->toContain('-->');
});

it('accepts a vtt file', function (): void {
    $vtt = "WEBVTT\n\n00:00:01.000 --> 00:00:03.000\n".longProse();

    $result = $this->manual->fetch(request_()->withUploadedFile(writeScratch('a.vtt', $vtt)));

    expect($result->wordCount())->toBeGreaterThanOrEqual(TranscriptErrorCode::MINIMUM_WORDS);
});

// نصّ ملصوق بلا توقيتات لا يمرّ على المحوّل: كلّ كتلةٍ بلا سطر توقيت تُترك،
// فيعود المحوّل بفراغ. فيُميَّز أوّلاً.
it('does not run plain text through the subtitle converter', function (): void {
    $result = $this->manual->fetch(request_()->withUploadedFile(writeScratch('a.txt', longProse())));

    expect($result->wordCount())->toBe(600);
});

// ── فحص النوع بالمحتوى — §5-أ-4-ب ────────────────────────────────

it('refuses a binary file wearing a text extension', function (): void {
    $path = writeScratch('fake.txt', "\x00\x01\x02\x03binary");

    expect(fn () => $this->manual->fetch(request_()->withUploadedFile($path)))
        ->toThrow(TranscriptFailed::class);
});

it('refuses an extension that is not a transcript format', function (): void {
    $path = writeScratch('lesson.pdf', longProse());

    expect($this->manual->supports(request_()->withUploadedFile($path)))->toBeFalse();
});

it('refuses a text file past the size limit', function (): void {
    config()->set('khulasah.transcript.upload.text_max_bytes', 64);

    $path = writeScratch('big.txt', longProse());

    expect(fn () => $this->manual->fetch(request_()->withUploadedFile($path)))
        ->toThrow(TranscriptFailed::class);
});

// ── الحدّ الأدنى يسري هنا أيضاً — §5-أ-7 ─────────────────────────

// لصقُ فقرةٍ سهوًا بدل درسٍ كامل حالةٌ متوقّعة، وكشفُها الآن أرخص.
it('stops a pasted fragment shorter than five hundred words', function (): void {
    try {
        $this->manual->fetch(request_()->withPastedText('بسم الله الرحمن الرحيم'));
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::TranscriptTooShort)
            ->and($failure->userMessage())->toContain('دون ٥٠٠ كلمة')
            // ★ T-221: والوحدةُ احتُسبت عند إنشاء المهمّة، فلا يُقال إنّ الحصّة لم تُمسّ.
            ->and($failure->userMessage())->not->toContain('حصّتكم');

        return;
    }

    $this->fail('كان يجب أن يُرفض النصّ القصير.');
});

// ── متى يُدعَم — §5-أ-5 ──────────────────────────────────────────

it('is available whenever the user supplies anything', function (): void {
    expect($this->manual->supports(request_()->withPastedText('نصّ')))->toBeTrue()
        ->and($this->manual->supports(request_()->withUploadedFile(writeScratch('a.srt', 'x'))))->toBeTrue()
        // وبلا مدخل لا يُدعَم: هو آخر الصفّ، لا مُلتقِطٌ لكلّ شيء.
        ->and($this->manual->supports(request_()))->toBeFalse()
        ->and($this->manual->name())->toBe('manual_upload');
});
