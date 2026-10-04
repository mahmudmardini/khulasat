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
 * والقاعدةُ الحاكمة: المحاولاتُ بلا أصحاب (قرار @HasanSiwi)، فكلُّ محاولةٍ
 * منتهية تُحسب، ومن لم يُنهِ يُنقص نسبةَ الإكمال وحدها. **ولا قائمةَ أشخاص**.
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
function reportAttempt(Quiz $quiz, array $choices, ?int $seconds = 60): QuizAttempt
{
    $started = now()->subMinutes(10);
    $attempt = QuizAttempt::acrossTenants()->create([
        'tenant_id' => $quiz->tenant_id,
        'quiz_id' => $quiz->id,
        'token' => Str::random(40),
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

it('يحسب كلَّ محاولةٍ منتهية، ويعدّ غير المنتهية في الإكمال وحده', function (): void {
    reportAttempt($this->quiz, [0, 0, 0], 30);          // ١٠٠٪
    reportAttempt($this->quiz, [0, 0, 0], 20);          // إعادةٌ لا تُعرف: تُحسب كغيرها
    reportAttempt($this->quiz, [1, 0, null], 90);       // ٣٣٪
    reportAttempt($this->quiz, [0, 2, 1], 60);          // ٣٣٪
    reportAttempt($this->quiz, [0, null, null], null);  // لم يُنهِ

    $report = app(BuildQuizReport::class)->forQuiz($this->quiz->refresh());

    expect($report)->not->toHaveKey('participants')
        ->and($report['started'])->toBe(5)
        ->and($report['finished'])->toBe(4)
        ->and($report['completion'])->toEqual(80)
        // (100 + 100 + 33.3 + 33.3) / 4
        ->and($report['average'])->toBe(67)
        // وسيطُ عددٍ زوجيّ: (33.3 + 100) / 2
        ->and($report['median'])->toBe(67)
        // ٢٠ و٣٠ و٦٠ و٩٠ — وسيطُها ٤٥.
        ->and($report['duration_median'])->toBe(45)
        ->and(array_column($report['distribution'], 'count'))->toBe([0, 2, 0, 2]);

    [$first, $second, $third] = $report['questions'];

    expect($first['correct_rate'])->toBe(75)
        ->and($first['counts'])->toBe([3, 1, 0])
        ->and($first['top_wrong'])->toBe(['index' => 1, 'rate' => 25])
        ->and($second['correct_rate'])->toBe(75)
        // تركه واحد — فالمقامُ المحاولاتُ المنتهية كلُّها.
        ->and($third['correct_rate'])->toBe(50);

    expect($report['axes'])->toBe([
        ['index' => 1, 'name' => 'المحور الثاني', 'rate' => 50, 'questions' => 1],
        ['index' => 0, 'name' => 'المحور الأوّل', 'rate' => 75, 'questions' => 2],
    ]);
});

it('يحسب الوسيط في عددٍ زوجيّ', function (): void {
    expect(BuildQuizReport::median([10, 40, 20, 30]))->toBe(25.0)
        ->and(BuildQuizReport::median([]))->toBeNull();
});

it('يعرض التقرير العامّ واختبارَ الجهة وحدها', function (): void {
    reportAttempt($this->quiz, [0, 0, 0], 30);

    $other = Tenant::factory()->create(['slug' => 'tenant-b']);
    reportAttempt(reportQuiz($other, ['المحور الأوّل']), [1, 1, 1], 30);

    $this->actingAs($this->owner)
        ->get(route('quizzes.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quizzes/Index')
            ->where('report.attempts', 1)
            ->where('report.average', 100)
            ->has('report.quizzes', 1)
            ->has('report.daily', 30));
});

it('يعرض تقرير الاختبار أرقاماً بلا قائمة أشخاص', function (): void {
    reportAttempt($this->quiz, [0, 0, 0], 30);

    $this->actingAs($this->owner)
        ->get(route('quizzes.show', $this->quiz))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quizzes/Show')
            ->missing('participants')
            ->where('report.finished', 1)
            ->has('report.questions', 3));
});

it('ينزّل CSV بصفٍّ لكلّ سؤال لا لكلّ مشارك، وبعلامة BOM', function (): void {
    reportAttempt($this->quiz, [0, 1, null], 30);
    reportAttempt($this->quiz, [1, 1, 0], 40);

    $csv = $this->actingAs($this->owner)
        ->get(route('quizzes.export', $this->quiz))
        ->assertOk()
        ->streamedContent();

    $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", substr($csv, 3)))));

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
        ->and($rows)->toHaveCount(4)
        ->and($rows[0])->toHaveCount(11)
        ->and($rows[1][1])->toBe('السؤال 1')
        ->and($rows[1][3])->toBe('50')
        ->and($rows[1][8])->toBe('الصحيح (1)')
        ->and($rows[1][9])->toBe('خطأٌ أوّل (1)');
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
