<?php

declare(strict_types=1);

namespace App\Support\Quota;

use App\Enums\QuotaLimit;

/**
 * The answer to "may this run?" — المواصفة §11.
 *
 * وتحمل المستهلَك والحدّ معاً لا الرفضَ وحده: «اعرض المتبقّي للمستخدم **قبل**
 * التنفيذ لا بعده» (T-13). فالمستخدم يرى أنّه على وشك النفاد قبل أن ينفد.
 */
final readonly class QuotaDecision
{
    private function __construct(
        public bool $denied,
        public ?QuotaLimit $limit = null,
        public int $used = 0,
        public int $allowance = 0,
    ) {}

    public static function allowed(int $used = 0, int $allowance = 0): self
    {
        return new self(false, used: $used, allowance: $allowance);
    }

    public static function denied(QuotaLimit $limit, int $used = 0, int $allowance = 0): self
    {
        return new self(true, $limit, $used, $allowance);
    }

    public function permitted(): bool
    {
        return ! $this->denied;
    }

    /** كم بقي — ولا ينزل تحت الصفر ولو تجاوز الاستهلاك الحدّ. */
    public function remaining(): int
    {
        return max(0, $this->allowance - $this->used);
    }

    /** الرسالة العربية التي تُعرض، بلا رمز الحدّ. */
    public function message(): string
    {
        return $this->limit?->message() ?? '';
    }
}
