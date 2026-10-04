<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Quiz\BuildQuizReport;
use App\Http\Controllers\Public\QuizController as PublicQuiz;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقاريرُ الاختبارات في لوحة الجهة — T-201.
 *
 * **ويراها كلُّ عضوٍ في الجهة**: قراءةٌ لا تغيير. والاختبارُ بنطاق الجهة في
 * ربط المسار، فتقريرُ جهةٍ ٤٠٤ لغيرها.
 */
class QuizReportController extends Controller
{
    private const PER_PAGE = 25;

    /** عمودُ الترتيب في جدول المشاركين ← عمودُه في الجدول. */
    private const SORTS = [
        'date' => 'started_at',
        'name' => 'name_key',
        'score' => 'score',
        'duration' => 'duration_seconds',
        'attempt' => 'attempt_number',
    ];

    public function index(BuildQuizReport $report): InertiaResponse
    {
        return Inertia::render('Quizzes/Index', ['report' => $report->overview()]);
    }

    public function show(Request $request, Quiz $quiz, BuildQuizReport $report): InertiaResponse
    {
        $quiz->loadMissing('summaryJob.lecture');
        $counted = $report->countedIds($quiz->id);

        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'date';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) $request->query('search', ''));

        $participants = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->when($search !== '', static fn ($q) => $q->where('name_key', 'like', '%'.PublicQuiz::nameKey($search).'%'))
            ->orderBy(self::SORTS[$sort], $dir)
            ->orderBy('id', $dir)
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static fn (QuizAttempt $a): array => [
                'id' => $a->id,
                'name' => $a->participant_name,
                'ip' => $a->ip,
                'score' => $a->score,
                'total' => $a->total,
                'percent' => $a->isFinished() ? $a->percent() : null,
                'duration' => $a->duration_seconds,
                'started_at' => $a->started_at->toIso8601String(),
                'finished' => $a->isFinished(),
                'attempt_number' => $a->attempt_number,
                'counted' => in_array($a->id, $counted, true),
            ]);

        return Inertia::render('Quizzes/Show', [
            'quiz' => [
                'id' => $quiz->id,
                'job_id' => $quiz->summary_job_id,
                'title' => $quiz->summaryJob?->structure_json['title_ar'] ?? $quiz->summaryJob?->lecture?->title_ar,
                'status' => $quiz->status,
                'url' => $quiz->publicUrl(),
            ],
            'report' => $report->forQuiz($quiz),
            'participants' => $participants,
            'filters' => ['search' => $search, 'sort' => $sort, 'dir' => $dir],
        ]);
    }

    /** إجاباتُ محاولةٍ واحدة — تُفتح من صفّها في الجدول. */
    public function attempt(Quiz $quiz, int $attempt): JsonResponse
    {
        $row = QuizAttempt::query()->where('quiz_id', $quiz->id)->whereKey($attempt)->firstOrFail();
        $questions = $quiz->questions()->get();
        $answers = $row->answers()->get()->keyBy('quiz_question_id');
        $evidence = BuildQuizReport::evidenceExcerpts($questions->all());

        return response()->json([
            'name' => $row->participant_name,
            'answers' => $questions->map(static function (QuizQuestion $q) use ($answers, $evidence): array {
                /** @var QuizAnswer|null $answer */
                $answer = $answers->get($q->id);
                $text = static fn (?int $i): ?string => $i === null ? null
                    : (string) ($q->options[$i]['text'] ?? $evidence[$q->options[$i]['evidence_item_id'] ?? 0] ?? '');

                return [
                    'position' => $q->position,
                    'prompt' => $q->prompt,
                    'chosen' => $text($answer?->option_index),
                    'correct' => $text($q->correct_index),
                    'is_correct' => $answer?->is_correct ?? false,
                ];
            })->all(),
        ]);
    }

    /**
     * «نزّل CSV» — صفٌّ لكلّ محاولة، وعمودٌ لكلّ سؤال بجواب المشارك.
     *
     * **وبعلامة BOM**: بلاها يفتح Excel الملفَّ بترميزٍ غربيّ فتصير العربية رموزاً.
     */
    public function export(Quiz $quiz, BuildQuizReport $report): StreamedResponse
    {
        $counted = $report->countedIds($quiz->id);
        $questions = $quiz->questions()->get();
        $evidence = BuildQuizReport::evidenceExcerpts($questions->all());

        $name = 'quiz-'.$quiz->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($quiz, $counted, $questions, $evidence): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                ...array_map(static fn (string $key): string => trans("quiz.reports.csv.{$key}"),
                    ['name', 'ip', 'score', 'total', 'percent', 'seconds', 'started', 'finished', 'attempt', 'counted']),
                ...$questions->map(static fn (QuizQuestion $q): string => trans('quiz.reports.csv.question', ['n' => $q->position]))->all(),
            ]);

            QuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->with('answers')
                ->orderBy('id')
                ->chunk(200, function ($attempts) use ($out, $counted, $questions, $evidence): void {
                    foreach ($attempts as $a) {
                        $answers = $a->answers->keyBy('quiz_question_id');

                        fputcsv($out, [
                            $a->participant_name,
                            $a->ip,
                            $a->score,
                            $a->total,
                            $a->isFinished() ? $a->percent() : '',
                            $a->duration_seconds,
                            $a->started_at->toDateTimeString(),
                            $a->finished_at?->toDateTimeString(),
                            $a->attempt_number,
                            in_array($a->id, $counted, true) ? '1' : '0',
                            ...$questions->map(static function (QuizQuestion $q) use ($answers, $evidence): string {
                                $answer = $answers->get($q->id);

                                if ($answer === null) {
                                    return '';
                                }

                                $option = $q->options[$answer->option_index] ?? [];
                                $text = (string) ($option['text'] ?? $evidence[$option['evidence_item_id'] ?? 0] ?? '');

                                return ($answer->is_correct ? '✓ ' : '✗ ').$text;
                            })->all(),
                        ]);
                    }
                });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
