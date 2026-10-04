<?php

declare(strict_types=1);

use App\Support\Quiz\QuizGuard;

/*
 * حارسُ أسئلة الاختبار — T-195. **حتميٌّ بلا نموذج** (§2، القاعدة الثالثة).
 *
 * والقاعدةُ الحاكمة: سؤالٌ يخالف يُحذف وحده. ولكلّ حالةٍ في قائمة المهمّة
 * اختبارٌ هنا: صحيحان أو لا صحيح، وخيارٌ مكرّر، ومحورٌ مجهول، وشاهدٌ غير
 * محسوم، ولفظٌ منسوبٌ في نصٍّ حرّ، وطولٌ زائد.
 */

function quizGuard(): QuizGuard
{
    return QuizGuard::for(axisCount: 2, evidenceIds: ['e1' => 11, 'e2' => 12], settledTexts: [], seed: 7);
}

/** @param array<string, mixed> $overrides */
function quizQuestion(array $overrides = []): array
{
    return array_replace([
        'kind' => 'single',
        'level' => 'recall',
        'prompt' => 'ما الفكرة الرئيسة في المحور الأوّل؟',
        'options' => [
            ['text' => 'الجواب الصحيح', 'evidence_id' => null, 'correct' => true],
            ['text' => 'خيارٌ ثانٍ', 'evidence_id' => null, 'correct' => false],
            ['text' => 'خيارٌ ثالث', 'evidence_id' => null, 'correct' => false],
        ],
        'explanation' => 'شرحٌ قصير من الدرس.',
        'axis_id' => 'axis-1',
        'evidence_ids' => [],
    ], $overrides);
}

it('يُبقي السؤال السليم ويشير إلى الصحيح بعد الخلط', function (): void {
    $kept = quizGuard()->guard(['questions' => [quizQuestion()]]);

    expect($kept)->toHaveCount(1)
        ->and($kept[0]->options[$kept[0]->correctIndex]['text'])->toBe('الجواب الصحيح')
        ->and($kept[0]->axisIndex)->toBe(0);
});

it('يحذف سؤالاً بجوابين صحيحين أو بلا صحيح', function (array $flags): void {
    $options = array_map(
        static fn (string $text, bool $correct): array => ['text' => $text, 'evidence_id' => null, 'correct' => $correct],
        ['أوّل', 'ثانٍ', 'ثالث'],
        $flags,
    );

    $guard = quizGuard();

    expect($guard->guard(['questions' => [quizQuestion(['options' => $options])]]))->toBe([])
        ->and($guard->dropped())->toHaveCount(1);
})->with([
    'صحيحان' => [[true, true, false]],
    'لا صحيح' => [[false, false, false]],
]);

it('يحذف خيارين متطابقين بعد التطبيع', function (): void {
    $options = quizQuestion()['options'];
    $options[1]['text'] = 'الجوابُ الصحيحُ';

    expect(quizGuard()->guard(['questions' => [quizQuestion(['options' => $options])]]))->toBe([]);
});

it('يحذف سؤالاً عن محورٍ ليس في البنية', function (): void {
    expect(quizGuard()->guard(['questions' => [quizQuestion(['axis_id' => 'axis-3'])]]))->toBe([]);
});

it('يحذف شاهداً ليس من شواهد الدرس المحسومة', function (): void {
    $question = quizQuestion([
        'kind' => 'evidence',
        'options' => [
            ['text' => null, 'evidence_id' => 'e1', 'correct' => true],
            ['text' => null, 'evidence_id' => 'e9', 'correct' => false],
        ],
    ]);

    expect(quizGuard()->guard(['questions' => [$question]]))->toBe([])
        ->and(quizGuard()->guard(['questions' => [quizQuestion(['evidence_ids' => ['e9']])]]))->toBe([]);
});

it('يجعل خيار الشاهد معرّفاً لا نصّاً — فلفظُه لا يأتي من النموذج', function (): void {
    $question = quizQuestion([
        'kind' => 'evidence',
        'options' => [
            ['text' => 'لفظٌ كتبه النموذج', 'evidence_id' => 'e2', 'correct' => true],
            ['text' => null, 'evidence_id' => 'e1', 'correct' => false],
        ],
    ]);

    $kept = quizGuard()->guard(['questions' => [$question]]);

    expect($kept)->toHaveCount(1)
        ->and(array_column($kept[0]->options, 'text'))->toBe([null, null])
        ->and($kept[0]->options[$kept[0]->correctIndex]['evidence_item_id'])->toBe(12);
});

it('يحذف سؤالاً ينسب لفظاً لم يُتحقَّق منه', function (string $field): void {
    $quote = 'قال رسول الله ﷺ: «من صلّى الفجر في جماعةٍ فله أجرُ حجّةٍ تامّة»';

    $question = match ($field) {
        'prompt' => quizQuestion(['prompt' => $quote.'. فما معناه؟']),
        'explanation' => quizQuestion(['explanation' => $quote.'.']),
        default => quizQuestion(['options' => [
            ['text' => $quote, 'evidence_id' => null, 'correct' => true],
            ['text' => 'خيارٌ ثانٍ', 'evidence_id' => null, 'correct' => false],
            ['text' => 'خيارٌ ثالث', 'evidence_id' => null, 'correct' => false],
        ]]),
    };

    expect(quizGuard()->guard(['questions' => [$question]]))->toBe([]);
})->with(['prompt', 'explanation', 'option']);

it('يحذف نصّاً تجاوز حدّه', function (): void {
    $long = trim(str_repeat('كلمة ', QuizGuard::MAX_PROMPT_WORDS + 1));

    expect(quizGuard()->guard(['questions' => [quizQuestion(['prompt' => $long])]]))->toBe([]);
});

it('يحذف «كلّ ما سبق»', function (): void {
    $options = quizQuestion()['options'];
    $options[2]['text'] = 'كلّ ما سبق';

    expect(quizGuard()->guard(['questions' => [quizQuestion(['options' => $options])]]))->toBe([]);
});

it('يُبقي الصواب والخطأ بترتيبهما الثابت أيّاً كان ترتيب النموذج', function (): void {
    $question = quizQuestion([
        'kind' => 'true_false',
        'options' => [
            ['text' => 'خطأ', 'evidence_id' => null, 'correct' => true],
            ['text' => 'صواب', 'evidence_id' => null, 'correct' => false],
        ],
    ]);

    $kept = quizGuard()->guard(['questions' => [$question]]);

    expect(array_column($kept[0]->options, 'text'))->toBe(['صواب', 'خطأ'])
        ->and($kept[0]->correctIndex)->toBe(1);
});

it('يخلط حتمياً، ولا يستقرّ الصحيح في موضعٍ واحد', function (): void {
    $questions = array_fill(0, 20, quizQuestion());
    $decoded = ['questions' => array_slice($questions, 0, 10)];

    expect(quizGuard()->guard($decoded))->toEqual(quizGuard()->guard($decoded));

    // عشرون سؤالاً عبر بذرتين — والصحيحُ أوّلاً في كلّها كما تأمر التعليمات.
    $positions = [];
    foreach ([7, 8] as $seed) {
        $guard = QuizGuard::for(2, [], [], $seed);
        foreach ($guard->guard($decoded) as $draft) {
            $positions[] = $draft->correctIndex;
        }
    }

    expect($positions)->toHaveCount(20)
        ->and(count(array_unique($positions)))->toBeGreaterThan(1);
});

it('لا يُبقي أكثر من عشرة أسئلة', function (): void {
    expect(quizGuard()->guard(['questions' => array_fill(0, 12, quizQuestion())]))->toHaveCount(QuizGuard::MAX_QUESTIONS);
});

it('يفحص تعديل اللوحة بالحارس نفسه', function (): void {
    $guard = quizGuard();

    expect($guard->editProblem('single', 'سؤالٌ معدَّل؟', ['أ', 'ب', 'ج'], 'شرحٌ معدَّل.'))->toBeNull()
        ->and($guard->editProblem('single', 'سؤال؟', ['أ', 'أ', 'ج'], 'شرح.'))->not->toBeNull()
        ->and($guard->editProblem('single', 'قال رسول الله ﷺ: «من صلّى الفجر في جماعةٍ فله أجرُ حجّةٍ تامّة»', ['أ', 'ب', 'ج'], 'شرح.'))->not->toBeNull()
        ->and($guard->editProblem('true_false', 'عبارة.', ['نعم', 'لا'], 'شرح.'))->not->toBeNull()
        ->and($guard->editProblem('true_false', 'عبارة.', ['صواب', 'خطأ'], 'شرح.'))->toBeNull();
});
