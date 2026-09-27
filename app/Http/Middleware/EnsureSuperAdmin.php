<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the operator panel — المواصفة §10.
 *
 * يردّ 404 لا 403: وجود لوحة مشرف على هذا المسار **لا يُؤكَّد** لمن لا
 * يملكها، كما لا يُؤكَّد وجود سجلّ جهةٍ أخرى في TenantScope.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Auth::guard('admin')->check(), 404);

        return $next($request);
    }
}
