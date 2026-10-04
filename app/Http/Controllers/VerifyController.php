<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Verify\SubmitVerifyCheck;
use App\Exceptions\VerifyRefused;
use App\Models\VerifyCheck;
use App\Support\Verify\CheckPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * أداة «تحقّق» — الصفحة العامّة، T-181. **بلا تسجيل دخول**، كالاعتراض.
 *
 * والصفحةُ عربيةٌ مهما كانت لغةُ الزائر: المادّةُ عربية، والنصوصُ في
 * `lang/ar/verify.php` وحدها.
 */
class VerifyController extends Controller
{
    public function create(): Response
    {
        return $this->page(null);
    }

    public function store(Request $request, SubmitVerifyCheck $submit): RedirectResponse
    {
        $text = self::validated($request);

        try {
            $check = $submit->handle($text, (string) $request->ip());
        } catch (VerifyRefused $refused) {
            throw ValidationException::withMessages(['text' => $refused->getMessage()]);
        }

        return redirect()->route('verify.show', $check->id);
    }

    public function show(VerifyCheck $check): Response
    {
        return $this->page($check);
    }

    /** ما تستطلعه الصفحة كلّ ثانيتين حتى يجهز التقرير. */
    public function status(VerifyCheck $check): JsonResponse
    {
        return response()->json(['check' => CheckPresenter::toArray($check)]);
    }

    /**
     * النصّ بعد تنظيفه وفحص طوله — **والرسائلُ من `verify.form`** لا من
     * رسائل التحقّق العامّة، فتقول للباحث ما يفعل لا ما أخطأ فيه.
     */
    public static function validated(Request $request): string
    {
        $request->merge(['text' => SubmitVerifyCheck::clean((string) $request->input('text', ''))]);

        $min = (int) config('khulasah.verify.min_chars');
        $max = (int) config('khulasah.verify.max_chars');

        $request->validate(
            ['text' => ['required', 'string', "min:{$min}", "max:{$max}"]],
            [
                'text.required' => __('verify.form.required'),
                'text.min' => __('verify.form.too_short', ['min' => $min]),
                'text.max' => __('verify.form.too_long', ['max' => $max]),
            ],
        );

        return (string) $request->input('text');
    }

    private function page(?VerifyCheck $check): Response
    {
        app()->setLocale('ar');

        return Inertia::render('Public/Verify', [
            'check' => $check === null ? null : CheckPresenter::toArray($check),
            'limits' => [
                'min_chars' => (int) config('khulasah.verify.min_chars'),
                'max_chars' => (int) config('khulasah.verify.max_chars'),
                'per_hour' => (int) config('khulasah.verify.per_hour'),
                'retention_days' => (int) config('khulasah.verify.retention_days'),
            ],
            'api_url' => url('/api/v1/verify'),
        ]);
    }
}
