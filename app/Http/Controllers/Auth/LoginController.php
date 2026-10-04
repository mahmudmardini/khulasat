<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\JudgeAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * تسجيل الدخول — SCREENS.md §1، والمواصفة §10.
 *
 * **حقلان وزر، ولا تسجيل ذاتي**: الحسابات بدعوة، فلا مسار `register` ولا
 * يُبنى. ومن أراد حساباً فمن مالك جهته أو من المشرف العام.
 */
class LoginController extends Controller
{
    /** خمس محاولات لكلّ (بريد + عنوان) — ثمّ انتظار. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function show(Request $request): Response
    {
        $key = $request->query('judge');

        return Inertia::render('Auth/Login', [
            // أزرارُ لجنة التحكيم — T-186. **ولا يُرسل شيءٌ منها لمن لا يحقّ له**،
            // فلا يُعرف من صفحة الدخول العادية أنّ لها باباً آخر.
            'judges' => JudgeAccess::allows($key) && ($roles = JudgeAccess::roles()) !== []
                ? ['key' => is_string($key) ? $key : null, 'roles' => $roles]
                : null,
        ]);
    }

    /**
     * دخولُ لجنة التحكيم بنقرة — T-186، قرار مالك المنتج.
     *
     * يُدخل أحد الحسابات الثلاثة في {@see JudgeAccess} بأعيانها، لا غيرها.
     * **وما لا يحقّ له الدخولُ يُردّ 404** — كأن لا باب هنا.
     */
    public function judge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string'],
            'judge' => ['nullable', 'string'],
        ]);

        abort_unless(JudgeAccess::allows($data['judge'] ?? null), 404);

        $user = JudgeAccess::user($data['role']);

        abort_if($user === null, 404);

        $panel = $user->isSuperAdmin();

        Auth::guard($panel ? 'admin' : 'web')->login($user);

        $request->session()->regenerate();

        return redirect($panel ? '/admin' : route('lectures.index'));
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $remember = $request->boolean('remember');

        /*
         * ★ **حارس المشرف يُجرَّب أوّلاً — T-21.**
         *
         * وكان `Auth::attempt` وحده، وهو على الحارس الافتراضي `web`. فيدخل
         * المشرف العامّ على حارس الجهة ثمّ يُحوَّل إلى `/admin`، و`/admin`
         * يشترط حارس `admin` فيردّ **404**. فاللوحة كانت محروسةً لا يبلغها
         * أحد — عين ثغرة T-16ب: حارسٌ ومسارٌ بلا باب.
         *
         * والترتيب هو الحلّ كلُّه: مزوّد `admins` مقصورٌ على
         * `tenant_id IS NULL`، فمستخدم الجهة **لا يُصادَق عليه هنا أصلاً**
         * ولو صحّت كلمته، ويسقط إلى الحارس الذي يخصّه. والعكسُ غير صحيح:
         * مزوّد `users` يشمل الجميع، فلو جُرّب `web` أوّلاً لدخل المشرف
         * على حارس الجهة — وهو ما كان يقع.
         */
        $panel = Auth::guard('admin')->attempt($credentials, $remember);

        if (! $panel && ! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            /*
             * **رسالة واحدة لا اثنتان.** والتفريق بين «لا حساب بهذا البريد»
             * و«كلمة المرور خاطئة» يُخبر المهاجم أيّ البُرد مسجَّل عندنا،
             * فيصير نموذج الدخول أداةَ استطلاع.
             *
             * والحارسان هنا لا يُفرّقان كذلك: من أخطأ لا يُعلَم أكان
             * يقصد لوحةَ مشرفٍ أم لوحةَ جهة.
             */
            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($key);

        // تدوير معرّف الجلسة بعد النجاح — وإلّا صحّت جلسةٌ زرعها مهاجم قبله.
        $request->session()->regenerate();

        // المشرف العام في لوحةٍ منفصلة — المواصفة §10.
        return redirect()->intended($panel ? '/admin' : route('lectures.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /*
         * **الحارسان معاً.** والجلسة تُبطَل بعدهما، فيكفي واحدٌ عملياً —
         * لكنّ الخروج الصريح من كليهما يجعل الشيفرة تقول ما تفعله، ويُخرج
         * المشرفَ المنتحلَ من هويّة الجهة ومن لوحته بضغطةٍ واحدة.
         */
        Auth::guard('web')->logout();
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function throttleKey(Request $request): string
    {
        return 'login:'.mb_strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
