<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fills the tenant context from the authenticated user.
 *
 * يعمل **بعد** المصادقة: قبلها لا مستخدم يُقرأ منه، ولو ضُبط الحاجز أوّلاً
 * لمنع مزوّد المصادقة من قراءة المستخدم نفسه.
 *
 * والمشرف العام (tenant_id فيه null) يبقى بلا حصر، ولوحته محروسة بحارس
 * منفصل لا بهذا الحاجز.
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(TenantContext::class)->set($request->user()?->tenant_id);

        return $next($request);
    }
}
