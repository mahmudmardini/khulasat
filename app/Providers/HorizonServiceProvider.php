<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Restrict the Horizon dashboard to the platform operator.
     *
     * لوحة Horizon تكشف طوابير كلّ المستأجرين ومهامّهم، فهي للمشرف العام
     * وحده. وكانت مغلقة على الجميع في T-01 لأنّ حارس المشرف لم يكن قد بُني،
     * فصارت الآن على الحارس نفسه — المواصفة §10.
     */
    protected function gate(): void
    {
        // بلا تلميح نوع في التوقيع: البوّابة تُستدعى بمستخدم أيّ حارس،
        // وتلميح Admin يرمي TypeError على مستخدم جهة بدل أن يردّه false.
        Gate::define('viewHorizon', fn (mixed $user = null): bool => $user instanceof Admin);
    }
}
