<?php

declare(strict_types=1);

namespace App\Actions\Quiz;

use App\Models\EvidenceItem;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\Render\RenderedEvidence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * أرقامُ تقارير الاختبارات — T-201.
 *
 * ★ **المحاولةُ المحسوبة هي الأولى المنتهية لكلّ (اسم + IP)** — القرار ٤ في
 * T-195. وعليها تُبنى المتوسّطات والتوزيع ونِسَبُ الأسئلة والمحاور كلُّها.
 * فمن أعاد الاختبار حتى أصاب لا يرفع متوسّط فهم الدرس بإعادته. وما بعدها
 * يُعرض في جدول المشاركين وحده.
 *
 * **والحسابُ في استعلاماتٍ مجمَّعة** لا بتحميل المحاولات: اختبارٌ يُشارَك في
 * مجموعة واتساب كبيرة يبلغ آلاف المحاولات.
 */
final class BuildQuizReport
{
    /** يُحسب «أصعبُ الاختبارات» ممّا شارك فيه هذا العددُ فأكثر — فاختبارٌ بمشاركَين لا يُحكم عليه. */
    public const MIN_FOR_RANKING = 5;

    /** @return array<string, mixed> */
    public function forQuiz(Quiz $quiz): array
    {
        $counted = $this->countedIds($quiz->id);
        $questions = $quiz->questions()->get();
        $axes = array_values((array) ($quiz->summaryJob?->structure_json['axes'] ?? []));

        $started = QuizAttempt::query()->where('quiz_id', $quiz->id)->count();
        $finished = QuizAttempt::query()->where('quiz_id', $quiz->id)->whereNotNull('finished_at')->count();

        $rows = QuizAttempt::query()
            ->whereIn('id', $counted)
            ->get(['id', 'score', 'total', 'duration_seconds']);

        $percents = $rows->map(static fn (QuizAttempt $a): float => $a->total === 0 ? 0.0 : 100 * (int) $a->score / $a->total)->all();
        $durations = $rows->pluck('duration_seconds')->filter(static fn ($s): bool => $s !== null)->map(static fn ($s): int => (int) $s)->all();

        $distribution = array_fill(0, $questions->count() + 1, 0);
        foreach ($rows as $row) {
            $score = min((int) $row->score, $questions->count());
            $distribution[$score]++;
        }

        $perQuestion = $this->questions($questions->all(), $counted, count($counted));

        return [
            'opens' => (int) $quiz->opens_count,
            'started' => $started,
            'finished' => $finished,
            'completion' => $started === 0 ? null : round(100 * $finished / $started),
            'participants' => count($counted),
            'average' => self::round(self::mean($percents)),
            'median' => self::round(self::median($percents)),
            'duration_median' => self::median($durations) === null ? null : (int) round((float) self::median($durations)),
            'duration_average' => self::mean($durations) === null ? null : (int) round((float) self::mean($durations)),
            'distribution' => array_map(static fn (int $score, int $count): array => ['score' => $score, 'count' => $count], array_keys($distribution), $distribution),
            'questions' => $perQuestion,
            'axes' => $this->axes($perQuestion, $axes),
            'daily' => $this->daily(QuizAttempt::query()->where('quiz_id', $quiz->id)),
        ];
    }

    /**
     * التقرير العامّ على اختبارات الجهة — **والجهةُ من نطاق النموذج**، فلا
     * يدخل اختبارُ جهةٍ أرقامَ غيرها.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $quizzes = Quiz::query()
            ->where('state', Quiz::STATE_READY)
            ->with(['summaryJob.lecture'])
            ->get();

        $list = [];
        $allCounted = [];

        foreach ($quizzes as $quiz) {
            $counted = $this->countedIds($quiz->id);
            $allCounted = [...$allCounted, ...$counted];

            $rows = QuizAttempt::query()->whereIn('id', $counted)->get(['score', 'total', 'duration_seconds']);
            $percents = $rows->map(static fn (QuizAttempt $a): float => $a->total === 0 ? 0.0 : 100 * (int) $a->score / $a->total)->all();
            $durations = $rows->pluck('duration_seconds')->filter(static fn ($s): bool => $s !== null)->map(static fn ($s): int => (int) $s)->all();

            $last = QuizAttempt::query()->where('quiz_id', $quiz->id)->max('started_at');

            $list[] = [
                'id' => $quiz->id,
                'job_id' => $quiz->summary_job_id,
                'title' => $quiz->summaryJob?->structure_json['title_ar'] ?? $quiz->summaryJob?->lecture?->title_ar,
                'status' => $quiz->status,
                'participants' => count($counted),
                'average' => self::round(self::mean($percents)),
                'duration_median' => self::median($durations) === null ? null : (int) round((float) self::median($durations)),
                'last_attempt' => $last === null ? null : Carbon::parse($last)->toIso8601String(),
            ];
        }

        // **الأحدثُ محاولةً أوّلاً**، وما لم يُشارَك فيه آخراً.
        usort($list, static fn (array $a, array $b): int => strcmp((string) $b['last_attempt'], (string) $a['last_attempt']));

        $ids = $quizzes->pluck('id')->all();
        $started = QuizAttempt::query()->whereIn('quiz_id', $ids)->count();
        $finished = QuizAttempt::query()->whereIn('quiz_id', $ids)->whereNotNull('finished_at')->count();

        $all = QuizAttempt::query()->whereIn('id', $allCounted)->get(['score', 'total']);
        $percents = $all->map(static fn (QuizAttempt $a): float => $a->total === 0 ? 0.0 : 100 * (int) $a->score / $a->total)->all();

        $ranked = array_values(array_filter($list, static fn (array $q): bool => $q['participants'] >= self::MIN_FOR_RANKING));
        usort($ranked, static fn (array $a, array $b): int => $a['average'] <=> $b['average']);

        return [
            'open' => $quizzes->where('status', Quiz::STATUS_OPEN)->count(),
            'participants' => count($allCounted),
            'average' => self::round(self::mean($percents)),
            'completion' => $started === 0 ? null : round(100 * $finished / $started),
            'daily' => $this->daily(QuizAttempt::query()->whereIn('quiz_id', $ids)),
            'quizzes' => $list,
            'hardest' => array_slice($ranked, 0, 3),
        ];
    }

    /**
     * معرّفاتُ المحاولات المحسوبة: **أوّلُ منتهيةٍ لكلّ (اسم + IP)**.
     *
     * @return list<int>
     */
    public function countedIds(int $quizId): array
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->whereNotNull('finished_at')
            ->groupBy('name_key', 'ip')
            ->selectRaw('MIN(id) as id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * لكلّ سؤال: نسبةُ الصواب، وتوزيعُ الخيارات، وأكثرُ خيارٍ خاطئٍ اختير،
     * ووسيطُ وقت الجواب. **والمقامُ المحسوبون كلُّهم**: من ترك سؤالاً بلا
     * جوابٍ لم يُصبه.
     *
     * @param  list<QuizQuestion>  $questions
     * @param  list<int>  $counted
     * @return list<array<string, mixed>>
     */
    private function questions(array $questions, array $counted, int $participants): array
    {
        $choices = QuizAnswer::query()
            ->whereIn('quiz_attempt_id', $counted)
            ->groupBy('quiz_question_id', 'option_index')
            ->selectRaw('quiz_question_id, option_index, COUNT(*) as total')
            ->get()
            ->groupBy('quiz_question_id');

        $seconds = $this->answerSeconds($counted);
        $evidence = self::evidenceExcerpts($questions);

        return array_map(function (QuizQuestion $q) use ($choices, $participants, $seconds, $evidence): array {
            $counts = array_fill(0, count($q->options), 0);

            foreach ($choices->get($q->id, collect()) as $row) {
                if (isset($counts[(int) $row->option_index])) {
                    $counts[(int) $row->option_index] = (int) $row->total;
                }
            }

            $wrong = $counts;
            unset($wrong[$q->correct_index]);
            arsort($wrong);
            $topWrong = array_key_first($wrong);

            return [
                'id' => $q->id,
                'position' => $q->position,
                'prompt' => $q->prompt,
                'axis_index' => $q->axis_index,
                'correct_index' => $q->correct_index,
                'options' => array_map(static fn (array $o): string => (string) ($o['text'] ?? $evidence[$o['evidence_item_id']] ?? ''), $q->options),
                'counts' => $counts,
                'correct_rate' => $participants === 0 ? null : (int) round(100 * $counts[$q->correct_index] / $participants),
                'top_wrong' => $topWrong === null || ($wrong[$topWrong] ?? 0) === 0 ? null : [
                    'index' => $topWrong,
                    'rate' => (int) round(100 * $wrong[$topWrong] / max(1, $participants)),
                ],
                'median_seconds' => self::median($seconds[$q->id] ?? []) === null ? null : (int) round((float) self::median($seconds[$q->id])),
            ];
        }, $questions);
    }

    /**
     * وقتُ كلّ جواب: من الجواب الذي قبله في المحاولة، أو من بدئها لأوّلها.
     * **تقريبٌ لا قياس**: من غيّر جوابه في وضع «في الآخر» يُحسب له وقتُ آخرِ
     * تغيير. ويُقرأ رقمٌ واحدٌ لكلّ جواب، لا صفٌّ كامل.
     *
     * @param  list<int>  $counted
     * @return array<int, list<int>>
     */
    private function answerSeconds(array $counted): array
    {
        if ($counted === []) {
            return [];
        }

        $rows = DB::table('quiz_answers')
            ->join('quiz_attempts', 'quiz_attempts.id', '=', 'quiz_answers.quiz_attempt_id')
            ->whereIn('quiz_answers.quiz_attempt_id', $counted)
            ->orderBy('quiz_answers.quiz_attempt_id')
            ->orderBy('quiz_answers.answered_at')
            ->get(['quiz_answers.quiz_attempt_id', 'quiz_answers.quiz_question_id', 'quiz_answers.answered_at', 'quiz_attempts.started_at']);

        $seconds = [];
        $previous = [];

        foreach ($rows as $row) {
            $from = Carbon::parse($previous[$row->quiz_attempt_id] ?? $row->started_at);
            $at = Carbon::parse($row->answered_at);
            $seconds[(int) $row->quiz_question_id][] = max(0, (int) $from->diffInSeconds($at, absolute: true));
            $previous[$row->quiz_attempt_id] = $row->answered_at;
        }

        return $seconds;
    }

    /**
     * كلُّ محورٍ بنسبة صواب أسئلته، **من الأضعف** — «ما يحتاج مراجعة».
     *
     * @param  list<array<string, mixed>>  $questions
     * @param  list<mixed>  $axes
     * @return list<array{index: int, name: string, rate: int|null, questions: int}>
     */
    private function axes(array $questions, array $axes): array
    {
        $groups = [];

        foreach ($questions as $q) {
            if ($q['axis_index'] === null) {
                continue;
            }

            $groups[$q['axis_index']][] = $q['correct_rate'];
        }

        $rows = [];

        foreach ($groups as $index => $rates) {
            $known = array_values(array_filter($rates, static fn ($r): bool => $r !== null));

            $rows[] = [
                'index' => (int) $index,
                'name' => (string) ($axes[$index]['name'] ?? ''),
                'rate' => $known === [] ? null : (int) round(array_sum($known) / count($known)),
                'questions' => count($rates),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($a['rate'] ?? 101) <=> ($b['rate'] ?? 101));

        return $rows;
    }

    /**
     * المحاولاتُ المنتهية في اليوم، آخرَ ثلاثين يوماً — وأيّامٌ بلا محاولةٍ صفرٌ ظاهر.
     *
     * @param  Builder<QuizAttempt>  $query
     * @return list<array{date: string, count: int}>
     */
    private function daily(Builder $query): array
    {
        $from = now()->subDays(29)->startOfDay();

        $counts = (clone $query)
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', $from)
            ->get(['finished_at'])
            ->countBy(static fn (QuizAttempt $a): string => $a->finished_at->toDateString());

        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $date = $from->copy()->addDays($i)->toDateString();
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }

    /**
     * خيارُ الشاهد في التقرير بأوّل لفظه — **من مصدره** كما في صفحة المشارك.
     *
     * @param  list<QuizQuestion>  $questions
     * @return array<int, string>
     */
    public static function evidenceExcerpts(array $questions): array
    {
        $ids = [];
        foreach ($questions as $q) {
            foreach ($q->options as $option) {
                if ($option['evidence_item_id'] !== null) {
                    $ids[] = (int) $option['evidence_item_id'];
                }
            }
        }

        return $ids === [] ? [] : EvidenceItem::acrossTenants()
            ->whereIn('id', array_unique($ids))
            ->get()
            ->mapWithKeys(static fn (EvidenceItem $item): array => [(int) $item->id => RenderedEvidence::fromItem($item)->excerpt(10)])
            ->all();
    }

    /** @param list<int|float> $values */
    public static function mean(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /** @param list<int|float> $values */
    public static function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    private static function round(?float $value): ?int
    {
        return $value === null ? null : (int) round($value);
    }
}
