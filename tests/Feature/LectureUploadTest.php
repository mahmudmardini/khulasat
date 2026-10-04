<?php

declare(strict_types=1);

use App\Actions\Stages\ResolveTranscript;
use App\Actions\Summary\ResumeFailedJob;
use App\Contracts\SpeechToText;
use App\Domain\Summary\JobState;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Transcript\Ffmpeg;
use App\Services\Transcript\Speech\FakeSpeechToText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

function uploadPayload(?UploadedFile $file, array $extra = []): array
{
    return [
        'source_kind' => 'upload',
        'source_file' => $file,
        'title_ar' => 'درس الجمعة',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        ...$extra,
    ];
}

// ── الإنشاء ──────────────────────────────────────────────────────

it('creates the lesson from an uploaded recording and keeps the file for the pipeline', function (): void {
    probeAs(seconds: 1_830.4);

    $this->actingAs($this->user)
        ->post('/panel/lectures', uploadPayload(
            UploadedFile::fake()->createWithContent('درس الجمعة.wav', wavBytes()),
            // رابطٌ بقي في الحقل من تبويبٍ آخر لا يُحفظ.
            ['source_url' => 'https://www.youtube.com/watch?v=leftover123'],
        ))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $job = SummaryJob::query()->sole();
    $lecture = $job->lecture;

    expect($lecture->source_platform)->toBe('upload')
        ->and($lecture->source_url)->toBeNull()
        ->and($lecture->source_key)->toBeNull()
        // من ffprobe، مقرَّبةً إلى أعلى.
        ->and($lecture->duration_seconds)->toBe(1_831)
        ->and($job->upload_name)->toBe('درس الجمعة.wav')
        // باسمٍ مولَّد تحت الجهة، لا باسم المستخدم.
        ->and($job->upload_path)->toStartWith("uploads/{$this->tenant->id}/")
        ->and($job->upload_path)->not->toContain('الجمعة');

    Storage::disk('local')->assertExists($job->upload_path);
});

it('asks for the file when none was chosen', function (): void {
    $this->actingAs($this->user)
        ->post('/panel/lectures', uploadPayload(null))
        ->assertSessionHasErrors('source_file');

    expect(Lecture::query()->count())->toBe(0);
});

// النوع بالمحتوى لا باللاحقة — اللاحقة يكتبها المستخدم.
it('refuses a file that only claims to be audio', function (): void {
    probeAs(seconds: 600.0);

    $this->actingAs($this->user)
        ->post('/panel/lectures', uploadPayload(UploadedFile::fake()->createWithContent('درس.mp3', 'هذا نصّ لا صوت')))
        ->assertSessionHasErrors(['source_file' => trans('lectures.create.source.upload_invalid')]);

    expect(Lecture::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a video with no sound before the quota is spent', function (): void {
    probeAs(seconds: 600.0, audio: false);

    $this->actingAs($this->user)
        ->post('/panel/lectures', uploadPayload(UploadedFile::fake()->createWithContent('شاشة.wav', wavBytes())))
        ->assertSessionHasErrors(['source_file' => trans('lectures.create.source.upload_no_audio')]);

    expect(Lecture::query()->count())->toBe(0);
});

// «المدّة تُقرأ بـ ffprobe وتُفحص على حدّ الاشتراك قبل أي معالجة» — §5-أ-4-ب.
it('refuses a recording longer than the plan allows, at upload time', function (): void {
    probeAs(seconds: 120 * 60.0);

    $this->actingAs($this->user)
        ->post('/panel/lectures', uploadPayload(UploadedFile::fake()->createWithContent('طويل.wav', wavBytes())))
        ->assertSessionHasErrors('source_file');

    expect(Lecture::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('offers the accepted media types to the page', function (): void {
    $this->actingAs($this->user)
        ->get('/panel/lectures/create')
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
