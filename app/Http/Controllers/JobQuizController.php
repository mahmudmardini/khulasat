<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Quiz\GenerateQuiz;
use App\Actions\Stages\RenderAndPublish;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SummaryJob;
use App\Support\Quiz\QuizGuard;
use App\Support\Render\RenderedEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;
use Throwable;

/**
 * إدارةُ اختبار الملخّص في اللوحة — T-195.
 *
 * **والمحاولاتُ تُقفل البنية**: بعد أوّل محاولةٍ لا يُحذف سؤالٌ ولا يُعاد
 * التوليد، ويبقى تعديلُ النصّ وحده — بالحارس نفسه. فالإحصاءاتُ (T-201)
 * على أسئلةٍ ثابتة المعرّف والجواب.
 *
 * **و«ابنِ اختباراً» لا يُحتسب من الحصّة** (SCREENS.md §6): يجري على محتوًى
 * محقَّقٍ موجود. لكنّه نداءُ نموذج، فيُفحص سقفُ الإنفاق قبله.
 */
class JobQuizController extends Controller
{
    public function show(Request $request, SummaryJob $job): InertiaResponse
    {
        $job->loadMissing('lecture');
        $quiz = $job->quiz()->first();

        return Inertia::render('Jobs/Quiz', [
            'job' => [
                'id' => $job->id,
                'title' => $job->structure_json['title_ar'] ?? $job->lecture?->title_ar,
                'published' => $job->published_at !== null,
                'pending_evidence' => $job->pendingEvidenceCount(),
                'has_structure' => ((array) ($job->structure_json['axes'] ?? [])) !== [],
            ],
            'can_edit' => $request->user()?->role->canPublish() ?? false,
            'quiz' => $quiz === null ? null : $this->payload($job, $quiz),
        ]);
    }

    /** يبني الاختبار أو يعيد توليده — نداءٌ مدفوع، ولا يُحتسب من الحصّة. */
    public function store(Request $request, SummaryJob $job, GenerateQuiz $generate, RenderAndPublish $publish): RedirectResponse
    {
        $this->authorizeEditing($request);

        if ($job->hasUnresolvedEvidence()) {
            return back()->withErrors(['quiz' => trans('quiz.panel.blocked_body')]);
        }

        try {
            $quiz = $generate->handle($job, fromPanel: true);
        } catch (RuntimeException $refused) {
            return back()->withErrors(['quiz' => $refused->getMessage()]);
        }

        if (! $quiz->isReady()) {
            return back()->withErrors(['quiz' => trans('quiz.panel.failed')]);
        }

        $this->republish($job, $publish);

        return back()->with('message', trans('quiz.panel.built'));
    }

    /** الحالةُ (مفتوح/مغلق) ومتى يُكشف الجواب. */
    public function update(Request $request, SummaryJob $job, RenderAndPublish $publish): RedirectResponse
    {
        $this->authorizeEditing($request);
        $quiz = $this->quizOf($job);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in([Quiz::STATUS_OPEN, Quiz::STATUS_CLOSED])],
            'feedback' => ['sometimes', Rule::in([Quiz::FEEDBACK_END, Quiz::FEEDBACK_IMMEDIATE])],
        ]);

        $statusChanged = isset($data['status']) && $data['status'] !== $quiz->status;

        $quiz->forceFill($data)->save();

        // **الزرُّ يظهر ويختفي مع الحالة** — فيُحدَّث المنشور بمساره القائم (T-30).
        if ($statusChanged) {
            $this->republish($job, $publish);
        }

        return back()->with('message', trans('quiz.panel.saved'));
    }

    /** تعديلُ نصّ سؤال — **بالحارس نفسه**، ولا يُعدَّل خيارُ شاهدٍ ولا الجوابُ الصحيح. */
    public function updateQuestion(Request $request, SummaryJob $job, int $question): RedirectResponse
    {
        $this->authorizeEditing($request);
        $quiz = $this->quizOf($job);
        $row = $quiz->questions()->whereKey($question)->firstOrFail();

        $data = $request->validate([
            'prompt' => ['required', 'string', 'max:500'],
            'explanation' => ['required', 'string', 'max:500'],
            'options' => ['array'],
            'options.*' => ['nullable', 'string', 'max:300'],
        ]);

        $texts = [];
        foreach ($row->options as $k => $option) {
            // خيارُ الشاهد يبقى معرّفاً؛ والصوابُ والخطأ ثابتان.
            $texts[$k] = $option['evidence_item_id'] !== null
                ? null
                : trim((string) ($data['options'][$k] ?? $option['text']));
        }

        $problem = $this->guard($job)->editProblem($row->kind, trim($data['prompt']), $texts, trim($data['explanation']));

        if ($problem !== null) {
            return back()->withErrors(["question.{$row->id}" => $problem]);
        }

        $row->forceFill([
            'prompt' => trim($data['prompt']),
            'explanation' => trim($data['explanation']),
            'options' => array_map(
                static fn (array $option, ?string $text): array => $option['evidence_item_id'] !== null ? $option : ['text' => $text, 'evidence_item_id' => null],
                $row->options,
                $texts,
            ),
        ])->save();

        return back()->with('message', trans('quiz.panel.saved'));
    }

    /** حذفُ سؤال — **قبل أوّل محاولةٍ وحدها**، ولا يُنزل الاختبار تحت ثلاثة. */
    public function destroyQuestion(Request $request, SummaryJob $job, int $question): RedirectResponse
    {
        $this->authorizeEditing($request);
        $quiz = $this->quizOf($job);

        if ($quiz->hasAttempts()) {
            return back()->withErrors(['quiz' => trans('quiz.panel.locked_delete')]);
        }

        if ($quiz->questions()->count() <= QuizGuard::MIN_QUESTIONS) {
            return back()->withErrors(['quiz' => trans('quiz.panel.min_questions', ['min' => QuizGuard::MIN_QUESTIONS])]);
        }

        DB::transaction(function () use ($quiz, $question): void {
            $quiz->questions()->whereKey($question)->firstOrFail()->delete();

            foreach ($quiz->questions()->get() as $index => $row) {
                $row->forceFill(['position' => $index + 1])->save();
            }
        });

        return back()->with('message', trans('quiz.panel.deleted'));
    }

    /** @return array<string, mixed> */
    private function payload(SummaryJob $job, Quiz $quiz): array
    {
        $axes = array_values((array) ($job->structure_json['axes'] ?? []));
        $questions = $quiz->questions()->get();
        $evidence = $this->evidence($questions->all());

        return [
            'state' => $quiz->state,
            'failure_reason' => $quiz->failure_reason,
            'status' => $quiz->status,
            'feedback' => $quiz->feedback,
            'url' => $quiz->publicUrl(),
            'attempts' => $quiz->attempts()->count(),
            'generated_at' => $quiz->generated_at?->toIso8601String(),
            'questions' => $questions->map(static fn (QuizQuestion $q): array => [
                'id' => $q->id,
                'position' => $q->position,
                'kind' => $q->kind,
                'level' => $q->level,
                'prompt' => $q->prompt,
                'explanation' => $q->explanation,
                'correct_index' => $q->correct_index,
                'axis' => $q->axis_index === null ? null : ($axes[$q->axis_index]['name'] ?? null),
                'options' => array_map(static function (array $option) use ($evidence): array {
                    $shown = $option['evidence_item_id'] === null ? null : ($evidence[$option['evidence_item_id']] ?? null);

                    return [
                        'text' => $shown?->text ?? (string) ($option['text'] ?? ''),
                        'evidence' => $shown === null ? null : [
                            'kind' => $shown->kind,
                            'citation' => $shown->citation(),
                        ],
                    ];
                }, $q->options),
            ])->all(),
        ];
    }

    /**
     * @param  list<QuizQuestion>  $questions
     * @return array<int, RenderedEvidence>
     */
    private function evidence(array $questions): array
    {
        $ids = [];
        foreach ($questions as $q) {
            foreach ($q->options as $option) {
                if ($option['evidence_item_id'] !== null) {
                    $ids[] = (int) $option['evidence_item_id'];
                }
            }
        }

        return $ids === [] ? [] : EvidenceItem::query()
            ->whereIn('id', array_unique($ids))
            ->get()
            ->mapWithKeys(static fn (EvidenceItem $item): array => [(int) $item->id => RenderedEvidence::fromItem($item)])
            ->all();
    }

    /** الحارسُ نفسُه الذي مرّ عليه التوليد — بشواهد هذه المهمّة المحسومة. */
    private function guard(SummaryJob $job): QuizGuard
    {
        $items = $job->evidenceItems()->whereNot('review_status', ReviewStatus::Removed->value)->get();

        return QuizGuard::for(
            axisCount: count((array) ($job->structure_json['axes'] ?? [])),
            evidenceIds: [],
            settledTexts: $items->map(static fn (EvidenceItem $i): string => RenderedEvidence::fromItem($i)->text)->all(),
            seed: (int) $job->id,
        );
    }

    private function quizOf(SummaryJob $job): Quiz
    {
        $quiz = $job->quiz()->first();

        abort_if($quiz === null, 404);

        return $quiz;
    }

    /**
     * يُحدَّث المنشور ليظهر الزرّ أو يختفي — **مسارُ «حدّث المنشور» نفسُه**
     * (T-30)، ولا نداءَ نموذجٍ فيه. وإخفاقُه لا يُفسد ما حُفظ: يُكتب ويُمضى،
     * وزرُّ «حدّث المنشور» في صفحة الملخّص يُعيده.
     */
    private function republish(SummaryJob $job, RenderAndPublish $publish): void
    {
        if ($job->published_at === null) {
            return;
        }

        try {
            $publish->handle($job->refresh());
        } catch (Throwable $failure) {
            Log::warning('quiz.republish_failed', ['summary_job_id' => $job->id, 'reason' => $failure->getMessage()]);
        }
    }

    private function authorizeEditing(Request $request): void
    {
        abort_unless($request->user()?->role->canPublish() ?? false, 403);
    }
}
