<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\DB;

/**
 * يُنهي المحاولة ويصحّحها — T-195. **التصحيحُ هنا وحده**، على الخادم.
 *
 * ويقبل أجوبةً مع الإنهاء: نموذجُ ما بلا JavaScript يرسلها كلَّها مرّةً. وفي
 * وضع «بعد كلّ سؤال» لا يُكتب فوق جوابٍ رأى صاحبُه حكمَه.
 */
final class FinishQuizAttempt
{
    /** درجةُ التهنئة في بطاقة النتيجة — قرار @HasanSiwi، ٤ أكتوبر ٢٠٢٦. */
    public const HONOUR_PERCENT = 80;

    /**
     * @param  array<int|string, mixed>  $answers  رقمُ السؤال ← رقمُ الخيار.
     */
    public function handle(QuizAttempt $attempt, array $answers = []): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers): QuizAttempt {
            $attempt = QuizAttempt::acrossTenants()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->isFinished()) {
                return $attempt;
            }

            $quiz = $attempt->quiz()->withoutGlobalScopes()->firstOrFail();
            $questions = QuizQuestion::query()->where('quiz_id', $quiz->id)->get()->keyBy('id');
            $locked = $quiz->revealsImmediately();

            foreach ($answers as $questionId => $option) {
                $question = $questions->get((int) $questionId);
                $option = filter_var($option, FILTER_VALIDATE_INT);

                if ($question === null || $option === false || $option < 0 || $option >= count($question->options)) {
                    continue;
                }

                $where = ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id];

                if ($locked && QuizAnswer::query()->where($where)->exists()) {
                    continue;
                }

                QuizAnswer::query()->updateOrCreate($where, [
                    'option_index' => $option,
                    'is_correct' => $option === $question->correct_index,
                    'answered_at' => now(),
                ]);
            }

            $finishedAt = now();

            $attempt->forceFill([
                'score' => QuizAnswer::query()->where('quiz_attempt_id', $attempt->id)->where('is_correct', true)->count(),
                'total' => $questions->count(),
                'finished_at' => $finishedAt,
                'duration_seconds' => max(0, (int) $attempt->started_at->diffInSeconds($finishedAt, absolute: true)),
            ])->save();

            return $attempt;
        });
    }
}
