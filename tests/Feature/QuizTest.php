<?php

declare(strict_types=1);

use App\Actions\Quiz\GenerateQuiz;
use App\Actions\Stages\RenderAndPublish;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\ModelCall;
use App\Models\Output;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Model\FakeModelGateway;
use App\Services\Quota\SpendCap;
use App\Support\Ui\JobProgress;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/*
 * اختبارُ الفهم برابطٍ يُشارَك — T-195.
 *
 * ثلاثةٌ تحكم هذا الملفّ: **الملخّصُ لا يسقط بسبب الاختبار**، و**المتصفّحُ
 * لا يعرف الصحيح قبل أوانه**، و**لفظُ الشاهد من مصدره لا من النموذج**.
 */

const QUIZ_FIRST_EVIDENCE = 'لفظُ الشاهد الأوّل كما في مصدره';

const QUIZ_SECOND_EVIDENCE = 'لفظُ الشاهد الثاني كما في مصدره';

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'name_ar' => 'جهة الاختبار']);
    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
        'want_quiz' => true,
    ]);

    $this->job = SummaryJob::factory()->for_($this->lecture)->inState(JobState::Published)->create([
        'published_at' => now(),
        'structure_json' => [
            'title_ar' => 'عنوان الدرس',
            'core_concept' => 'الفكرة الجامعة.',
            'axes' => [['name' => 'المحور الأوّل'], ['name' => 'المحور الثاني']],
        ],
    ]);

    foreach ([QUIZ_FIRST_EVIDENCE, QUIZ_SECOND_EVIDENCE] as $text) {
        EvidenceItem::factory()->for_($this->job)->create([
            'kind' => 'hadith',
            'raw_text' => 'لفظٌ كما سُمع',
            'matched_text' => $text,
            'source_ref' => 'مصدرٌ محايد',
            'match_status' => MatchStatus::Exact,
            'review_status' => ReviewStatus::AutoPassed,
        ]);
    }

    $this->gateway = app(FakeModelGateway::class);
    $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);
});

function quizFor(SummaryJob $job): Quiz
{
    return app(GenerateQuiz::class)->handle($job);
}

/** يقود المهمّة إلى `rendering` بالمسار الشرعي. */
function quizRenderingReady(SummaryJob $job): SummaryJob
{
    $job->forceFill(['state' => JobState::Queued->value, 'published_at' => null])->saveQuietly();

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job->refresh();
}

function startQuiz(Quiz $quiz, string $ip = '203.0.113.7'): QuizAttempt
{
    test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->post(route('quiz.start', $quiz->token))
        ->assertRedirect();

    return QuizAttempt::acrossTenants()->latest('id')->firstOrFail();
}

/*
 * ─── البناء ───────────────────────────────────────────────────────────
 */

it('لا ينادي النموذج حين لا يُطلب الاختبار', function (): void {
    $this->lecture->forceFill(['want_quiz' => false])->save();

    app(RenderAndPublish::class)->handle(quizRenderingReady($this->job));

    expect(Quiz::acrossTenants()->count())->toBe(0)
        ->and(ModelCall::query()->where('stage', Stage::Quiz->value)->exists())->toBeFalse();
});

it('يبني الاختبار قبل الصفحة، ويرسم زرَّه فيها، ويقيّد كلفته تحت quiz', function (): void {
    $urls = app(RenderAndPublish::class)->handle(quizRenderingReady($this->job));

    $quiz = Quiz::acrossTenants()->firstOrFail();
    $page = Storage::disk('public')->get(
        Output::acrossTenants()->where('type', OutputType::Page->value)->value('storage_path'),
    );

    expect($urls)->toHaveKey(OutputType::Page->value)
        ->and($quiz->isOpen())->toBeTrue()
        ->and($quiz->questions()->count())->toBe(5)
        ->and($page)->toContain($quiz->publicUrl())
        ->and($page)->toContain('اختبر فهمك')
        ->and(ModelCall::query()->where('stage', Stage::Quiz->value)->exists())->toBeTrue();
});

it('ينشر الملخّص ولو أخفق الاختبار، ويحفظ السبب', function (string $case): void {
    $this->gateway->willReturn(Stage::Quiz, $case);

    $job = quizRenderingReady($this->job);
    $urls = app(RenderAndPublish::class)->handle($job);

    $quiz = Quiz::acrossTenants()->firstOrFail();

    expect($urls)->toHaveKey(OutputType::Page->value)
        ->and($job->refresh()->state)->toBe(JobState::Published)
        ->and($quiz->state)->toBe(Quiz::STATE_FAILED)
        ->and($quiz->failure_reason)->not->toBeNull()
        ->and(Storage::disk('public')->get(Output::acrossTenants()->where('type', 'page')->value('storage_path')))
        ->not->toContain('/q/');
})->with(['too_few', 'provider_error']);

it('لا يُعيد البناء عند «حدّث المنشور»', function (): void {
    quizFor($this->job);
    $calls = ModelCall::query()->where('stage', Stage::Quiz->value)->count();

    app(RenderAndPublish::class)->handle($this->job->refresh());

    expect(ModelCall::query()->where('stage', Stage::Quiz->value)->count())->toBe($calls);
});

it('لا يُظهر الزرّ في المعاينة', function (): void {
    $this->job->forceFill(['body_html' => '<p>متنُ الملخّص.</p>'])->save();
    quizFor($this->job);

    $this->actingAs($this->owner)
        ->get(route('jobs.preview.page', $this->job))
        ->assertOk()
        ->assertDontSee('/q/', false);
});

/*
 * ─── المشارك ──────────────────────────────────────────────────────────
 */

/*
 * ★ **لا يُطلب من المشارك شيءٌ ولا يُحفظ عنه شيء** — قرار @HasanSiwi، ٤ أكتوبر
 * ٢٠٢٦: لا اسمَ ولا IP.
 */
it('يبدأ المشارك بلا اسمٍ ولا حقل، ولا يُحفظ عنه شيء', function (): void {
    $quiz = quizFor($this->job);

    $this->get(route('quiz.show', $quiz->token))
        ->assertOk()
        ->assertSee('عنوان الدرس')
        ->assertDontSee('name="name"', false)
        ->assertDontSee('عنوان IP');

    startQuiz($quiz);

    expect(QuizAttempt::acrossTenants()->count())->toBe(1)
        ->and($quiz->refresh()->opens_count)->toBe(1)
        ->and(Schema::getColumnListing('quiz_attempts'))->not->toContain('ip')
        ->and(Schema::getColumnListing('quiz_attempts'))->not->toContain('participant_name');
});

it('يُري اسمَ الملقي كما كتبته الجهة، بلا لقبٍ يُضاف من عندنا', function (): void {
    $this->lecture->forceFill(['speaker_title' => null])->save();
    $quiz = quizFor($this->job);

    $this->get(route('quiz.show', $quiz->token))
        ->assertSee('اسم الملقي')
        ->assertDontSee('الشيخ');
});

it('لا يحمل HTML الأسئلة ولا ردُّ الحفظ الجوابَ الصحيح في وضع «في الآخر»', function (): void {
    $quiz = quizFor($this->job);
    $attempt = startQuiz($quiz);
    $question = $quiz->questions()->first();

    $html = $this->get(route('quiz.attempt', [$quiz->token, $attempt->token]))->assertOk()->getContent();

    expect($html)->not->toContain($question->explanation)
        ->and($html)->not->toContain('class="kq-opt is-correct"');

    $this->postJson(route('quiz.answer', [$quiz->token, $attempt->token]), ['question' => $question->id, 'option' => 0])
        ->assertOk()
        ->assertExactJson(['saved' => true]);
});

it('يُري لفظ الشاهد من مصدره', function (): void {
    $quiz = quizFor($this->job);
    $attempt = startQuiz($quiz);

    $this->get(route('quiz.attempt', [$quiz->token, $attempt->token]))
        ->assertSee(QUIZ_FIRST_EVIDENCE)
        ->assertSee(QUIZ_SECOND_EVIDENCE);
});

it('يصحّح على الخادم، ويهنّئ عند ٨٠٪ فأكثر وحدها', function (bool $allRight): void {
    $quiz = quizFor($this->job);
    $attempt = startQuiz($quiz);

    $answers = [];
    foreach ($quiz->questions()->get() as $question) {
        $answers[$question->id] = $allRight ? $question->correct_index : ($question->correct_index + 1) % count($question->options);
    }

    $this->post(route('quiz.finish', [$quiz->token, $attempt->token]), ['answers' => $answers])
        ->assertRedirect(route('quiz.result', [$quiz->token, $attempt->token]));

    $attempt->refresh();

    expect($attempt->score)->toBe($allRight ? 5 : 0)
        ->and($attempt->finished_at)->not->toBeNull()
        ->and($attempt->duration_seconds)->toBeGreaterThanOrEqual(0);

    $result = $this->get(route('quiz.result', [$quiz->token, $attempt->token]))->assertOk()->assertSee('عنوان الدرس');

    $allRight ? $result->assertSee('مبارك') : $result->assertDontSee('مبارك');
})->with(['كلّه صحيح' => true, 'كلّه خطأ' => false]);

it('يكشف الحكم بعد كلّ سؤال في وضع «بعد كلّ سؤال»، ويُقفل الجواب', function (): void {
    $quiz = quizFor($this->job);
    $quiz->forceFill(['feedback' => Quiz::FEEDBACK_IMMEDIATE])->save();
    $attempt = startQuiz($quiz);
    $question = $quiz->questions()->first();
    $url = route('quiz.answer', [$quiz->token, $attempt->token]);

    $this->postJson($url, ['question' => $question->id, 'option' => $question->correct_index])
        ->assertOk()
        ->assertJson(['correct' => true, 'correct_index' => $question->correct_index]);

    $this->postJson($url, ['question' => $question->id, 'option' => ($question->correct_index + 1) % 3])
        ->assertStatus(409);
});

/*
 * **ستّون بدءاً في الساعة من عنوانٍ واحد** — المشاركون بلا أسماء، وحلقةٌ على
 * شبكةٍ واحدة تخرج بعنوانٍ واحد. وما بعد الستّين يُردّ.
 */
it('يردّ البدء الحادي والستّين في الساعة من IP واحد بـ429', function (): void {
    $quiz = quizFor($this->job);

    foreach (range(1, 60) as $i) {
        startQuiz($quiz);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->post(route('quiz.start', $quiz->token))
        ->assertStatus(429);

    startQuiz($quiz, '198.51.100.4');

    expect(QuizAttempt::acrossTenants()->count())->toBe(61);
});

it('يُغلق الاختبار مع إغلاقه ومع إلغاء نشر ملخّصه', function (string $how): void {
    $quiz = quizFor($this->job);

    $how === 'closed'
        ? $quiz->forceFill(['status' => Quiz::STATUS_CLOSED])->save()
        : $this->job->forceFill(['published_at' => null])->save();

    $this->get(route('quiz.show', $quiz->token))->assertOk()->assertSee('هذا الاختبار مغلق');
    $this->post(route('quiz.start', $quiz->token))->assertRedirect();

    expect(QuizAttempt::acrossTenants()->count())->toBe(0);
})->with(['closed', 'unpublished']);

it('يردّ ٤٠٤ على رمزٍ مجهول وعلى اختبارٍ تعذّر بناؤه', function (): void {
    $this->get('/q/abcdefghijkl')->assertNotFound();

    $this->gateway->willReturn(Stage::Quiz, 'too_few');
    $quiz = quizFor($this->job);

    $this->get(route('quiz.show', $quiz->token))->assertNotFound();
});

/*
 * ─── اللوحة ───────────────────────────────────────────────────────────
 */

it('يعرض الاختبارَ تبويباً في المعاينة، ويحيل مسارَه القديم إليه', function (): void {
    quizFor($this->job);

    $this->actingAs($this->owner)
        ->get(route('jobs.quiz', $this->job))
        ->assertRedirect(route('jobs.preview', ['job' => $this->job, 'tab' => 'quiz']));

    $this->actingAs($this->owner)
        ->get(route('jobs.preview', $this->job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Jobs/Preview')
            ->has('quiz.quiz.questions', 5)
            ->where('quiz.can_edit', true));
});

it('يمرّ تعديلُ النصّ بالحارس', function (): void {
    $quiz = quizFor($this->job);
    $question = $quiz->questions()->where('kind', 'single')->first();
    $url = route('jobs.quiz.questions.update', [$this->job, $question->id]);

    $this->actingAs($this->owner)->put($url, [
        'prompt' => 'قال رسول الله ﷺ: «من صلّى الفجر في جماعةٍ فله أجرُ حجّةٍ تامّة»',
        'explanation' => 'شرح.',
        'options' => array_column($question->options, 'text'),
    ])->assertSessionHasErrors("question.{$question->id}");

    $this->actingAs($this->owner)->put($url, [
        'prompt' => 'سؤالٌ معدَّل؟',
        'explanation' => 'شرحٌ معدَّل.',
        'options' => array_column($question->options, 'text'),
    ])->assertSessionHasNoErrors();

    expect($question->refresh()->prompt)->toBe('سؤالٌ معدَّل؟');
});

it('يعطّل الحذف وإعادة التوليد بعد أوّل محاولة', function (): void {
    $quiz = quizFor($this->job);
    startQuiz($quiz);
    $question = $quiz->questions()->first();

    $this->actingAs($this->owner)
        ->delete(route('jobs.quiz.questions.destroy', [$this->job, $question->id]))
        ->assertSessionHasErrors('quiz');

    $this->actingAs($this->owner)
        ->post(route('jobs.quiz.store', $this->job))
        ->assertSessionHasErrors('quiz');

    expect($quiz->questions()->count())->toBe(5);
});

it('يحذف سؤالاً قبل المحاولات ويُعيد الترقيم', function (): void {
    $quiz = quizFor($this->job);
    $first = $quiz->questions()->first();

    $this->actingAs($this->owner)
        ->delete(route('jobs.quiz.questions.destroy', [$this->job, $first->id]))
        ->assertSessionHasNoErrors();

    expect($quiz->questions()->pluck('position')->all())->toBe([1, 2, 3, 4]);
});

it('يبني اختباراً من اللوحة بلا حصّة، ويفحص سقف الإنفاق قبله', function (): void {
    app(SpendCap::class)->halt('اختبار');

    $this->actingAs($this->owner)
        ->post(route('jobs.quiz.store', $this->job))
        ->assertSessionHasErrors('quiz');

    expect(ModelCall::query()->where('stage', Stage::Quiz->value)->exists())->toBeFalse();

    app(SpendCap::class)->release();

    $this->actingAs($this->owner)
        ->post(route('jobs.quiz.store', $this->job))
        ->assertSessionHasNoErrors();

    expect($this->job->quiz()->first()?->isReady())->toBeTrue();
});

it('لا يعدّل القارئ الاختبار', function (): void {
    quizFor($this->job);
    $viewer = User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($viewer)
        ->put(route('jobs.quiz.update', $this->job), ['status' => Quiz::STATUS_CLOSED])
        ->assertForbidden();
});

it('لا تقرأ جهةٌ اختبارَ جهةٍ أخرى ولا تعدّله', function (): void {
    quizFor($this->job);

    $other = Tenant::factory()->create(['slug' => 'tenant-b']);
    $intruder = User::factory()->owner()->create(['tenant_id' => $other->id]);

    $this->actingAs($intruder)->get(route('jobs.quiz', $this->job))->assertNotFound();
    $this->actingAs($intruder)
        ->put(route('jobs.quiz.update', $this->job), ['status' => Quiz::STATUS_CLOSED])
        ->assertNotFound();

    expect(Quiz::acrossTenants()->firstOrFail()->status)->toBe(Quiz::STATUS_OPEN);
});

/*
 * ─── مراحلُ الإعداد ───────────────────────────────────────────────────
 */

it('يُظهر «بناء الاختبار» في مراحل الإعداد لمن طلبه، قبل إخراج الصفحة', function (): void {
    $job = quizRenderingReady($this->job);
    $states = fn (): array => array_column(JobProgress::steps($job->refresh()), 'state', 'key');

    expect(array_column(JobProgress::steps($job), 'key'))
        ->toBe(['transcribing', 'cleaning', 'structuring', 'extracting', 'verifying', 'review', 'writing', 'quiz', 'rendering']);

    // يُبنى الآن: جاريةٌ واحدة لا اثنتان.
    expect($states())->toMatchArray(['writing' => 'done', 'quiz' => 'active', 'rendering' => 'pending']);

    quizFor($job);

    expect($states())->toMatchArray(['quiz' => 'done', 'rendering' => 'active']);
});

it('لا يُظهر مرحلة الاختبار لمن لم يطلبه', function (): void {
    $this->lecture->forceFill(['want_quiz' => false])->save();

    expect(array_column(JobProgress::steps($this->job->refresh()), 'key'))->not->toContain('quiz');
});
