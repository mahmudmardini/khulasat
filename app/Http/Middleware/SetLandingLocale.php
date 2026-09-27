<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * لغةُ صفحة التعريف — T-131.
 *
 * **ولا تُقرأ اللغة من `Accept-Language` ولا من كوكي.** الرابطُ وحده
 * يحكم: `/` عربية، و`/en` إنجليزية. فصفحةٌ تتبدّل بمتصفّح قارئها لا
 * تُشارَك — يُرسل أحدهم رابطاً فيرى صاحبُه غيرَ ما رأى، ولا يُخزَّن
 * جوابُها على الحافّة لأنّه يتبدّل بالرأس.
 *
 * **والمدخلُ `locale` يُقرأ للطلبات البريدية** — نموذجُ طلب الدعوة يرجع
 * بـ`back()`، ورسائلُ خطئه يجب أن تكون بلغة الصفحة التي أُرسل منها.
 * وهو مدخلٌ عابث لا يُوثق به، فـ{@see Locale::parse} تُسقط ما لا تعرفه
 * إلى العربية ولا ترمي.
 */
class SetLandingLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = Locale::parse($request->route('locale') ?? $request->input('locale'));

        app()->setLocale($locale->value);

        /*
         * ★★ **وتُحفظ في الجلسة لتعبر إلى اللوحة** — بلاغُ مالك المنتج.
         *
         * كان من يقرأ `/en` ثمّ يضغط «دخول» يجد شاشةَ الباب عربية: صفحةُ
         * التعريف تقرأ لغتها من **الرابط** ولا تكتبها، واللوحةُ تقرأ من
         * **الجلسة** — فلا شيء يصل بينهما، وتنقطع اللغة عند أوّل نقلة.
         *
         * **والقاعدة: لغةُ الصفحة التي جئتَ منها تصحبك.** فمن كان على `/en`
         * دخل بالإنجليزية، ومن كان على الجذر العربيّ دخل بالعربية — بلا
         * مفاجأة في الاتجاهين.
         *
         * **ولا تطغى على اختيارٍ محفوظ**: {@see SetAppLocale} تقدّم لغةَ
         * المستخدم على الجلسة، فمن اختار لغةَ لوحته لا تُبدّلها زيارةُ
         * صفحةِ تعريفٍ بغيرها.
         */
        $request->session()->put('locale', $locale->value);

        return $next($request);
    }
}
