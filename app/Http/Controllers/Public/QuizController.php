<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Quiz\FinishQuizAttempt;
use App\Enums\OutputType;
use App\Http\Controllers\Controller;
use App\Models\EvidenceItem;
use App\Models\Output;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Support\Render\BrandKit;
use App\Support\Render\RenderedEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * صفحاتُ المشارك في الاختبار — T-195. **عامّةٌ بلا دخول ولا اسم**.
 *
 * ★ **ولا يُطلب من المشارك شيءٌ ولا يُحفظ عنه شيء** — قرار @HasanSiwi، ٤
 * أكتوبر ٢٠٢٦: لا اسمَ ولا IP. فالمحاولةُ إجاباتٌ ودرجةٌ ووقت، بلا صاحب.
 *
 * ★ **والخادمُ يصحّح، والمتصفّحُ لا يعرف الصحيح قبل أوانه.** فلا يحمل HTML
 * صفحة الأسئلة الجوابَ الصحيح، ولا ردُّ حفظ الجواب في وضع «في الآخر». ومن فتح
 * مصدر الصفحة لا يجد الحلّ — وإلّا صارت التقارير (T-201) أرقاماً لا معنى لها.
 *
 * ★★ **والوقتُ من ساعة الخادم**: من «ابدأ» إلى «أنهِ»، لا من ساعة الجهاز.
 *
 * والاختبارُ يُطلب برمزه وحده، **بلا نطاق جهة**: لا جهةَ في سياق طلبٍ عامّ.
 * والرمزُ عشوائيّ، فلا يُعدّ إلى اختبار جهةٍ أخرى.
 */
class QuizController extends Controller
{
    public function show(string $token): View
    {
        $quiz = $this->quiz($token);
        $available = $this->available($quiz);

        if ($available) {
            // **عدُّ الفتح بلا أثرٍ عن القارئ**: رقمٌ يزيد، لا صفٌّ يُكتب.
            Quiz::acrossTenants()->whereKey($quiz->id)->increment('opens_count');
        }

        return view('quiz.start', $this->frame($quiz) + [
            'available' => $available,
            'count' => $quiz->questions()->count(),
        ]);
    }

    /** «ابدأ» — بلا حقلٍ واحد. والمحاولةُ تُعرف برمزها في رابطها وحده. */
    public function start(string $token): RedirectResponse
    {
        $quiz = $this->quiz($token);

        if (! $this->available($quiz)) {
            return redirect()->route('quiz.show', $quiz->token);
        }

        $attempt = QuizAttempt::acrossTenants()->create([
            'tenant_id' => $quiz->tenant_id,
            'quiz_id' => $quiz->id,
            'token' => Str::random(40),
            'total' => $quiz->questions()->count(),
            'started_at' => now(),
        ]);

        return redirect()->route('quiz.attempt', [$quiz->token, $attempt->token]);
    }

    public function attempt(string $token, string $attempt): View|RedirectResponse
    {
        $quiz = $this->quiz($token);
        $row = $this->attemptOf($quiz, $attempt);

        if ($row->isFinished()) {
            return redirect()->route('quiz.result', [$quiz->token, $row->token]);
        }

        $questions = $quiz->questions()->get();
        $answers = $row->answers()->get()->keyBy('quiz_question_id');
        $immediate = $quiz->revealsImmediately();
        $evidence = $this->evidence($questions->all());

        return view('quiz.attempt', $this->frame($quiz) + [
            'attempt' => $row,
            'immediate' => $immediate,
            'questions' => $questions->map(fn (QuizQuestion $q): array => $this->question(
                $q,
                $answers->get($q->id),
                // **في وضع «بعد كلّ سؤال» وحده** يُكشف الحكمُ لما أُجيب عنه.
                reveal: $immediate && $answers->has($q->id),
                evidence: $evidence,
            ))->all(),
        ]);
    }

    public function answer(Request $request, string $token, string $attempt): JsonResponse
    {
        $quiz = $this->quiz($token);
        $row = $this->attemptOf($quiz, $attempt);

        if ($row->isFinished()) {
            return response()->json(['message' => trans('quiz.public.already_finished', [], 'ar')], 409);
        }

        $question = $quiz->questions()->whereKey((int) $request->input('question'))->first();
        $option = filter_var($request->input('option'), FILTER_VALIDATE_INT);

        if ($question === null || $option === false || $option < 0 || $option >= count($question->options)) {
            return response()->json(['message' => trans('quiz.public.answer_invalid', [], 'ar')], 422);
        }

        $existing = $row->answers()->where('quiz_question_id', $question->id)->first();

        // **في وضع «بعد كلّ سؤال» يُقفل الجواب** بعد أن رأى صاحبُه الحكم.
        if ($quiz->revealsImmediately() && $existing !== null) {
            return response()->json($this->verdict($question, $existing), 409);
        }

        $answer = QuizAnswer::query()->updateOrCreate(
            ['quiz_attempt_id' => $row->id, 'quiz_question_id' => $question->id],
            ['option_index' => $option, 'is_correct' => $option === $question->correct_index, 'answered_at' => now()],
        );

        return response()->json($quiz->revealsImmediately() ? $this->verdict($question, $answer) : ['saved' => true]);
    }

    public function finish(Request $request, string $token, string $attempt, FinishQuizAttempt $finish): RedirectResponse
    {
        $quiz = $this->quiz($token);
        $row = $this->attemptOf($quiz, $attempt);

        if (! $row->isFinished()) {
            // نموذجُ ما بلا JavaScript يحمل الأجوبة كلَّها في الإنهاء.
            $finish->handle($row, (array) $request->input('answers', []));
        }

        return redirect()->route('quiz.result', [$quiz->token, $row->token]);
    }

    public function result(string $token, string $attempt): View|RedirectResponse
    {
        $quiz = $this->quiz($token);
        $row = $this->attemptOf($quiz, $attempt);

        if (! $row->isFinished()) {
            return redirect()->route('quiz.attempt', [$quiz->token, $row->token]);
        }

        $questions = $quiz->questions()->get();
        $answers = $row->answers()->get()->keyBy('quiz_question_id');
        $axes = array_values((array) ($quiz->summaryJob?->structure_json['axes'] ?? []));
        $evidence = $this->evidence($questions->all());

        return view('quiz.result', $this->frame($quiz) + [
            'attempt' => $row,
            'percent' => $row->percent(),
            'honoured' => $row->percent() >= FinishQuizAttempt::HONOUR_PERCENT,
            'questions' => $questions->map(fn (QuizQuestion $q): array => $this->question($q, $answers->get($q->id), reveal: true, evidence: $evidence) + [
                'axis' => $q->axis_index === null ? null : ($axes[$q->axis_index]['name'] ?? null),
            ])->all(),
        ]);
    }

    /** الاختبار برمزه — **ولا يُكشف اختبارٌ تعذّر بناؤه**: لمن عرف رمزه ٤٠٤. */
    private function quiz(string $token): Quiz
    {
        $quiz = Quiz::acrossTenants()
            ->where('token', $token)
            ->with(['summaryJob.lecture', 'summaryJob.tenant'])
            ->first();

        abort_if($quiz === null || ! $quiz->isReady() || $quiz->summaryJob === null, 404);

        return $quiz;
    }

    private function attemptOf(Quiz $quiz, string $token): QuizAttempt
    {
        $attempt = QuizAttempt::acrossTenants()->where('quiz_id', $quiz->id)->where('token', $token)->first();

        abort_if($attempt === null, 404);

        return $attempt;
    }

    /**
     * يقبل المحاولاتِ اختبارٌ مفتوحٌ لملخّصٍ منشور — **فملخّصٌ أُلغي نشرُه
     * يُغلق اختبارَه معه**، ولا يبقى رابطٌ يُحيل إلى صفحةٍ تردّ ٤١٠.
     */
    private function available(Quiz $quiz): bool
    {
        return $quiz->isOpen() && $quiz->summaryJob?->published_at !== null;
    }

    /**
     * ما تشترك فيه صفحاتُ الاختبار: هويةُ الجهة وعنوانُ الدرس ورابطُ الملخّص.
     *
     * @return array<string, mixed>
     */
    private function frame(Quiz $quiz): array
    {
        $job = $quiz->summaryJob;
        $lecture = $job?->lecture;
        $brand = BrandKit::forTenant($job->tenant, $lecture);

        return [
            'quiz' => $quiz,
            'brand' => $brand,
            'palette' => $brand->palette,
            'title' => (string) ($job->structure_json['title_ar'] ?? $lecture?->title_ar ?? ''),
            // **اسمُ الملقي كما في صفحة الملخّص** (`sheikh_full`) — لا يُضاف إليه
            // لقبٌ من عندنا ولا يُنزع منه شيء: ما كتبته الجهةُ هو ما يُعرض.
            'speaker' => trim(($lecture?->speaker_title ?? '').' '.($lecture?->speaker_name ?? '')) ?: null,
            'summaryUrl' => $job->published_at === null ? null : Output::acrossTenants()
                ->where('summary_job_id', $job->id)
                ->where('type', OutputType::Page->value)
                ->where('locale', 'ar')
                ->whereNotNull('public_url')
                ->value('public_url'),
        ];
    }

    /**
     * السؤالُ كما يُرسم — **والصحيحُ لا يدخله إلّا مع `reveal`**.
     *
     * @param  array<int, RenderedEvidence>  $evidence
     * @return array<string, mixed>
     */
    private function question(QuizQuestion $q, ?QuizAnswer $answer, bool $reveal, array $evidence): array
    {
        return [
            'id' => $q->id,
            'position' => $q->position,
            'kind' => $q->kind,
            'prompt' => $q->prompt,
            'options' => array_map(static function (array $option) use ($evidence): array {
                $shown = $option['evidence_item_id'] === null ? null : ($evidence[$option['evidence_item_id']] ?? null);

                return [
                    'text' => $shown?->text ?? (string) ($option['text'] ?? ''),
                    'evidence' => $shown,
                ];
            }, $q->options),
            'chosen' => $answer?->option_index,
            'correct' => $reveal ? $q->correct_index : null,
            'explanation' => $reveal ? $q->explanation : null,
        ];
    }

    /**
     * ألفاظُ الشواهد في خيارات الأسئلة — **من مصدرها، لا من النموذج أبداً**.
     *
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

        if ($ids === []) {
            return [];
        }

        return EvidenceItem::acrossTenants()
            ->whereIn('id', array_unique($ids))
            ->get()
            ->mapWithKeys(static fn (EvidenceItem $item): array => [(int) $item->id => RenderedEvidence::fromItem($item)])
            ->all();
    }

    /** @return array<string, mixed> */
    private function verdict(QuizQuestion $question, QuizAnswer $answer): array
    {
        return [
            'saved' => true,
            'correct' => $answer->is_correct,
            'correct_index' => $question->correct_index,
            'explanation' => $question->explanation,
        ];
    }
}
