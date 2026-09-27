<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Team\AcceptInvitation;
use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * قبول الدعوة — SCREENS.md §10، والمهمّة T-33.
 *
 * **وهو خارج المصادقة**: المدعوّ ليس في المنتج بعد. **وهذا هو المسار
 * الوحيد الذي يُنشأ به حساب** — ولا تسجيل ذاتي (§1)، فالرابط بديلُه.
 */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->find($token);

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            /*
             * **والحال تُقال ولا يُردّ ٤٠٤.** فرابطٌ منتهٍ يردّ «غير موجود»
             * يُقرأ عطلاً عندنا، ويُراسَل به من دعا فيظنّ المنتج معطوباً.
             * والصفحة تقول «انتهت» وتدلّه على من يدعوه ثانيةً.
             */
            'valid' => $invitation !== null,
            'email' => $invitation?->email,
            'tenant' => $invitation?->tenant?->name_ar,
            'role' => $invitation?->role->value,
        ]);
    }

    public function store(Request $request, AcceptInvitation $accept): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->uncompromised()],
        ]);

        try {
            $result = $accept->handle($data['token'], $data['name'], $data['password']);
        } catch (RuntimeException $refused) {
            return back()->withErrors(['token' => $refused->getMessage()]);
        }

        /*
         * **ويُدخَل هنا تلقائياً — بخلاف استعادة كلمة المرور (T-32).**
         *
         * والفرق أنّ هذا حسابٌ **أُنشئ الآن على حارس الجهة قطعاً**: لا يكون
         * مشرفاً عامّاً بحال، فلا يقع الالتباس الذي مُنع هناك. ومطالبةُ من
         * وضع كلمته قبل ثانية بأن يكتبها ثانيةً مطالبةٌ بلا سبب.
         */
        Auth::guard('web')->login($result['user']);

        $request->session()->regenerate();

        return to_route('lectures.index')->with('message', trans('team.welcome'));
    }

    private function find(string $token): ?TeamInvitation
    {
        $invitation = TeamInvitation::acrossTenants()
            ->with('tenant')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation?->isPending() === true ? $invitation : null;
    }
}
