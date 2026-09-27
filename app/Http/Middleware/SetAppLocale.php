<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغةُ لوحة الجهة وشاشات الباب — T-133.
 *
 * **واللغةُ صفةُ المستخدم لا صفةُ الرابط.** بادئةُ المسار صلحت لصفحةِ
 * تعريفٍ واحدة (T-131) — تُفهرَس وتُشارَك — ولا تصلح للوحةٍ فيها مئاتُ
 * نداءات `route()`: كلُّ نداءٍ وتحويلٍ يلزمه حملُ اللغة، وذلك سطحُ
 * انحدارٍ واسعٌ لا يشتري شيئاً. فلا أحدَ يُشارك رابط لوحته.
 *
 * ★ **والترتيب: المستخدمُ ثمّ الجلسةُ ثمّ العربية.** وشاشاتُ الباب قبل
 * الجلسة فلا مستخدمَ يُسأل — فتعمل الجلسةُ وحدها هناك، ومتى دخل غلبت
 * لغتُه المحفوظة على ما اختاره ضيفاً.
 *
 * **ولوحةُ المشرف لا تمرّ من هنا**: هي تحت `/admin` بحارسها المنفصل،
 * وتبقى عربيةً بقرار T-133 — أداةُ عملٍ داخلية لا واجهةُ زبون.
 */
class SetAppLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Auth::guard('web')->user()?->locale
            ?? Locale::parse($request->session()->get('locale'));

        app()->setLocale($locale->value);

        return $next($request);
    }
}
