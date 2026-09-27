<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * استعادة كلمة المرور — SCREENS.md §1، والمهمّة T-32.
 *
 * ★ **وهي البابُ الوحيد لمن نسي كلمته.** فالحسابات بدعوة ولا تسجيل ذاتي
 * (§1)، فمن نسي **لا مدخل له إلى المنتج البتّة**: لا استعادة، ولا إنشاء
 * حسابٍ بديل. ومالكُ جهةٍ نسي كلمته يخرج من منتجه ولا يعود إلّا بأن
 * يراسلنا وينتظر.
 */
class PasswordResetController extends Controller
{
    /** ثلاث محاولات في الساعة لكلّ (بريد + عنوان). */
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 3600;

    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * يرسل الرابط — **ويقول الشيء نفسه في الحالين**.
     *
     * ★ **ولا يُفرَّق بين بريدٍ مسجَّل وغير مسجَّل**، وهي عين علّة
     * `auth.failed`: لو قيل «لا حساب بهذا البريد» لصار النموذج **أداةَ
     * استطلاع** يعرف بها من شاء أيُّ البُرد عندنا — ومن عرف أنّ بريد فلانٍ
     * مسجَّل عرف أنّ لفلانٍ جهةً عندنا، وهو وحده تسريب.
     */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $key = 'reset:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        /*
         * وحالةُ الوسيط لا تُعرض: `INVALID_USER` و`RESET_THROTTLED` كلاهما
         * يكشف شيئاً عن البريد. ويُقيَّد الإخفاق في السجلّ لا في الشاشة.
         */
        Password::sendResetLink($data);

        return back()->with('message', trans('auth.reset.sent'));
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->string('email'),
        ]);
    }

    /**
     * يبدّل الكلمة — **ولا يُدخل صاحبها تلقائياً**.
     *
     * ★ **والمنتج بحارسين** (`web` للجهة و`admin` للمشرف — المواصفة §10)،
     * ووسيطُ الاستعادة على مزوّد `users` وحده. فدخولٌ تلقائيّ هنا **يُدخل
     * المشرفَ العامّ على حارس الجهة**، فيرى لوحةً ليست له ويُحوَّل إلى
     * `/admin` فيُردّ. والتحويل إلى الدخول يُصيب الحارسَين معاً.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // **ثمانيةٌ وغير مسرّبة**: الطول وحده لا يمنع «12345678».
            'password' => ['required', 'confirmed', PasswordRule::min(8)->uncompromised()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    // **وتُبطَل جلسة «أبقني داخلاً» القديمة**: من بدّل كلمته
                    // بدّلها غالباً لأنّ جهازاً ضاع أو وصولاً يُخشى منه.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            /*
             * وهنا **يُقال السبب**: الرابط بيد صاحبه، ورسالةٌ غامضة تتركه
             * يعيد المحاولة برابطٍ منتهٍ إلى ما لا نهاية.
             */
            throw ValidationException::withMessages([
                'email' => trans('auth.reset.invalid'),
            ]);
        }

        return to_route('login')->with('message', trans('auth.reset.done'));
    }
}
