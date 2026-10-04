<?php

declare(strict_types=1);

namespace App\Support\Quiz;

/**
 * سؤالٌ مرّ بـ{@see QuizGuard} — جاهزٌ للحفظ، وخياراتُه مخلوطةٌ كما تُعرض.
 *
 * **وخيارُ الشاهد لا نصّ فيه**: معرّفُ الشاهد وحده، ولفظُه يُقرأ عند العرض من
 * `evidence_items.matched_text` — §2، القاعدة الثالثة.
 */
final readonly class QuizQuestionDraft
{
    public const KINDS = ['single', 'true_false', 'evidence'];

    public const LEVELS = ['recall', 'understanding', 'application'];

    /**
     * @param  list<array{text: string|null, evidence_item_id: int|null}>  $options
     * @param  list<int>  $evidenceItemIds
     */
    public function __construct(
        public string $kind,
        public ?string $level,
        public string $prompt,
        public array $options,
        public int $correctIndex,
        public string $explanation,
        public ?int $axisIndex,
        public array $evidenceItemIds = [],
    ) {}
}
