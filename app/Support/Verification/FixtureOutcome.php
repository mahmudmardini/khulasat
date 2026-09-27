<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\ReviewStatus;

/**
 * What actually happened to one fixture case.
 */
final readonly class FixtureOutcome
{
    /**
     * @param  list<string>  $failures  ما أخفق من التوقّعات، بالعربية، جاهزاً للعرض.
     */
    public function __construct(
        public FixtureCase $case,
        public VerificationResult $result,
        public ReviewStatus $reviewStatus,
        public array $failures,
    ) {}

    public function passed(): bool
    {
        return $this->failures === [];
    }

    /**
     * أنُشر هذا الشاهد؟ — §7-5.
     *
     * والمرور الآلي هو النشر: `pending` تقف، و`removed` تُحذف قبل الكتابة.
     */
    public function published(): bool
    {
        return $this->reviewStatus === ReviewStatus::AutoPassed;
    }

    /** وصف مختصر لما جرى فعلاً — للعمود «الفعلي» في تقرير الأمر. */
    public function actual(): string
    {
        $parts = [$this->result->status->value, $this->reviewStatus->value];

        if ($this->result->sourceRef !== null) {
            $parts[] = $this->result->sourceRef;
        }

        return implode(' · ', $parts);
    }

    /** وصف مختصر لما كان منتظراً. */
    public function expected(): string
    {
        $parts = [];

        foreach ($this->case->expect as $key => $value) {
            $parts[] = $key.'='.match (true) {
                is_bool($value) => $value ? 'نعم' : 'لا',
                default => (string) $value,
            };
        }

        return implode(' · ', $parts);
    }
}
