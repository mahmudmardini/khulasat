<?php

declare(strict_types=1);

use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Services\Transcript\ManualUpload;
use App\Services\Transcript\TranscriptResolver;
use App\Services\Transcript\WhisperAudio;
use App\Services\Transcript\YoutubeCaptions;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;

// المواصفة §5-أ-2: «يُجرَّب بالترتيب، وأوّل ناجحٍ يُعتمد». و§5-أ-6 البند ١:
// الحجب يُحوَّل تلقائياً إلى المسار اليدوي.

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

function ask(): TranscriptRequest
{
    return TranscriptRequest::for(Lecture::factory()->create(['tenant_id' => test()->tenant->id]));
}

/** مصدرٌ مصنوع: يُدعَم أو لا، وينجح أو يُخفق برمزٍ معلوم. */
function source(string $name, bool $supports = true, ?TranscriptErrorCode $fails = null): TranscriptProvider
{
    return new class($name, $supports, $fails) implements TranscriptProvider
    {
        public int $fetched = 0;

        public function __construct(
            private string $label,
            private bool $supports,
            private ?TranscriptErrorCode $fails,
        ) {}

        public function name(): string
        {
            return $this->label;
        }

        public function supports(TranscriptRequest $request): bool
        {
            return $this->supports;
        }

        public function fetch(TranscriptRequest $request): TranscriptResult
        {
            $this->fetched++;

            if ($this->fails !== null) {
                throw TranscriptFailed::because($this->fails, $this->label);
            }

            return new TranscriptResult("نصّ من {$this->label}", TranscriptSource::Captions);
        }
    };
}

it('takes the first source that succeeds', function (): void {
    $first = source('first');
    $second = source('second');

    $result = (new TranscriptResolver([$first, $second]))->resolve(ask());

    expect($result->text)->toBe('نصّ من first')
        ->and($second->fetched)->toBe(0);
});

it('skips a source that does not support the request, without calling it', function (): void {
    $skipped = source('skipped', supports: false);
    $used = source('used');

    $result = (new TranscriptResolver([$skipped, $used]))->resolve(ask());

    expect($skipped->fetched)->toBe(0)
        ->and($result->text)->toBe('نصّ من used');
});

// **جوهر §5-أ-2.** غيابُ ترجمة عربية ليس عطلاً يُبلَّغ به المستخدم، بل هو
// بعينه سببُ وجود المسار الثالث. ولو عُومل وقوفاً لما عمل الجدول أصلاً.
it('moves on when a source reports no Arabic captions', function (): void {
    $captions = source('captions', fails: TranscriptErrorCode::NoArabicSource);
    $audio = source('audio');

    $result = (new TranscriptResolver([$captions, $audio]))->resolve(ask());

    expect($captions->fetched)->toBe(1)
        ->and($result->text)->toBe('نصّ من audio');
});

// ── ما يقف ولا ينتقل ─────────────────────────────────────────────

it('stops at once on a failure no later source can fix', function (TranscriptErrorCode $code): void {
    $first = source('first', fails: $code);
    $second = source('second');

    expect(fn () => (new TranscriptResolver([$first, $second]))->resolve(ask()))
        ->toThrow(TranscriptFailed::class);

    // ولم يُجرَّب ما بعده: لا مصدر يُصلح رابطاً مرفوضاً ولا مدّةً زائدة.
    expect($second->fetched)->toBe(0);
})->with([
    TranscriptErrorCode::HostNotAllowed,
    TranscriptErrorCode::DurationExceeded,
]);

// §5-أ-7: النصّ القصير **«يُرفع لمدير المحتوى»** — إلى إنسان لا إلى مصدرٍ
// أغلى. والانتقال منه إلى التفريغ الصوتي يصرف من دقائق الجهة على نصٍّ
// عُلم نقصُه.
it('raises a short transcript to a human instead of paying for audio', function (): void {
    $captions = source('captions', fails: TranscriptErrorCode::TranscriptTooShort);
    $audio = source('audio');

    try {
        (new TranscriptResolver([$captions, $audio]))->resolve(ask());
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::TranscriptTooShort)
            ->and($audio->fetched)->toBe(0);

        return;
    }

    $this->fail('كان يجب أن يقف عند النصّ القصير.');
});

// ── الحجب — §5-أ-6 البند ١ ───────────────────────────────────────

// المساران الأوّلان يستعملان yt-dlp، فيُخفقان معاً بالرمز نفسه. والمُبلَّغ
// به إخفاقُ آخر مصدرٍ جُرّب لا أوّلِه، لأنّ الأوّل كثيراً ما يكون انتقالاً.
it('reports the deepest failure, not the first hand-off', function (): void {
    $captions = source('captions', fails: TranscriptErrorCode::NoArabicSource);
    $audio = source('audio', fails: TranscriptErrorCode::BotCheck);

    try {
        (new TranscriptResolver([$captions, $audio]))->resolve(ask());
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode)->toBe(TranscriptErrorCode::BotCheck);

        return;
    }

    $this->fail('كان يجب أن يُبلَّغ بالحجب.');
});

// الرمز يحمل علامة التحويل، فيعرف المُستدعي أن يعرض نموذج اللصق.
it('marks a block as something the manual path recovers', function (): void {
    $captions = source('captions', fails: TranscriptErrorCode::BotCheck);
    $audio = source('audio', fails: TranscriptErrorCode::BotCheck);

    try {
        (new TranscriptResolver([$captions, $audio]))->resolve(ask());
    } catch (TranscriptFailed $failure) {
        expect($failure->errorCode->fallsBackToManualPath())->toBeTrue()
            ->and($failure->userMessage())->toContain('الإدخال اليدوي')
            ->and($failure->userMessage())->toContain('ولم يضع شيء ممّا أدخلته');

        return;
    }

    $this->fail('كان يجب أن يُبلَّغ بالحجب.');
});

// وحين يكون النصّ الملصوق حاضراً، الحجب لا يمنع شيئاً: المسار اليدوي
// يلتقط الطلب. «وهو ما يُنقذ الجهة حين يُخفق كلّ ما سبق» — §5-أ-5.
it('still finishes through the manual path when the platform blocks us', function (): void {
    $blocked = source('captions', fails: TranscriptErrorCode::BotCheck);
    $manual = source('manual');

    $result = (new TranscriptResolver([$blocked, $manual]))->resolve(ask());

    expect($result->text)->toBe('نصّ من manual');
});

it('reports a clear failure when no source can even be tried', function (): void {
    $resolver = new TranscriptResolver([source('a', supports: false)]);

    expect(fn () => $resolver->resolve(ask()))->toThrow(TranscriptFailed::class);
});

// ── الترتيب المسجَّل في الحاوية — §5-أ-2 و§5-أ-5 ─────────────────

// اليدويّ آخرها دائماً: لو تقدّم لالتُقط كلّ درسٍ عنده نصٌّ ملصوق قبل أن
// تُجرَّب ترجمةُ المنصّة، وهي أدقّ وأرخص.
it('registers the sources in the order of the spec table', function (): void {
    expect(app(TranscriptResolver::class)->order())
        ->toBe(['youtube_captions', 'whisper_audio', 'manual_upload']);
});

it('wires the real providers behind the contract', function (): void {
    expect(app(YoutubeCaptions::class))->toBeInstanceOf(TranscriptProvider::class)
        ->and(app(WhisperAudio::class))->toBeInstanceOf(TranscriptProvider::class)
        ->and(app(ManualUpload::class))->toBeInstanceOf(TranscriptProvider::class);
});
