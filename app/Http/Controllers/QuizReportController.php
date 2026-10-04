<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Quiz\BuildQuizReport;
use App\Models\Quiz;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تقاريرُ الاختبارات في لوحة الجهة — T-201.
 *
 * ★ **أرقامٌ مجموعة، لا قائمةُ أشخاص** — قرار @HasanSiwi، ٤ أكتوبر ٢٠٢٦:
 * المحاولاتُ بلا أصحاب، فلا يُعرض صفٌّ لمشارك ولا إجاباتُ مشاركٍ بعينه.
 *
 * **ويراها كلُّ عضوٍ في الجهة**: قراءةٌ لا تغيير. والاختبارُ بنطاق الجهة في
 * ربط المسار، فتقريرُ جهةٍ ٤٠٤ لغيرها.
 */
class QuizReportController extends Controller
{
    public function index(BuildQuizReport $report): InertiaResponse
    {
        return Inertia::render('Quizzes/Index', ['report' => $report->overview()]);
    }

    public function show(Quiz $quiz, BuildQuizReport $report): InertiaResponse
    {
        $quiz->loadMissing('summaryJob.lecture');

        return Inertia::render('Quizzes/Show', [
            'quiz' => [
                'id' => $quiz->id,
                'job_id' => $quiz->summary_job_id,
                'title' => $quiz->summaryJob?->structure_json['title_ar'] ?? $quiz->summaryJob?->lecture?->title_ar,
                'status' => $quiz->status,
                'url' => $quiz->publicUrl(),
            ],
            'report' => $report->forQuiz($quiz),
        ]);
    }

    /**
     * «نزّل CSV» — **صفٌّ لكلّ سؤال لا لكلّ مشارك**: نسبةُ صوابه، وأكثرُ خطأٍ
     * فيه، وعددُ من اختار كلَّ خيار.
     *
     * **وبعلامة BOM**: بلاها يفتح Excel الملفَّ بترميزٍ غربيّ فتصير العربية رموزاً.
     */
    public function export(Quiz $quiz, BuildQuizReport $report): StreamedResponse
    {
        $quiz->loadMissing('summaryJob');
        $data = $report->forQuiz($quiz);
        $axes = array_values((array) ($quiz->summaryJob?->structure_json['axes'] ?? []));
        $width = max(2, ...array_map(static fn (array $q): int => count($q['options']), $data['questions'] ?: [['options' => []]]));

        return response()->streamDownload(function () use ($data, $axes, $width): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                ...array_map(static fn (string $key): string => trans("quiz.reports.csv.{$key}"),
                    ['number', 'question', 'axis', 'correct_rate', 'correct', 'top_wrong', 'top_wrong_rate', 'median_seconds']),
                ...array_map(static fn (int $n): string => trans('quiz.reports.csv.option', ['n' => $n]), range(1, $width)),
            ]);

            foreach ($data['questions'] as $q) {
                $options = array_map(
                    static fn (string $text, int $count): string => "{$text} ({$count})",
                    $q['options'],
                    $q['counts'],
                );

                fputcsv($out, [
                    $q['position'],
                    $q['prompt'],
                    $q['axis_index'] === null ? '' : (string) ($axes[$q['axis_index']]['name'] ?? ''),
                    $q['correct_rate'],
                    $q['options'][$q['correct_index']] ?? '',
                    $q['top_wrong'] === null ? '' : $q['options'][$q['top_wrong']['index']],
                    $q['top_wrong']['rate'] ?? '',
                    $q['median_seconds'],
                    ...array_pad($options, $width, ''),
                ]);
            }

            fclose($out);
        }, 'quiz-'.$quiz->id.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
