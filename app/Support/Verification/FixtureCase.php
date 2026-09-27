<?php

declare(strict_types=1);

namespace App\Support\Verification;

/**
 * One case out of the governing acceptance sample.
 *
 * `fixtures/evidence-fixtures.json` هو معيار القبول الحاكم للبوّابة صفر،
 * وهذا الكائن صورةُ حالةٍ واحدة منه بعد قراءتها.
 */
final readonly class FixtureCase
{
    /**
     * @param  array<string, mixed>  $expect
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $note,
        public string $input,
        public array $expect,
        public ?string $claimedNarrator = null,
    ) {}

    public function toInput(): EvidenceInput
    {
        return new EvidenceInput(
            kind: $this->kind,
            rawText: $this->input,
            claimedNarrator: $this->claimedNarrator,
        );
    }

    /** الحالة التي يُنتظر فيها وقوفُ الشاهد للمراجعة. */
    public function expectsHold(): bool
    {
        return ($this->expect['review_status'] ?? null) === 'pending'
            || ($this->expect['must_not_auto_pass'] ?? false) === true;
    }

    /** الحالة التي يُنتظر فيها مرورُه آلياً. */
    public function expectsAutoPass(): bool
    {
        return ($this->expect['review_status'] ?? null) === 'auto_passed';
    }
}
