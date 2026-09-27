<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * The primary tenant barrier — المواصفة §10.
 *
 * يُقيَّد كلّ استعلام بجهة السياق الحالي. وهذا هو الحاجز، لا الشرط اليدوي
 * في المتحكّم: الشرط اليدوي يُنسى في متحكّم واحد فيتسرّب كلّ شيء.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->has()) {
            return;
        }

        $builder->where(
            $model->qualifyColumn('tenant_id'),
            $context->id(),
        );
    }
}
