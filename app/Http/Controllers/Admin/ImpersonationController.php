<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * الدخول بهوية جهة للدعم — SCREENS.md §أ، والمواصفة §10.
 *
 * **أخطر صلاحية في النظام**، وSCREENS.md تصفها بأنّها «تُراقَب لا تُمنع».
 * فالمراقبة شرطُ إتاحتها لا زينةٌ عليها: بدءُ الجلسة وانتهاؤها وكم دامت،
 * كلُّها تُقيَّد.
 *
 * **والجلستان تقومان معاً**: حارس `admin` يبقى، ويُضاف إليه حارس `web`
 * بهوية مالك الجهة. فالمشرف يرى ما يراه صاحبُ الجهة، ويعود إلى لوحته
 * بضغطةٍ بلا إعادة دخول.
 */
class ImpersonationController extends Controller
{
    /** مفاتيح الجلسة — يقرؤها `HandleInertiaRequests` ليرسم الشريط. */
    public const TENANT = 'impersonating.tenant_id';

    public const STARTED = 'impersonating.started_at';

    public function __construct(private readonly RecordAudit $audit) {}

    public function start(Request $request, Tenant $tenant): RedirectResponse
    {
        /*
         * **مالك الجهة لا أوّل مستخدمٍ فيها.** والدعم يُطلب على ما يراه
         * صاحب القرار، ودخولٌ بهوية «مطّلع» يُري المشرف شاشاتٍ ناقصة
         * فيُشخّص عطلاً ليس عطلاً.
         */
        $owner = $tenant->users()
            ->where('role', Role::Owner->value)
            ->orderBy('id')
            ->first();

        if ($owner === null) {
            return back()->withErrors(['impersonate' => trans('admin.errors.no_owner')]);
        }

        $this->audit->handle(
            AuditAction::ImpersonationStarted,
            $tenant,
            ['user' => ['from' => null, 'to' => $owner->email]],
        );

        Auth::guard('web')->login($owner);

        $request->session()->put(self::TENANT, $tenant->id);
        $request->session()->put(self::STARTED, now()->toIso8601String());

        return redirect()->route('lectures.index');
    }

    public function stop(Request $request): RedirectResponse
    {
        $tenantId = $request->session()->pull(self::TENANT);
        $startedAt = $request->session()->pull(self::STARTED);

        // من لا جلسة انتحالٍ له لا يُخرَج من شيء — ولا يُقيَّد له صفّ.
        if ($tenantId === null) {
            return redirect()->route('lectures.index');
        }

        Auth::guard('web')->logout();

        $tenant = Tenant::query()->find($tenantId);

        $this->audit->handle(
            AuditAction::ImpersonationEnded,
            $tenant,
            ['minutes' => ['from' => null, 'to' => $this->minutesSince($startedAt)]],
        );

        return redirect()->route('admin.tenants.show', $tenantId);
    }

    /**
     * كم دامت الجلسة بالدقائق.
     *
     * **ودقيقةٌ أدنى ما يُقال**: جلسةٌ دامت خمساً وأربعين ثانية ليست «صفر
     * دقيقة»، وصفرٌ في سجلّ مراقبةٍ يُقرأ «لم يحدث شيء».
     *
     * والتقريبُ إلى الأقرب لا إلى أعلى: `diffInSeconds` تعود عدداً عشرياً،
     * فجلسةُ سبع دقائق تصير ٤٢٠٫٠٠٠١ ثانية، ويرفعها `ceil` إلى ثمانٍ.
     */
    private function minutesSince(mixed $startedAt): int
    {
        if (! is_string($startedAt)) {
            return 0;
        }

        return max(1, (int) round(Carbon::parse($startedAt)->diffInSeconds(now()) / 60));
    }

    /**
     * الجلسة القائمة، للشريط التحذيري.
     *
     * @return array{tenant: string, since: string}|null
     */
    public static function current(Request $request): ?array
    {
        $tenantId = $request->session()->get(self::TENANT);

        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant === null ? null : [
            'tenant' => $tenant->name_ar,
            'since' => (string) $request->session()->get(self::STARTED),
        ];
    }
}
