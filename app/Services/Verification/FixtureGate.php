<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\VerifierRegistry;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\FixtureCase;
use App\Support\Verification\FixtureOutcome;
use App\Support\Verification\VerificationResult;
use RuntimeException;

/**
 * Gate zero — runs the planted-evidence sample against the verifiers.
 *
 * هذه هي البوّابة الحاكمة للمشروع كلّه: عيّنة فيها شواهد صحيحة وأخرى
 * محرَّفة وموضوعة، والمنتج لا يُدمج حتى تُصنَّف كلّها على وجهها. وتُقرأ من
 * `fixtures/evidence-fixtures.json` لا من الكود، **فتُعدَّل العيّنة بلا نشر**
 * ولا يستطيع أحد أن يُرخي المعيار بتعديل اختبار.
 *
 * ويُشغّلها موضعان: اختبار `EvidenceFixturesTest` في CI، وأمر
 * `khulasah:verify-fixtures` على بيانات حقيقية. والمنطق هنا وحده حتى لا
 * يفترق ما يقيسه CI عمّا يقيسه المشغّل.
 */
class FixtureGate
{
    public const PATH = 'fixtures/evidence-fixtures.json';

    /** أقسام الملفّ ونوع الشاهد في كلّ منها. */
    private const SECTIONS = ['quran' => 'ayah', 'hadith' => 'hadith'];

    /**
     * **يُحلّ المحقّق من السجلّ لا من خريطةٍ تُمرَّر** — المواصفة §7-4 وT-02ب.
     * فالعيّنة تُشغَّل بسياسة مجالها هي، ولا تفترض أنّ المجال الشرعي هو
     * النظام كلّه.
     */
    public function __construct(
        private readonly VerifierRegistry $registry,
        private readonly string $domain = DomainPolicy::DEFAULT,
        private readonly ?string $path = null,
        /**
         * وضع الجهة — `tenants.on_unverified` (§7-5). والعيّنة مكتوبة على
         * `disclose` وهو الافتراض، ويُشغَّل `review` عليها في الاختبار
         * ليُقاس الوضعان معاً.
         */
        private readonly UnverifiedPolicy $mode = UnverifiedPolicy::Disclose,
    ) {}

    /**
     * @return list<FixtureOutcome>
     */
    public function run(): array
    {
        return array_map($this->check(...), $this->cases());
    }

    /**
     * @return list<FixtureCase>
     */
    public function cases(): array
    {
        $data = $this->read();
        $cases = [];

        foreach (self::SECTIONS as $section => $kind) {
            foreach ($data[$section] ?? [] as $raw) {
                $cases[] = new FixtureCase(
                    id: $raw['id'],
                    kind: $kind,
                    note: $raw['note'] ?? '',
                    input: $raw['input'],
                    expect: $raw['expect'] ?? [],
                    claimedNarrator: $raw['claimed_narrator'] ?? null,
                );
            }
        }

        return $cases;
    }

    /**
     * القواعد الحاجبة — تُقاس على العيّنة كلّها لا على حالة حالة.
     *
     * **وهذا مقصود:** الحالة الواحدة قد تمرّ لسببٍ خاطئ، والقاعدة الحاجبة
     * تسأل سؤالاً مجموعاً لا يُخفيه نجاحُ حالة. أهمّها الأولى: كم شاهداً
     * كان يجب أن يقف فمرّ؟ والجواب المقبول الوحيد صفر.
     *
     * @param  list<FixtureOutcome>  $outcomes
     * @return list<array{rule: string, passed: bool, detail: string}>
     */
    public function blockingRules(array $outcomes): array
    {
        // ★ الضمانة الحاكمة بعد سياسة البيان — §7-5:
        //   «لا يُنشر لفظٌ إلا لفظَ مصدره مقروناً بدرجته. وما جُهل مصدره
        //    لا يُنشر البتّة.»
        $lectureWordingIds = $this->idsOf($outcomes, fn (FixtureOutcome $o): bool => $o->published()
            && blank($o->result->matchedText));

        $ungradedIds = $this->idsOf($outcomes, fn (FixtureOutcome $o): bool => $o->published()
            && ! DomainPolicy::for($this->domain)->hasStatedSource($o->result));

        $undisclosedIds = $this->idsOf($outcomes, fn (FixtureOutcome $o): bool => $o->published()
            && ! $this->disclosesWeakness($o->result));

        $substitutedIds = $this->idsOf($outcomes, fn (FixtureOutcome $o): bool => ($o->case->expect['match_status'] ?? null) === 'none'
            && $o->result->matchedText !== null);

        $stitched = $this->outcomeFor($outcomes, 'Q-STITCHED-01');

        $soundRatio = $this->soundAutoMatchedRatio($outcomes);
        $minRatio = (float) ($this->read()['acceptance_criteria']['target_metrics']['sound_evidence_auto_matched_ratio_min'] ?? 0.7);

        return [
            [
                'rule' => 'لا يُنشر شاهد بلفظ المحاضرة — كلّ منشور بلفظ مصدره.',
                'passed' => $lectureWordingIds === [],
                'detail' => $lectureWordingIds === []
                    ? 'كلّ منشور معه لفظ مصدره.'
                    : 'نُشر بلا لفظ مصدر: '.implode('، ', $lectureWordingIds),
            ],
            [
                'rule' => 'لا يُنشر شاهد بلا درجة منصوصة — none والمجهول يُحذفان.',
                'passed' => $ungradedIds === [],
                'detail' => $ungradedIds === []
                    ? 'صفر — وهو الجواب المقبول الوحيد.'
                    : 'نُشر بلا درجة: '.implode('، ', $ungradedIds),
            ],
            [
                'rule' => 'لا يُنشر ضعيف ولا موضوع بلا بيان درجته في سلسلة التخريج.',
                'passed' => $undisclosedIds === [],
                'detail' => $undisclosedIds === []
                    ? 'كلّ ضعيفٍ وموضوعٍ منشورٍ مقرونٌ ببيانه.'
                    : 'نُشر بلا بيان: '.implode('، ', $undisclosedIds),
            ],
            [
                'rule' => 'لا يُستبدل نصّ غير مطابق بأقرب نصّ. none تعني none.',
                'passed' => $substitutedIds === [],
                'detail' => $substitutedIds === []
                    ? 'لم يُخترع لفظ لغير مطابق.'
                    : 'استُبدل لفظ في: '.implode('، ', $substitutedIds),
            ],
            [
                'rule' => 'المركّب من آيتين من سورتين مختلفتين لا يُطابَق.',
                'passed' => $stitched !== null && ! $stitched->result->matched(),
                'detail' => $stitched === null
                    ? 'حالة Q-STITCHED-01 غائبة عن العيّنة.'
                    : ($stitched->result->matched() ? 'طُوبقت — والنظام إذن يخترع آيات.' : 'لم تُطابَق.'),
            ],
            [
                'rule' => sprintf('يمرّ آلياً %s%% من الشواهد الصحيحة على الأقلّ.', $minRatio * 100),
                'passed' => $soundRatio >= $minRatio,
                'detail' => sprintf('%.0f%% مرّت آلياً.', $soundRatio * 100),
            ],
        ];
    }

    /**
     * أيُبيَّن الضعف والوضع في سلسلة التخريج؟ — §7-5.
     *
     * **ولماذا جاز نشر الضعيف أصلاً:** لأنّه يُنشر مقروناً ببيانه، وهذا عمل
     * أهل العلم. **والآفة في نقل الضعيف موهِماً صحّته، لا في نقله مبيَّناً.**
     * فإن سقط البيان سقط المسوّغ كلّه، وصار المنتج ينشر ضعيفاً على أنّه ثابت.
     *
     * وما لا درجة ضعفٍ له لا يُسأل عنه: الصحيح والحسن يُنشران بتخريجهما.
     */
    private function disclosesWeakness(VerificationResult $result): bool
    {
        $grade = $result->sourceMeta['grade'] ?? null;

        if (! in_array($grade, ['daif', 'mawdu'], true)) {
            return true;
        }

        $disclosure = (string) ($result->sourceMeta['disclosure'] ?? '');

        return $grade === 'daif'
            ? str_contains($disclosure, 'ضعيف')
            : str_contains($disclosure, 'لا يصحّ');
    }

    /**
     * تحقّق من حالة واحدة ووازن نتيجتها بتوقّعها.
     */
    private function check(FixtureCase $case): FixtureOutcome
    {
        $verifier = $this->registry->for($this->domain, $case->kind);

        // النوع الذي لا محقّق له يعود none ويُرفع للمراجعة — المواصفة §7-4.
        $result = $verifier === null
            ? VerificationResult::none()
            : $verifier->verify($case->toInput());

        $review = ReviewStatus::decide($result, DomainPolicy::for($this->domain), $this->mode);

        return new FixtureOutcome($case, $result, $review, $this->failures($case, $result, $review));
    }

    /**
     * @return list<string>
     */
    private function failures(FixtureCase $case, VerificationResult $result, ReviewStatus $review): array
    {
        $meta = $result->sourceMeta;
        $failures = [];

        foreach ($case->expect as $key => $want) {
            $got = match ($key) {
                'match_status' => $result->status->value,
                'review_status' => $review->value,
                'grade_class' => $meta['grade'] ?? null,
                'must_not_auto_pass' => $review !== ReviewStatus::AutoPassed,
                // **البيان لا مجرّد وجود الحكم** — §7-5. المطلوب أن تُقرأ
                // الدرجة في سلسلة التخريج المنشورة، لا أن تُسجَّل في البيانات.
                'must_show_grade' => filled($meta['disclosure'] ?? null),
                'must_show_correct_wording' => $this->showsBothWordings($result),
                'must_not_guess_nearest' => ! $result->matched() && $result->matchedText === null,
                // أيُنشر أم يُحذف — §7-5، والحقل أُضيف في T-06ب.
                'published' => $review === ReviewStatus::AutoPassed,
                // سلسلة التخريج المتوقَّعة بنصّها — T-06ب.
                'expected_takhrij' => $meta['disclosure'] ?? null,
                default => $meta[$key] ?? null,
            };

            if ($got !== $want) {
                $failures[] = sprintf(
                    '%s: منتظر %s، والحاصل %s.',
                    $key,
                    $this->readable($want),
                    $this->readable($got),
                );
            }
        }

        return $failures;
    }

    /**
     * أيُعرض لفظ المصدر إلى جانب المستخرج؟
     *
     * لا يكفي وجود لفظ المصدر: يجب أن يكون المستخرج محفوظاً معه وأن
     * يختلفا. فلو حلّ لفظ المصدر محلّ المستخرج صامتاً لضاع على المراجع
     * ما يراجعه أصلاً.
     */
    private function showsBothWordings(VerificationResult $result): bool
    {
        $extracted = $result->sourceMeta['extracted_text'] ?? null;

        return filled($result->matchedText)
            && filled($extracted)
            && $result->matchedText !== $extracted;
    }

    /**
     * نسبة الشواهد الصحيحة التي مرّت آلياً — `target_metrics`.
     *
     * @param  list<FixtureOutcome>  $outcomes
     */
    private function soundAutoMatchedRatio(array $outcomes): float
    {
        $sound = array_filter($outcomes, fn (FixtureOutcome $o): bool => $o->case->expectsAutoPass());

        if ($sound === []) {
            return 1.0;
        }

        $passed = array_filter($sound, fn (FixtureOutcome $o): bool => $o->reviewStatus === ReviewStatus::AutoPassed);

        return count($passed) / count($sound);
    }

    /**
     * @param  list<FixtureOutcome>  $outcomes
     * @param  callable(FixtureOutcome): bool  $predicate
     * @return list<string>
     */
    private function idsOf(array $outcomes, callable $predicate): array
    {
        return array_values(array_map(
            fn (FixtureOutcome $o): string => $o->case->id,
            array_filter($outcomes, $predicate),
        ));
    }

    /**
     * @param  list<FixtureOutcome>  $outcomes
     */
    private function outcomeFor(array $outcomes, string $id): ?FixtureOutcome
    {
        foreach ($outcomes as $outcome) {
            if ($outcome->case->id === $id) {
                return $outcome;
            }
        }

        return null;
    }

    private function readable(mixed $value): string
    {
        return match (true) {
            $value === true => 'نعم',
            $value === false => 'لا',
            $value === null => 'لا شيء',
            default => (string) $value,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $path = $this->path ?? base_path(self::PATH);

        if (! is_file($path)) {
            throw new RuntimeException("عيّنة القبول غير موجودة: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : throw new RuntimeException("عيّنة القبول غير صالحة: {$path}");
    }
}
