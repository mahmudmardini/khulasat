<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\TenantMismatch;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Confines a model to one tenant — المواصفة §10.
 *
 * يفعل ثلاثة أشياء:
 *   ١. حاجز على كلّ استعلام (TenantScope).
 *   ٢. ملء tenant_id تلقائياً عند الإنشاء.
 *   ٣. رفض الكتابة لجهة غير جهة السياق، إنشاءً كان أو تحديثاً.
 *
 * والثالث هو ما يمنع التسرّب في الاتجاه المعاكس: الحاجز يمنع **قراءة**
 * سجلّ جهة أخرى، وهذا يمنع **كتابة** سجلّ باسمها.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->tenant_id === null) {
                $model->tenant_id = $context->id();

                return;
            }

            if ($context->has() && (int) $model->tenant_id !== $context->id()) {
                throw TenantMismatch::forWrite(
                    $model::class,
                    (int) $model->tenant_id,
                    $context->id(),
                );
            }
        });

        static::updating(function (Model $model): void {
            if (! $model->isDirty('tenant_id')) {
                return;
            }

            throw TenantMismatch::forWrite(
                $model::class,
                $model->tenant_id === null ? null : (int) $model->tenant_id,
                (int) $model->getOriginal('tenant_id'),
            );
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Query across every tenant. **صريح عمداً** — انظر TenantContext::withoutScope.
     *
     * @return Builder<static>
     */
    public static function acrossTenants(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }
}
