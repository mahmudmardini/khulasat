<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * تبديلُ لغة اللوحة — T-133.
 *
 * **ويُكتب في الموضعين معاً.** الجلسةُ تخدم شاشات الباب (لا مستخدمَ بعد)،
 * والعمودُ يخدم ما بعد الدخول — فيبقى الاختيار بعد خروجٍ ودخول، ومن
 * جهازٍ آخر. ومن بدّل ضيفاً ثمّ دخل، غلبت لغتُه المحفوظة، وهو الصواب:
 * اختيارُه القديم أوثقُ من نقرةٍ عابرة على شاشة الدخول.
 *
 * ★ **ولا يُقيَّد بـ`validate`**: {@see Locale::parse} تُسقط ما لا تعرفه
 * إلى العربية ولا ترمي، ولغةٌ عابثة في الطلب تُهمَل ولا تُعطّل صفحة.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = Locale::parse($request->input('locale'));

        $request->session()->put('locale', $locale->value);

        Auth::guard('web')->user()?->forceFill(['locale' => $locale->value])->save();

        return back();
    }
}
