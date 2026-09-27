<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\MatchStatus;

/**
 * The verdict on one piece of evidence — المواصفة §7.
 *
 * `sourceMeta` يحمل ما يخصّ المجال (رقم السورة والآية، أو الراوي والتخريج
 * والدرجة)، ويُحفظ في عمود `source_meta` — المواصفة §4-أ.
 */
final readonly class VerificationResult
{
    /**
     * @param  array<string, mixed>  $sourceMeta
     */
    public function __construct(
        public MatchStatus $status,
        public ?string $matchedText = null,
        public ?string $sourceRef = null,
        public array $sourceMeta = [],
    ) {}

    /** لا مطابقة. **ولا يُخمَّن أقرب نصّ** — المواصفة §7-2. */
    public static function none(): self
    {
        return new self(MatchStatus::None);
    }

    public function matched(): bool
    {
        return $this->status !== MatchStatus::None;
    }

    /*
     * **وقرار المرور ليس هنا** — {@see \App\Support\Verification\DomainPolicy}.
     * النتيجة تصف ما وُجد، والمجال يحكم بما يُفعل به. وخلطُهما يجعل كلّ
     * مجالٍ يرث تشدّد الشرعي أو يُرخيه عليه.
     */
}
