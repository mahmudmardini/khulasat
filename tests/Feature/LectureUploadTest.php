<?php

declare(strict_types=1);

use App\Actions\Stages\ResolveTranscript;
use App\Actions\Summary\ResumeFailedJob;
use App\Actions\Summary\TransitionJob;
use App\Contracts\SpeechToText;
use App\Domain\Summary\JobState;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\MediaUpload;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Transcript\Ffmpeg;
use App\Services\Transcript\Speech\FakeSpeechToText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * رفع ملفّ صوت أو فيديو من الجهاز — المواصفة §5-أ-4-ب.
 *
 * «حالة حقيقية متكرّرة: درس مسجَّل بالجوال لم يُرفع إلى يوتيوب.» ويُفحص
 * الملفّ عند الرفع لا في الطابور: الحصّة تُخصم عند الإنشاء.
 */

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    Process::preventStrayProcesses();

    $this->tenant = Tenant::factory()->create(['max_lecture_minutes' => 90]);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

/** ترويسة WAV صحيحة، فيقرأها فاحص المحتوى صوتاً. */
function wavBytes(): string
{
    return "RIFF\x24\x00\x00\x00WAVEfmt \x10\x00\x00\x00\x01\x00\x01\x00\x40\x1f\x00\x00\x80>\x00\x00\x02\x00\x10\x00data\x00\x00\x00\x00";
}

/** ffmpeg مُزيَّف بمدّةٍ معلومة، وبصوتٍ أو بلا صوت. */
function probeAs(float $seconds, bool $audio = true): void
{
    app()->instance(Ffmpeg::class, new class($seconds, $audio) extends Ffmpeg
    {
        public function __construct(private float $seconds, private bool $audio) {}

        public function hasAudioTrack(string $path): bool
        {
            return $this->audio;
        }

        public function durationSeconds(string $path): float
        {
            return $this->seconds;
        }

        public function toSpeechAudio(string $path, string $directory): string
        {
            return $path;
        }
    });
}

/** ملفُّ صوتٍ صغير: ترويسةُ WAV ثمّ حشوٌ — مئة بايت، فثلاثة أجزاءٍ من أربعين. */
function lessonBytes(): string
{
    return str_pad(wavBytes(), 100, "\x01");
}

/** يبدأ رفعاً ويعيد ما قاله الخادم. */
function startUpload(string $name = 'درس الجمعة.wav', int $size = 100): array
{
    return test()->postJson('/panel/uploads', ['name' => $name, 'size' => $size])
        ->assertCreated()
        ->json();
}

/** جزءٌ خامٌ بجسم الطلب، كما يرسله المتصفّح. */
function putChunk(string $id, int $index, string $bytes): TestResponse
{
    return test()->call(
        'PUT',
        "/panel/uploads/{$id}/chunks/{$index}",
        server: ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
        content: $bytes,
    );
}

/** يرفع الملفّ كلّه أجزاءً ويجمعه — ويعيد معرّفه. */
function uploadWhole(string $bytes): string
{
    $id = startUpload(size: strlen($bytes))['id'];

    foreach (str_split($bytes, 40) as $index => $chunk) {
        putChunk($id, $index, $chunk)->assertOk();
    }

    test()->postJson("/panel/uploads/{$id}/complete")->assertOk();

    return $id;
}

function uploadPayload(string $uploadId, array $extra = []): array
{
    return [
        'source_kind' => 'upload',
        'upload_id' => $uploadId,
        'title_ar' => 'درس الجمعة',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        ...$extra,
    ];
}

// ── الرفع أجزاءً ─────────────────────────────────────────────────

beforeEach(function (): void {
    config()->set('khulasah.transcript.upload.chunk_bytes', 40);
    $this->actingAs($this->user);
});

it('announces an upload and says how to cut it', function (): void {
    $upload = startUpload(size: 100);

    expect($upload['chunk_bytes'])->toBe(40)
        ->and($upload['chunk_count'])->toBe(3)
        ->and($upload['received'])->toBe([])
        ->and(MediaUpload::query()->sole()->status)->toBe(MediaUpload::RECEIVING);
});

// يُرفض قبل أن يُرفع بايتٌ واحد — لا بعد نصف غيغابايت.
it('refuses a file too large or of the wrong type before any byte is sent', function (): void {
    $this->postJson('/panel/uploads', ['name' => 'درس.wav', 'size' => 500 * 1024 * 1024 + 1])
        ->assertUnprocessable()
        ->assertJson(['message' => trans('lectures.create.source.upload_too_large')]);

    $this->postJson('/panel/uploads', ['name' => 'درس.exe', 'size' => 100])
        ->assertUnprocessable()
        ->assertJson(['message' => trans('lectures.create.source.upload_invalid')]);

    expect(MediaUpload::query()->count())->toBe(0);
});

it('assembles the chunks in order, whatever order they arrived in', function (): void {
    probeAs(seconds: 1_830.4);
    $bytes = lessonBytes();
    $id = startUpload(size: 100)['id'];

    // الأخيرُ أوّلاً: الترتيب بالرقم لا بالوصول.
    putChunk($id, 2, substr($bytes, 80))->assertOk();
    putChunk($id, 0, substr($bytes, 0, 40))->assertOk();
    putChunk($id, 1, substr($bytes, 40, 40))->assertOk();

    $ready = $this->postJson("/panel/uploads/{$id}/complete")->assertOk()->json();

    $upload = MediaUpload::query()->sole();

    expect($ready['status'])->toBe('ready')
        ->and($ready['duration_seconds'])->toBe(1_831)
        ->and(Storage::disk('local')->get($upload->path))->toBe($bytes)
        // باسمٍ مولَّد تحت الجهة، لا باسم المستخدم.
        ->and($upload->path)->toStartWith("uploads/{$this->tenant->id}/")
        ->and($upload->path)->not->toContain('الجمعة');

    // الأجزاء صارت ملفّاً، فلا يبقى منها شيء.
    expect(Storage::disk('local')->allFiles("uploads/incoming/{$id}"))->toBe([]);
});

// **الجزء الساقط يُعاد وحده** — والمتصفّح يسأل ما وصل فيُكمل الناقص.
it('tells a resumed upload which chunks already arrived, and lets one be re-sent', function (): void {
    $bytes = lessonBytes();
    $id = startUpload(size: 100)['id'];

    putChunk($id, 0, substr($bytes, 0, 40))->assertOk();
    putChunk($id, 2, substr($bytes, 80))->assertOk();
    // إعادةُ جزءٍ وصل تكتب فوقه بلا ضرر.
    putChunk($id, 0, substr($bytes, 0, 40))->assertOk();

    $this->getJson("/panel/uploads/{$id}")->assertOk()->assertJson(['received' => [0, 2]]);

    // والجمعُ قبل اكتمالها لا يحذف شيئاً: يقول «ناقص» فيُرفع الناقص.
    $this->postJson("/panel/uploads/{$id}/complete")
        ->assertStatus(409)
        ->assertJson(['reason' => 'upload_incomplete']);

    expect(Storage::disk('local')->allFiles("uploads/incoming/{$id}"))->toHaveCount(2);
});

// جزءٌ ناقص يُجمع ملفّاً تالفاً لا يُكتشف إلّا عند ffmpeg.
it('refuses a chunk of the wrong size or number', function (): void {
    $id = startUpload(size: 100)['id'];

    putChunk($id, 0, str_repeat('a', 39))->assertUnprocessable();
    putChunk($id, 3, str_repeat('a', 40))->assertUnprocessable();

    expect(Storage::disk('local')->allFiles("uploads/incoming/{$id}"))->toBe([]);
});

// النوع بالمحتوى لا باللاحقة. **وما رُفض يُحذف فوراً**: لا يبقى ليُرفع غيرُه بجانبه.
it('checks the assembled content and deletes a file that is not audio', function (): void {
    probeAs(seconds: 600.0);
    $id = startUpload(name: 'درس.mp3', size: 100)['id'];

    // مئةُ بايتٍ من نصٍّ صريح، لا صوتٍ فيها.
    foreach (str_split(str_repeat('text ', 20), 40) as $index => $chunk) {
        putChunk($id, $index, $chunk)->assertOk();
    }

    $this->postJson("/panel/uploads/{$id}/complete")
        ->assertUnprocessable()
        ->assertJson(['message' => trans('lectures.create.source.upload_invalid')]);

    expect(MediaUpload::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('uploads'))->toBe([]);
});

// T-205: **أداةٌ غائبة عطلٌ عندنا لا في الملفّ.** فلا يُقال «ملفّك تالف»، ولا يُحذف
// ما رُفع: يُستأنف الجمعُ بعد إصلاح الخادم بلا رفعٍ ثانٍ.
it('says a missing ffprobe is a server fault, keeps the chunks, and completes once it is back', function (): void {
    Log::spy();
    $installed = false;

    Process::fake(function ($process) use (&$installed) {
        return match (true) {
            ! $installed => Process::result(errorOutput: 'exec: ffprobe: not found', exitCode: 127),
            in_array('a:0', $process->command, true) => Process::result('1'),
            default => Process::result('600.0'),
        };
    });
    $id = startUpload(size: 100)['id'];

    foreach (str_split(lessonBytes(), 40) as $index => $chunk) {
        putChunk($id, $index, $chunk)->assertOk();
    }

    $this->postJson("/panel/uploads/{$id}/complete")
        ->assertStatus(503)
        ->assertJson([
            'reason' => 'upload_unavailable',
            'message' => trans('lectures.create.source.upload_unavailable'),
        ]);

    Log::shouldHaveReceived('error')->withArgs(fn (string $event, array $context): bool => $event === 'upload.media_tool_unavailable'
        && str_contains($context['error'], 'ffprobe: not found'));

    // الأجزاءُ باقية، والملفُّ المجموع لم يبقَ.
    expect(MediaUpload::query()->sole()->status)->toBe(MediaUpload::RECEIVING)
        ->and(Storage::disk('local')->allFiles("uploads/incoming/{$id}"))->toHaveCount(3)
        ->and(Storage::disk('local')->allFiles("uploads/{$this->tenant->id}"))->toBe([]);

    $installed = true;

    $this->postJson("/panel/uploads/{$id}/complete")->assertOk()->assertJson(['status' => 'ready']);
});

// وffprobe الحاضرُ إذا رفض الملفّ فالعيبُ في الملفّ.
it('still calls a file ffprobe rejects unreadable', function (): void {
    Process::fake(['*' => Process::result(errorOutput: 'Invalid data found when processing input', exitCode: 1)]);
    $id = startUpload(size: 100)['id'];

    foreach (str_split(lessonBytes(), 40) as $index => $chunk) {
        putChunk($id, $index, $chunk)->assertOk();
    }

    $this->postJson("/panel/uploads/{$id}/complete")
        ->assertUnprocessable()
        ->assertJson(['reason' => 'upload_invalid']);

    expect(MediaUpload::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('uploads'))->toBe([]);
});

it('refuses a video with no sound, and deletes it', function (): void {
    probeAs(seconds: 600.0, audio: false);
    $id = startUpload(size: 100)['id'];

    foreach (str_split(lessonBytes(), 40) as $index => $chunk) {
        putChunk($id, $index, $chunk);
    }

    $this->postJson("/panel/uploads/{$id}/complete")
        ->assertUnprocessable()
        ->assertJson(['message' => trans('lectures.create.source.upload_no_audio')]);

    expect(Storage::disk('local')->allFiles('uploads'))->toBe([]);
});

// «المدّة تُقرأ بـ ffprobe وتُفحص على حدّ الاشتراك قبل أي معالجة» — §5-أ-4-ب.
it('refuses a recording longer than the plan allows as soon as it is uploaded', function (): void {
    probeAs(seconds: 120 * 60.0);
    $id = startUpload(size: 100)['id'];

    foreach (str_split(lessonBytes(), 40) as $index => $chunk) {
        putChunk($id, $index, $chunk);
    }

    $this->postJson("/panel/uploads/{$id}/complete")->assertUnprocessable();

    expect(MediaUpload::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('uploads'))->toBe([]);
});

// أُزيل الملفّ، أو اختير غيرُه، أو غادر المستخدم: يُحذف كلّ ما رُفع.
it('deletes every byte of a cancelled upload', function (): void {
    $bytes = lessonBytes();
    $id = startUpload(size: 100)['id'];
    putChunk($id, 0, substr($bytes, 0, 40));

    $this->deleteJson("/panel/uploads/{$id}")->assertNoContent();

    expect(MediaUpload::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('uploads'))->toBe([]);
});

it('hides one tenant\'s upload from another', function (): void {
    // قبل أوّل طلب: بعده يكون السياقُ على جهةٍ، فلا يُكتب مستخدمٌ لغيرها.
    $other = User::factory()->owner()->for_(Tenant::factory()->create())->create();

    $id = startUpload(size: 100)['id'];

    $this->actingAs($other)->getJson("/panel/uploads/{$id}")->assertNotFound();
    $this->actingAs($other)->deleteJson("/panel/uploads/{$id}")->assertNotFound();
    putChunk($id, 0, str_repeat('a', 40))->assertNotFound();

    expect(MediaUpload::query()->withoutGlobalScopes()->count())->toBe(1);
});

// ── الإنشاء ──────────────────────────────────────────────────────

it('creates the lesson from a completed upload, and the job takes the file over', function (): void {
    probeAs(seconds: 1_830.4);
    $id = uploadWhole(lessonBytes());
    $path = MediaUpload::query()->sole()->path;

    $this->post('/panel/lectures', uploadPayload($id, [
        // رابطٌ بقي في الحقل من تبويبٍ آخر لا يُحفظ.
        'source_url' => 'https://www.youtube.com/watch?v=leftover123',
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $job = SummaryJob::query()->sole();

    expect($job->lecture->source_platform)->toBe('upload')
        ->and($job->lecture->source_url)->toBeNull()
        ->and($job->lecture->duration_seconds)->toBe(1_831)
        ->and($job->upload_path)->toBe($path)
        ->and($job->upload_name)->toBe('درس الجمعة.wav')
        // صار ملكَ المهمّة: لا صفّ رفعٍ يُكنس فيأخذ ملفّها معه.
        ->and(MediaUpload::query()->count())->toBe(0);

    Storage::disk('local')->assertExists($path);
});

it('asks for the file when none was uploaded', function (): void {
    $this->post('/panel/lectures', uploadPayload(''))
        ->assertSessionHasErrors(['upload_id' => trans('lectures.create.source.upload_required')]);

    expect(Lecture::query()->count())->toBe(0);
});

it('refuses an upload that is unfinished, swept or unknown', function (): void {
    $unfinished = startUpload(size: 100)['id'];

    $this->post('/panel/lectures', uploadPayload($unfinished))
        ->assertSessionHasErrors(['upload_id' => trans('lectures.create.source.upload_expired')]);

    $this->post('/panel/lectures', uploadPayload('9b2f4a8e-0000-4000-8000-000000000000'))
        ->assertSessionHasErrors('upload_id');

    expect(Lecture::query()->count())->toBe(0);
});

it('offers the accepted media types to the page', function (): void {
    $this->get('/panel/lectures/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('limits.media_extensions', config('khulasah.transcript.upload.media_extensions'))
            ->where('limits.upload_max_bytes', 500 * 1024 * 1024));
});

// ── مرحلة التفريغ ────────────────────────────────────────────────

/** مهمّةٌ في مرحلة التفريغ، وملفُّها على القرص. */
function uploadedJob(Tenant $tenant, array $attributes = []): SummaryJob
{
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id, 'source_url' => null]);
    Storage::disk('local')->put("uploads/{$tenant->id}/lesson.wav", wavBytes());

    return SummaryJob::factory()->for_($lecture)->inState(JobState::Transcribing)->create([
        'upload_path' => "uploads/{$tenant->id}/lesson.wav",
        'upload_name' => 'lesson.wav',
        ...$attributes,
    ]);
}

it('transcribes the uploaded file, then deletes it', function (): void {
    probeAs(seconds: 900.0);

    app()->instance(SpeechToText::class, new class extends FakeSpeechToText
    {
        public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
        {
            return implode(' ', array_fill(0, 600, 'كلمة'));
        }
    });

    $job = uploadedJob($this->tenant);

    app(ResolveTranscript::class)->handle($job);

    $job->refresh();

    expect($job->transcript_source)->toBe(TranscriptSource::Whisper)
        ->and($job->transcript_word_count)->toBe(600)
        ->and($job->upload_path)->toBeNull()
        ->and($job->upload_name)->toBeNull();

    // صار نصّاً، فلا نحتفظ بتسجيل الدرس.
    Storage::disk('local')->assertMissing("uploads/{$this->tenant->id}/lesson.wav");
});

// إخفاقٌ في التفريغ يُبقي الملفّ لـ«أعد المحاولة»، فلا يُطلب رفعُه ثانيةً.
it('keeps the file when transcription fails, and the resumed job carries it', function (): void {
    probeAs(seconds: 900.0);

    app()->instance(SpeechToText::class, new class extends FakeSpeechToText
    {
        public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
        {
            throw TranscriptFailed::because(TranscriptErrorCode::TranscriptionFailed, 'Gemini مشغول.');
        }
    });

    $job = uploadedJob($this->tenant);

    expect(fn () => app(ResolveTranscript::class)->handle($job))->toThrow(TranscriptFailed::class);

    Storage::disk('local')->assertExists("uploads/{$this->tenant->id}/lesson.wav");

    $job->forceFill(['state' => JobState::Failed])->saveQuietly();
    $fresh = app(ResumeFailedJob::class)->handle($job->refresh());

    expect($fresh->upload_path)->toBe("uploads/{$this->tenant->id}/lesson.wav")
        ->and($fresh->upload_name)->toBe('lesson.wav');
});

// ── الكنس ────────────────────────────────────────────────────────

it('sweeps abandoned uploads after the retention period, never a running job\'s', function (): void {
    config()->set('khulasah.transcript.upload.retention_days', 7);

    $failed = uploadedJob($this->tenant, ['state' => JobState::Failed, 'upload_path' => 'uploads/1/failed.wav']);
    Storage::disk('local')->put('uploads/1/failed.wav', 'x');

    $running = uploadedJob($this->tenant, ['upload_path' => 'uploads/1/running.wav']);
    Storage::disk('local')->put('uploads/1/running.wav', 'x');

    Storage::disk('local')->put('uploads/1/fresh.wav', 'x');

    // عمرُ الملفّ من القرص لا من ساعة الاختبار — فيُكتب تاريخُه بيده.
    $week = now()->subDays(8)->getTimestamp();
    touch(Storage::disk('local')->path('uploads/1/failed.wav'), $week);
    touch(Storage::disk('local')->path('uploads/1/running.wav'), $week);

    $this->artisan('khulasah:prune-uploads')->assertSuccessful();

    Storage::disk('local')->assertMissing('uploads/1/failed.wav');
    Storage::disk('local')->assertExists('uploads/1/running.wav');
    Storage::disk('local')->assertExists('uploads/1/fresh.wav');

    expect($failed->refresh()->upload_path)->toBeNull()
        ->and($running->refresh()->upload_path)->toBe('uploads/1/running.wav');
});

// انقطع الاتّصال ولم يعد صاحبُه، أو رُفع الملفّ ولم يُرسَل النموذج.
it('sweeps unfinished and unsent uploads after their hours, with every chunk', function (): void {
    config()->set('khulasah.transcript.upload.stale_hours', 24);
    probeAs(seconds: 600.0);
    $bytes = lessonBytes();

    $unfinished = startUpload(size: 100)['id'];
    putChunk($unfinished, 0, substr($bytes, 0, 40));

    $unsent = uploadWhole($bytes);
    $unsentPath = MediaUpload::query()->find($unsent)->path;

    // وأجزاءٌ بلا صفّ — عطلٌ وقع بين كتابة الجزء وحفظ الصفّ.
    Storage::disk('local')->put('uploads/incoming/7d0c2b7e-0000-4000-8000-000000000000/0.part', 'x');
    touch(Storage::disk('local')->path('uploads/incoming/7d0c2b7e-0000-4000-8000-000000000000/0.part'), now()->subDay()->subHour()->getTimestamp());

    $this->travel(25)->hours();

    // ورفعٌ حيّ بدأ للتوّ لا يُمسّ.
    $fresh = startUpload(size: 100)['id'];
    putChunk($fresh, 0, substr($bytes, 0, 40));

    $this->artisan('khulasah:prune-uploads')->assertSuccessful();

    expect(MediaUpload::query()->pluck('id')->all())->toBe([$fresh]);

    Storage::disk('local')->assertMissing("uploads/incoming/{$unfinished}/0.part");
    Storage::disk('local')->assertMissing($unsentPath);
    Storage::disk('local')->assertMissing('uploads/incoming/7d0c2b7e-0000-4000-8000-000000000000/0.part');
    Storage::disk('local')->assertExists("uploads/incoming/{$fresh}/0.part");
});

// الإلغاءُ من الشاشة أو من لوحة المشرف يمرّ من الانتقال نفسه.
it('deletes the recording when its job is cancelled', function (): void {
    $job = uploadedJob($this->tenant);

    $this->post("/panel/jobs/{$job->id}/cancel")->assertRedirect();

    expect($job->refresh()->upload_path)->toBeNull();
    Storage::disk('local')->assertMissing("uploads/{$this->tenant->id}/lesson.wav");
});

// إخفاقٌ لا يُصلحه إلّا رفعٌ جديد: إبقاءُ الملفّ نصفُ غيغابايت لا يقرؤه أحد.
it('deletes the recording when the job fails for a reason only a new file can fix', function (): void {
    $job = uploadedJob($this->tenant);

    app(TransitionJob::class)->handle($job, JobState::Failed, errorCode: TranscriptErrorCode::TranscriptTooShort->value);

    expect($job->refresh()->upload_path)->toBeNull();
    Storage::disk('local')->assertMissing("uploads/{$this->tenant->id}/lesson.wav");
});

// وإخفاقٌ عارض يُبقيه لـ«أعد المحاولة» — بلا رفعٍ ثانٍ.
it('keeps the recording when the job fails for a passing reason', function (): void {
    $job = uploadedJob($this->tenant);

    app(TransitionJob::class)->handle($job, JobState::Failed, errorCode: TranscriptErrorCode::TranscriptionFailed->value);

    expect($job->refresh()->upload_path)->toBe("uploads/{$this->tenant->id}/lesson.wav");
    Storage::disk('local')->assertExists("uploads/{$this->tenant->id}/lesson.wav");
});
