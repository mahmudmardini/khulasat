<?php

declare(strict_types=1);

namespace App\Support\Quiz;

use App\Support\Arabic;
use App\Support\Verification\QuoteGuard;

/**
 * الحارسُ الحتميّ لأسئلة الاختبار — T-195. **بلا نموذج** (§2، القاعدة الثالثة).
 *
 * يأخذ ردَّ المرحلة ٨ ويُخرج ما يصلح للنشر منه. **والسؤالُ الذي يخالف يُحذف
 * وحده** ويُعاد الترقيم: سؤالٌ أقلّ خيرٌ من سؤالٍ بجوابين صحيحين، أو بشاهدٍ
 * لم يُحسم، أو بلفظٍ منسوبٍ لم يُتحقَّق منه.
 *
 * **وترتيبُ الخيارات يخلطه الحارس لا النموذج.** النموذجُ يضع الصحيح أوّلاً
 * كما تأمره التعليمات، والنماذجُ تميل إلى موضعٍ بعينه لو تُركت. والخلطُ
 * حتميٌّ ببذرةٍ من رقم المهمّة ورقم السؤال، فلا يتبدّل بين بناءٍ وبناء.
 */
final class QuizGuard
{
    public const MIN_QUESTIONS = 3;

    public const MAX_QUESTIONS = 10;

    public const MAX_PROMPT_WORDS = 40;

    public const MAX_OPTION_WORDS = 20;

    public const MAX_EXPLANATION_WORDS = 35;

    /** خيارا `true_false` بترتيبهما الثابت. */
    public const TRUE_LABEL = 'صواب';

    public const FALSE_LABEL = 'خطأ';

    /** خياراتٌ تمنعها التعليمات، وتُكشف بعد التطبيع. */
    private const BANNED_OPTIONS = ['كل ما سبق', 'جميع ما سبق', 'لا شيء مما سبق', 'ليس مما سبق', 'لا شيء ما سبق'];

    /** @var list<array{index: int, reason: string}> */
    private array $dropped = [];

    /**
     * @param  array<string, int>  $evidenceIds  معرّفُ الشاهد في المادّة ← `evidence_items.id`.
     */
    public function __construct(
        private readonly int $axisCount,
        private readonly array $evidenceIds,
        private readonly QuoteGuard $quotes,
        private readonly int $seed,
    ) {}

    /**
     * @param  array<string, int>  $evidenceIds
     * @param  list<string>  $settledTexts  ألفاظُ الشواهد المحسومة — بها يُعرف الاقتباسُ المثبَّت.
     */
    public static function for(int $axisCount, array $evidenceIds, array $settledTexts, int $seed): self
    {
        return new self($axisCount, $evidenceIds, QuoteGuard::for($settledTexts), $seed);
    }

    /**
     * @param  array<string, mixed>  $decoded  ردُّ المرحلة ٨ بعد التحقّق من مخطّطه.
     * @return list<QuizQuestionDraft>
     */
    public function guard(array $decoded): array
    {
        $this->dropped = [];
        $kept = [];

        foreach (array_values((array) ($decoded['questions'] ?? [])) as $index => $question) {
            if (count($kept) >= self::MAX_QUESTIONS) {
                $this->drop($index, 'زاد على الحدّ الأعلى للأسئلة.');

                continue;
            }

            $reason = null;
            $draft = is_array($question) ? $this->question($question, $index, $reason) : null;

            if ($draft === null) {
                $this->drop($index, $reason ?? 'سؤالٌ ليس كائناً.');

                continue;
            }

            $kept[] = $draft;
        }

        return $kept;
    }

    /** @return list<array{index: int, reason: string}> ما حُذف، وسببُه. */
    public function dropped(): array
    {
        return $this->dropped;
    }

    /**
     * يفحص نصّاً عدّله صاحبُ الاختبار في اللوحة — **بالحارس نفسه**.
     *
     * @param  list<string|null>  $optionTexts  نصوصُ الخيارات، و`null` لخيار الشاهد (لا يُعدَّل).
     * @return string|null سببُ الرفض، أو `null` إن صلح.
     */
    public function editProblem(string $kind, string $prompt, array $optionTexts, string $explanation): ?string
    {
        $problem = $this->textProblem('السؤال', $prompt, self::MAX_PROMPT_WORDS)
            ?? $this->textProblem('الشرح', $explanation, self::MAX_EXPLANATION_WORDS);

        if ($problem !== null) {
            return $problem;
        }

        if ($kind === 'evidence') {
            return null;
        }

        if ($kind === 'true_false') {
            return array_map(static fn ($t): string => Arabic::normalize((string) $t), $optionTexts) === [Arabic::normalize(self::TRUE_LABEL), Arabic::normalize(self::FALSE_LABEL)]
                ? null
                : 'خيارا الصواب والخطأ لا يُعدَّلان.';
        }

        foreach ($optionTexts as $text) {
            $problem = $this->textProblem('الخيار', (string) $text, self::MAX_OPTION_WORDS) ?? $this->bannedOption((string) $text);

            if ($problem !== null) {
                return $problem;
            }
        }

        return $this->duplicates($optionTexts) ? 'خياران متطابقان.' : null;
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function question(array $question, int $index, ?string &$reason): ?QuizQuestionDraft
    {
        $kind = (string) ($question['kind'] ?? '');

        if (! in_array($kind, QuizQuestionDraft::KINDS, true)) {
            $reason = "نوعٌ مجهول: {$kind}.";

            return null;
        }

        $level = $question['level'] ?? null;
        $level = in_array($level, QuizQuestionDraft::LEVELS, true) ? $level : null;

        $prompt = trim((string) ($question['prompt'] ?? ''));
        $explanation = trim((string) ($question['explanation'] ?? ''));

        $reason = $this->textProblem('السؤال', $prompt, self::MAX_PROMPT_WORDS)
            ?? $this->textProblem('الشرح', $explanation, self::MAX_EXPLANATION_WORDS);

        if ($reason !== null) {
            return null;
        }

        $axisIndex = $this->axis($question['axis_id'] ?? null);

        if ($axisIndex === null) {
            $reason = 'محورٌ ليس في البنية: '.(string) ($question['axis_id'] ?? '—').'.';

            return null;
        }

        $evidenceItemIds = [];

        foreach ((array) ($question['evidence_ids'] ?? []) as $key) {
            $id = $this->evidenceIds[(string) $key] ?? null;

            if ($id === null) {
                $reason = 'شاهدٌ ليس من شواهد هذا الدرس المحسومة: '.(string) $key.'.';

                return null;
            }

            $evidenceItemIds[] = $id;
        }

        $options = array_values(array_filter((array) ($question['options'] ?? []), 'is_array'));

        $built = match ($kind) {
            'true_false' => $this->trueFalse($options, $reason),
            'evidence' => $this->evidenceOptions($options, $reason),
            default => $this->singleOptions($options, $reason),
        };

        if ($built === null) {
            return null;
        }

        [$options, $correct] = $built;

        if ($kind !== 'true_false') {
            [$options, $correct] = $this->shuffle($options, $correct, $index);
        }

        return new QuizQuestionDraft(
            kind: $kind,
            level: $level,
            prompt: $prompt,
            options: $options,
            correctIndex: $correct,
            explanation: $explanation,
            axisIndex: $axisIndex,
            evidenceItemIds: array_values(array_unique($evidenceItemIds)),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array{0: list<array{text: string|null, evidence_item_id: int|null}>, 1: int}|null
     */
    private function singleOptions(array $options, ?string &$reason): ?array
    {
        if (count($options) < 3 || count($options) > 4) {
            $reason = 'عددُ الخيارات '.count($options).'، والمطلوب ثلاثة أو أربعة.';

            return null;
        }

        $correct = $this->correctIndex($options, $reason);

        if ($correct === null) {
            return null;
        }

        $texts = [];

        foreach ($options as $option) {
            $text = trim((string) ($option['text'] ?? ''));
            $reason = $this->textProblem('الخيار', $text, self::MAX_OPTION_WORDS) ?? $this->bannedOption($text);

            if ($reason !== null) {
                return null;
            }

            $texts[] = $text;
        }

        if ($this->duplicates($texts)) {
            $reason = 'خياران متطابقان.';

            return null;
        }

        return [array_map(static fn (string $text): array => ['text' => $text, 'evidence_item_id' => null], $texts), $correct];
    }

    /**
     * **«صواب» ثمّ «خطأ» دائماً**، أيّاً كان ترتيبُ النموذج — ولا يُخلطان.
     *
     * @param  list<array<string, mixed>>  $options
     * @return array{0: list<array{text: string|null, evidence_item_id: int|null}>, 1: int}|null
     */
    private function trueFalse(array $options, ?string &$reason): ?array
    {
        if (count($options) !== 2) {
            $reason = 'سؤالُ الصواب والخطأ بخيارين لا غير.';

            return null;
        }

        $correct = $this->correctIndex($options, $reason);

        if ($correct === null) {
            return null;
        }

        $true = Arabic::normalize(self::TRUE_LABEL);
        $false = Arabic::normalize(self::FALSE_LABEL);
        $labels = array_map(static fn (array $o): string => Arabic::normalize(trim((string) ($o['text'] ?? ''))), $options);

        if (! in_array($labels, [[$true, $false], [$false, $true]], true)) {
            $reason = 'خيارا الصواب والخطأ ليسا «صواب» و«خطأ».';

            return null;
        }

        $statementIsTrue = $labels[$correct] === $true;

        return [[
            ['text' => self::TRUE_LABEL, 'evidence_item_id' => null],
            ['text' => self::FALSE_LABEL, 'evidence_item_id' => null],
        ], $statementIsTrue ? 0 : 1];
    }

    /**
     * خياراتُ الشاهد معرّفاتٌ لا نصوص — **ولفظُ الشاهد لا يأتي من النموذج أبداً**.
     *
     * @param  list<array<string, mixed>>  $options
     * @return array{0: list<array{text: string|null, evidence_item_id: int|null}>, 1: int}|null
     */
    private function evidenceOptions(array $options, ?string &$reason): ?array
    {
        if (count($options) < 2 || count($options) > 4) {
            $reason = 'سؤالُ الشاهد بخيارين إلى أربعة.';

            return null;
        }

        $correct = $this->correctIndex($options, $reason);

        if ($correct === null) {
            return null;
        }

        $ids = [];

        foreach ($options as $option) {
            $id = $this->evidenceIds[(string) ($option['evidence_id'] ?? '')] ?? null;

            if ($id === null) {
                $reason = 'خيارُ شاهدٍ ليس من شواهد هذا الدرس المحسومة.';

                return null;
            }

            $ids[] = $id;
        }

        if (count(array_unique($ids)) !== count($ids)) {
            $reason = 'شاهدٌ مكرّر في الخيارات.';

            return null;
        }

        return [array_map(static fn (int $id): array => ['text' => null, 'evidence_item_id' => $id], $ids), $correct];
    }

    /** @param list<array<string, mixed>> $options */
    private function correctIndex(array $options, ?string &$reason): ?int
    {
        $correct = array_keys(array_filter($options, static fn (array $o): bool => ($o['correct'] ?? false) === true));

        if (count($correct) !== 1) {
            $reason = count($correct) === 0 ? 'لا جوابَ صحيح.' : 'أكثرُ من جوابٍ صحيح.';

            return null;
        }

        return (int) $correct[0];
    }

    private function axis(mixed $axisId): ?int
    {
        if (! is_string($axisId) || preg_match('/^axis-(\d+)$/', trim($axisId), $m) !== 1) {
            return null;
        }

        $number = (int) $m[1];

        return $number >= 1 && $number <= $this->axisCount ? $number - 1 : null;
    }

    private function textProblem(string $label, string $text, int $maxWords): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return "{$label} فارغ.";
        }

        if (self::words($text) > $maxWords) {
            return "{$label} تجاوز {$maxWords} كلمة.";
        }

        // **لفظٌ منسوبٌ لم يُتحقَّق منه يُسقط السؤال كلَّه** — لا جملتَه
        // وحدها كما في المتن: سؤالٌ نُزعت منه جملةٌ قد ينقلب معناه أو جوابُه.
        if (trim($this->quotes->clean($text)) !== $text) {
            return "{$label} ينسب لفظاً لم يُتحقَّق منه.";
        }

        return null;
    }

    private function bannedOption(string $text): ?string
    {
        $plain = self::plain($text);

        foreach (self::BANNED_OPTIONS as $banned) {
            if (str_contains($plain, self::plain($banned))) {
                return 'خيارٌ ممنوع: «'.$banned.'».';
            }
        }

        return null;
    }

    /** @param list<string|null> $texts */
    private function duplicates(array $texts): bool
    {
        $keys = array_map(static fn ($t): string => self::plain((string) $t), $texts);

        return count(array_unique($keys)) !== count($keys);
    }

    /**
     * خلطٌ حتميّ — Fisher–Yates بمولّدٍ خطّيٍّ بذرتُه من المهمّة والسؤال.
     *
     * **ولا يُمسّ مولّدُ PHP العامّ**: بذرُه يغيّر كلَّ ما يأتي بعده في الطلب.
     *
     * @param  list<array{text: string|null, evidence_item_id: int|null}>  $options
     * @return array{0: list<array{text: string|null, evidence_item_id: int|null}>, 1: int}
     */
    private function shuffle(array $options, int $correct, int $index): array
    {
        $order = array_keys($options);
        $state = crc32("{$this->seed}:{$index}");

        for ($i = count($order) - 1; $i > 0; $i--) {
            $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
            $j = $state % ($i + 1);
            [$order[$i], $order[$j]] = [$order[$j], $order[$i]];
        }

        return [
            array_map(static fn (int $k): array => $options[$k], $order),
            (int) array_search($correct, $order, true),
        ];
    }

    private function drop(int $index, string $reason): void
    {
        $this->dropped[] = ['index' => $index + 1, 'reason' => $reason];
    }

    private static function plain(string $text): string
    {
        $plain = Arabic::normalize($text);
        $plain = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $plain) ?? $plain;

        return trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);
    }

    private static function words(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
