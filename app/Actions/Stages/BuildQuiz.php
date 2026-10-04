<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Quiz\QuizGuard;
use App\Support\Quiz\QuizQuestionDraft;
use App\Support\Render\RenderedEvidence;
use Illuminate\Support\Facades\Log;

/**
 * المرحلة ٨ — اختبارُ الفهم. `prompts/islamic/PROMPT-PACK.md`، وT-195.
 *
 * **وتُستدعى عند طلب الاختبار وحده**، فلا حالةَ لها في آلة الحالات — كشأن
 * المرحلة ٧. وتجري بعد حسم المراجعة، فتصلها الشواهد بأحكامها النهائية.
 *
 * **ولا تُعيد قراءة التفريغ.** مدخلُها البنيةُ بمعرّفات محاورها والشواهدُ
 * المحسومة بألفاظ مصادرها، للسبب المكتوب في {@see CondenseForCarousel}:
 * كلفةٌ بلا حاجة، وبابُ حقنٍ ثانٍ (§12). **وشاهدٌ لم يُحسم أو حُذف لا
 * يصلها أصلاً.**
 */
final class BuildQuiz
{
    use RunsAStage;

    /**
     * @return array{questions: list<QuizQuestionDraft>, dropped: list<array{index: int, reason: string}>}
     *
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): array
    {
        $structure = (array) ($job->structure_json ?? []);
        $axes = array_values((array) ($structure['axes'] ?? []));

        if ($axes === []) {
            throw ModelCallFailed::permanent('structure_missing', 'لا بنية تُبنى منها الأسئلة.', Stage::Quiz);
        }

        if ($job->hasUnresolvedEvidence()) {
            throw ModelCallFailed::permanent('evidence_unresolved', 'في الملخّص شاهدٌ لم يُحسم بعد.', Stage::Quiz);
        }

        $items = $job->evidenceItems()
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->orderBy('id')
            ->get()
            ->values();

        // معرّفٌ قصير في المادّة ← معرّفُ الصفّ. والقصيرُ وحده يصل النموذج.
        $ids = [];
        foreach ($items as $n => $item) {
            $ids['e'.($n + 1)] = (int) $item->id;
        }

        $evidence = $items->map(static function (EvidenceItem $item): array {
            $shown = RenderedEvidence::fromItem($item);

            return ['kind' => $shown->kind, 'text' => $shown->text, 'source_line' => $shown->citation()];
        })->all();

        $response = $this->runStage(Stage::Quiz, self::material($structure, $evidence), $job);

        $guard = QuizGuard::for(
            axisCount: count($axes),
            evidenceIds: $ids,
            settledTexts: $items->map(static fn (EvidenceItem $i): string => RenderedEvidence::fromItem($i)->text)->all(),
            seed: (int) $job->id,
        );

        $questions = $guard->guard((array) ($response->decoded ?? []));

        if ($guard->dropped() !== []) {
            Log::warning('quiz_guard.dropped', [
                'summary_job_id' => $job->id,
                'dropped' => $guard->dropped(),
            ]);
        }

        if (count($questions) < QuizGuard::MIN_QUESTIONS) {
            throw ModelCallFailed::schemaValidation(
                'بقي '.count($questions).' من الأسئلة بعد الحراسة، والأقلّ '.QuizGuard::MIN_QUESTIONS.'.',
                Stage::Quiz,
            );
        }

        return ['questions' => $questions, 'dropped' => $guard->dropped()];
    }

    /**
     * المادّة كما تصل النموذج: «بنية المحاضرة بمحاورها، ولكلّ محورٍ معرّف.
     * والشواهد بعد التحقّق بألفاظ مصادرها، ولكلّ شاهدٍ معرّف» — نصّ المرحلة ٨.
     *
     * **عامّةٌ لا خاصّة**: يقرؤها قياسُ النماذج (`khulasah:quiz-bench`) بحرفها،
     * فلا يُقاس نموذجٌ على مادّةٍ غير التي يراها في المنتج.
     *
     * @param  array<string, mixed>  $structure
     * @param  list<array{kind: string, text: string, source_line: string}>  $evidence  بترتيب `e1`، `e2`…
     */
    public static function material(array $structure, array $evidence): string
    {
        $axes = array_values((array) ($structure['axes'] ?? []));

        $structure['axes'] = array_map(
            static fn (int $n, mixed $axis): array => ['axis_id' => 'axis-'.($n + 1)] + (array) $axis,
            array_keys($axes),
            $axes,
        );

        return (string) json_encode([
            'structure' => $structure,
            'evidence' => array_map(static fn (int $n, array $item): array => [
                'evidence_id' => 'e'.($n + 1),
                'kind' => $item['kind'],
                'text' => $item['text'],
                'source_line' => $item['source_line'],
            ], array_keys($evidence), array_values($evidence)),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
