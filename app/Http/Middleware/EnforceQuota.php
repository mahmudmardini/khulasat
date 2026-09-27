<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\QuotaLimit;
use App\Models\Tenant;
use App\Services\Quota\QuotaGuard;
use App\Services\Quota\SpendCap;
use App\Support\Quota\QuotaDecision;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request before anything is queued — المواصفة §11.
 *
 * **قبل وضع المهمّة في الطابور، لا بعده.** والفحص بعد الوضع يعني أنّ
 * التوكنز صُرفت، «وهذا الخطأ يكلّف مالاً حقيقياً» (T-13).
 *
 * ولذلك موضعُه Middleware لا داخل المهمّة: المهمّة تعمل في عاملٍ بعد
 * الوضع، وكلّ فحصٍ فيها متأخّر بحكم موضعه. **وهو كذلك نصّ CLAUDE.md §2
 * القاعدة الخامسة** — وقد كان مبنيّاً غيرَ موصول حتى T-29، فكان الحارس
 * يُرى في الشيفرة ولا يحرس شيئاً.
 *
 * ### وضعان، والفرق بينهما فرقُ ما يُصرف
 *
 * `quota` — **عملٌ جديد**: سقف الإنفاق، وتعليق الاشتراك، والحصّة الشهرية،
 * والسقف اليومي. وهو ما يُعلَّق على إنشاء ملخّصٍ وعلى إعادة توليده.
 *
 * `quota:cap` — **سقف الإنفاق وحده**: لعملٍ **دُفع ثمنُه سلفاً** ويُستأنف —
 * استئنافُ الخطّ بعد المراجعة، وإعادةُ تشغيل مهمّةٍ من لوحة المشرف. فحصّةٌ
 * تُخصم ثانيةً عن ملخّصٍ واحد خصمٌ مرّتين، **وسقفُ الإنفاق يبقى** لأنّه
 * «يوقف الطابور كلّه» (§11) ولا يستثني عملاً قائماً: بلوغُه يعني أنّ
 * الصرف وقف عندنا، لا أنّ جهةً بلغت حدَّها.
 *
 * `quota:extra` — **صرفٌ جديد على ملخّصٍ قائم** (T-166): إضافةُ لغةٍ نداءُ
 * ترجمةٍ يُدفع، فسقفُ الإنفاق يُفحص **وتعليقُ الاشتراك كذلك** — فالموقوفُ
 * لا يُنتج (T-23). **ولا حصّة**: الملخّصُ واحد، بقرار مالك المنتج.
 */
class EnforceQuota
{
    /** استئنافُ عملٍ قائم: سقف الإنفاق وحده. */
    public const CAP_ONLY = 'cap';

    /** صرفٌ إضافيّ على ملخّصٍ قائم: السقفُ والتعليق، بلا حصّة. */
    public const EXTRA = 'extra';

    public function __construct(
        private readonly QuotaGuard $guard,
        private readonly SpendCap $spendCap,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'full'): Response
    {
        $decision = $this->decide($mode);

        if ($decision === null || $decision->permitted()) {
            return $next($request);
        }

        return $this->refuse($request, $decision);
    }

    private function decide(string $mode): ?QuotaDecision
    {
        /*
         * **سقف الإنفاق أوّلاً وبلا جهة.** فهو وقفٌ عامّ عندنا لا حدُّ
         * جهةٍ بعينها، ويُفحص حتى للمشرف العامّ — وهو بلا سياق جهةٍ أصلاً.
         */
        if ($this->spendCap->isHalted()) {
            return QuotaDecision::denied(QuotaLimit::SpendCap);
        }

        if ($mode === self::CAP_ONLY) {
            return null;
        }

        $tenantId = $this->tenants->id();

        // بلا جهةٍ لا حدود تُقاس. والمصادقةُ حارسٌ آخر يسبق هذا.
        if ($tenantId === null) {
            return null;
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return null;
        }

        if ($mode === self::EXTRA) {
            return $tenant->isSuspended() ? QuotaDecision::denied(QuotaLimit::Suspended) : null;
        }

        return $this->guard->forNewSummary($tenant);
    }

    private function refuse(Request $request, QuotaDecision $decision): Response
    {
        // ٤٢٩ لحدّ استعمالٍ يُرفع بمرور الشهر أو بالشراء، و٤٠٣ لتعليق
        // اشتراكٍ لا يرفعه انتظار — {@see QuotaLimit::httpStatus()}.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $decision->message(),
                'limit' => $decision->limit?->value,
                'used' => $decision->used,
                'allowance' => $decision->allowance,
            ], $decision->limit?->httpStatus() ?? Response::HTTP_TOO_MANY_REQUESTS);
        }

        /*
         * **و`withInput` لا تُنسى.** فالحاجز يقف قبل المتحكّم، ونموذجُ
         * «ملخّص جديد» فيه عشرة حقول ملأها المستخدم — وردُّه فارغاً عقوبةٌ
         * على حدٍّ ليس ذنبَه، ويُفقد الرسالةَ نفسها معناها.
         */
        return back()->withInput()->withErrors(['quota' => $decision->message()]);
    }
}
