<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The tenant every scoped query is confined to for the current request or job.
 *
 * يُملأ من المصادقة في ResolveTenant، ويُضبط يدوياً في المهامّ الخلفية التي
 * لا مستخدم فيها. وهو مصدر الحقيقة الوحيد للحاجز في BelongsToTenant.
 *
 * القيمة null تعني «بلا حصر»، وهي حال المشرف العام (tenant_id فيه null)
 * وحال الأوامر السطرية. ولذلك **لا يُترك الحاجز وحده يحمي لوحة المشرف** —
 * لها حارس منفصل. انظر المواصفة §10.
 */
final class TenantContext
{
    private ?int $tenantId = null;

    private bool $forgotten = false;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->forgotten = false;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    /**
     * Run a callback with the tenant barrier lifted.
     *
     * صريح عمداً: رفع الحاجز يجب أن يظهر في الكود ويُقرأ في المراجعة،
     * لا أن يقع أثراً جانبياً لغياب مستخدم.
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->tenantId;
        $this->tenantId = null;
        $this->forgotten = true;

        try {
            return $callback();
        } finally {
            $this->tenantId = $previous;
            $this->forgotten = false;
        }
    }

    public function isLifted(): bool
    {
        return $this->forgotten;
    }
}
