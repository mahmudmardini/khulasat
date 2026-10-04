<?php

declare(strict_types=1);

use App\Actions\Quiz\BuildQuizReport;
use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;

/*
 * تقاريرُ الاختبارات — T-201. **على بياناتٍ معلومة**، فكلُّ رقمٍ يُحسب باليد.
 *
 * والقاعدةُ الحاكمة: المحسوبُ أوّلُ محاولةٍ منتهية لكلّ (اسم + IP). فالمعيدُ
 * لا يرفع المتوسّط، ومن لم يُنهِ يُنقص نسبةَ الإكمال وحدها.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->owner = User::factory()->owner()->create(['tenant_id' => $this->tenant->id]);

    $this->quiz = reportQuiz($this->tenant, ['المحور الأوّل', 'المحور الثاني']);
});

/** @param list<string> $axes */
function reportQuiz(Tenant $tenant, array $axes): Quiz
{
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id, 'title_ar' => 'عنوان الدرس']);
    $job = SummaryJob::factory()->for_($lecture)->inState(JobState::Published)->create([
        'published_at' => now(),
        'structure_json' => ['title_ar' => 'عنوان الدرس', 'axes' => array_map(static fn (string $n): array => ['name' => $n], $axes)],
    ]);

    $quiz = Quiz::acrossTenants()->create([
        'tenant_id' => $tenant->id,
        'summary_job_id' => $job->id,
        'token' => Quiz::newToken(),
        'generated_at' => now(),
    ]);

    // سؤالان على المحور الأوّل، وواحدٌ على الثاني. والصحيحُ الخيارُ الأوّل.
    foreach ([0, 0, 1] as $position => $axis) {
        $quiz->questions()->create([
            'position' => $position + 1,
            'kind' => 'single',
            'prompt' => 'السؤال '.($position + 1),
            'options' => [
                ['text' => 'الصحيح', 'evidence_item_id' => null],
                ['text' => 'خطأٌ أوّل', 'evidence_item_id' => null],
                ['text' => 'خطأٌ ثانٍ', 'evidence_item_id' => null],
            ],
            'correct_index' => 0,
            'explanation' => 'شرح.',
            'axis_index' => $axis,
        ]);
    }

    return $quiz;
}

/**
 * @param  list<int|null>  $choices  خيارُ كلّ سؤال، و`null` بلا جواب.
 */
function reportAttempt(Quiz $quiz, string $name, string $ip, array $choices, ?int $seconds = 60, int $number = 1): QuizAttempt
{
    $started = now()->subMinutes(10);
    $attempt = QuizAttempt::acrossTenants()->create([
        'tenant_id' => $quiz->tenant_id,
        'quiz_id' => $quiz->id,
        'token' => Str::random(40),
        'participant_name' => $name,
        'name_key' => $name,
        'ip' => $ip,
        'attempt_number' => $number,
        'total' => 3,
        'started_at' => $started,
        'finished_at' => $seconds === null ? null : $started->copy()->addSeconds($seconds),
        'duration_seconds' => $seconds,
        'score' => $seconds === null ? null : count(array_filter($choices, static fn ($c): bool => $c === 0)),
    ]);

    foreach ($quiz->questions()->get() as $i => $question) {
        if ($choices[$i] === null) {
            continue;
        }

        QuizAnswer::query()->create([
            'quiz_attempt_id' => $attempt->id,
            'quiz_question_id' => $question->id,
            'option_index' => $choices[$i],
            'is_correct' => $choices[$i] === 0,
            'answered_at' => $started->copy()->addSeconds(10 * ($i + 1)),
        ]);
    }

    return $attempt;
}

it('يحسب أوّل محاولةٍ منتهية لكلّ اسمٍ من جهاز، ويعدّ غير المنتهية في الإكمال وحده', function (): void {
    reportAttempt($this->quiz, 'أ', '203.0.113.1', [0, 0, 0], 30);           // ١٠٠٪
    reportAttempt($this->quiz, 'أ', '203.0.113.1', [0, 0, 0], 20, 2);        // إعادة: لا تُحسب
    reportAttempt($this->quiz, 'ب', '203.0.113.2', [1, 0, null], 90);        // ٣٣٪
    reportAttempt($this->quiz, 'ج', '203.0.113.3', [0, 2, 1], 60);           // ٣٣٪
    reportAttempt($this->quiz, 'د', '203.0.113.4', [0, null, null], null);  // لم يُنهِ

    $report = app(BuildQuizReport::class)->forQuiz($this->quiz->refresh());

    expect($report['participants'])->toBe(3)
        ->and($report['started'])->toBe(5)
        ->and($report['finished'])->toBe(4)
        ->and($report['completion'])->toEqual(80)
        // (100 + 33.3 + 33.3) / 3 = 55.6
        ->and($report['average'])->toBe(56)
        ->and($report['median'])->toBe(33)
        // وسيطُ ٣٠ و٦٠ و٩٠ — عددٌ فرديّ.
        ->and($report['duration_median'])->toBe(60)
        ->and(array_column($report['distribution'], 'count'))->toBe([0, 2, 0, 1]);

    [$first, $second, $third] = $report['questions'];

    // السؤال الأوّل: أصابه اثنان من ثلاثة، والخطأُ الأكثر الخيارُ الثاني.
    expect($first['correct_rate'])->toBe(67)
        ->and($first['counts'])->toBe([2, 1, 0])
        ->and($first['top_wrong'])->toBe(['index' => 1, 'rate' => 33])
        // السؤال الثاني: أصابه اثنان، وأخطأ واحدٌ بالثالث.
        ->and($second['correct_rate'])->toBe(67)
        // السؤال الثالث: أصابه واحد، وتركه واحد — فالمقامُ المحسوبون كلُّهم.
        ->and($third['correct_rate'])->toBe(33);

    // المحور الأوّل متوسّطُ سؤاليه (٦٧)، والثاني (٣٣) — والأضعفُ أوّلاً.
    expect($report['axes'])->toBe([
        ['index' => 1, 'name' => 'المحور الثاني', 'rate' => 33, 'questions' => 1],
        ['index' => 0, 'name' => 'المحور الأوّل', 'rate' => 67, 'questions' => 2],
    ]);
});

it('يحسب الوسيط في عددٍ زوجيّ', function (): void {
    expect(BuildQuizReport::median([10, 40, 20, 30]))->toBe(25.0)
        ->and(BuildQuizReport::median([]))->toBeNull();
});

it('يعرض التقرير العامّ واختبارَ الجهة وحدها', function (): void {
    reportAttempt($this->quiz, 'أ', '203.0.113.1', [0, 0, 0], 30);

    $other = Tenant::factory()->create(['slug' => 'tenant-b']);
    reportAttempt(reportQuiz($other, ['المحور الأوّل']), 'ب', '203.0.113.9', [1, 1, 1], 30);

    $this->actingAs($this->owner)
        ->get(route('quizzes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quizzes/Index')
            ->where('report.participants', 1)
            ->where('report.average', 100)
            ->has('report.quizzes', 1)
            ->has('report.daily', 30));
});

it('يعرض تقرير الاختبار بجدول المشاركين وإعاداتهم', function (): void {
    reportAttempt($this->quiz, 'أ', '203.0.113.1', [0, 0, 0], 30);
    reportAttempt($this->quiz, 'أ', '203.0.113.1', [0, 1, 0], 20, 2);

    $this->actingAs($this->owner)
        ->get(route('quizzes.show', $this->quiz))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quizzes/Show')
            ->has('participants.data', 2)
            ->where('participants.data.0.counted', false)
            ->where('participants.data.1.counted', true)
            ->where('participants.data.1.ip', '203.0.113.1'));
});

it('يفتح إجابات محاولةٍ واحدة', function (): void {
    $attempt = reportAttempt($this->quiz, 'أ', '203.0.113.1', [1, 0, null], 30);

    $this->actingAs($this->owner)
        ->getJson(route('quizzes.attempt', [$this->quiz, $attempt->id]))
        ->assertOk()
        ->assertJsonPath('answers.0.chosen', 'خطأٌ أوّل')
        ->assertJsonPath('answers.0.correct', 'الصحيح')
        ->assertJsonPath('answers.0.is_correct', false)
        ->assertJsonPath('answers.2.chosen', null);
});

it('ينزّل CSV بعمودٍ لكلّ سؤال، وبعلامة BOM', function (): void {
    reportAttempt($this->quiz, 'اسم المشارك', '203.0.113.1', [0, 1, null], 30);

    $csv = $this->actingAs($this->owner)
        ->get(route('quizzes.export', $this->quiz))
        ->assertOk()
        ->streamedContent();

    $lines = array_values(array_filter(explode("\n", $csv)));

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and(str_getcsv($lines[0]))->toHaveCount(13)
        ->and($lines[1])->toContain('اسم المشارك')
        ->and($lines[1])->toContain('✓ الصحيح')
        ->and($lines[1])->toContain('✗ خطأٌ أوّل');
});

it('لا يفتح تقريرَ جهةٍ لغيرها', function (): void {
    $intruder = User::factory()->owner()->create(['tenant_id' => Tenant::factory()->create(['slug' => 'tenant-b'])->id]);

    $this->actingAs($intruder)->get(route('quizzes.show', $this->quiz))->assertNotFound();
    $this->actingAs($intruder)->get(route('quizzes.export', $this->quiz))->assertNotFound();
});

it('يعرض التقرير للقارئ', function (): void {
    $viewer = User::factory()->viewer()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($viewer)->get(route('quizzes.show', $this->quiz))->assertOk();
});

it('يعرض حالةً فارغة لجهةٍ بلا اختبار', function (): void {
    $empty = User::factory()->owner()->create(['tenant_id' => Tenant::factory()->create(['slug' => 'tenant-c'])->id]);

    $this->actingAs($empty)
        ->get(route('quizzes.index'))
        ->assertInertia(fn ($page) => $page->has('report.quizzes', 0));
});
