<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Services\Quota\QuotaGuard;
use App\Services\Quota\SpendCap;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template rendered on the first page load.
     */
    protected $rootView = 'app';

    /**
     * Determine the asset version, so a deploy invalidates stale client bundles.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response.
     *
     * المفاتيح snake_case مطابقةً لقاعدة البيانات، بلا تحويل — انظر CLAUDE.md §1.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app_name' => config('app.name'),
            /*
             * ★★ **مؤجَّلتان لا مباشرتان** — وهذا عطلٌ ظهر عند تشغيل التطبيق.
             *
             * `share()` تُنادى في `handle()` **قبل** `$next($request)`، أي
             * قبل أن يبلغ الطلبُ `SetAppLocale`. فالقيمةُ المباشرة تُجمَّد على
             * العربية مهما كانت لغةُ المستخدم، **بينما `lang` أدناه تُصيب**
             * لأنّها مغلَّفةٌ في دالّةٍ تُقوَّم عند الرسم.
             *
             * وكان أثرُها أنّ القالب يُرسل `<html lang="en">` صحيحاً ثمّ
             * تُعيده القوقعةُ إلى `ar` من هذه الخاصّية بعينها — صفحةٌ
             * إنجليزيةُ النصّ عربيةُ الاتّجاه.
             */
            /*
             * ★★ **رمزُ CSRF يُبثّ مع كلّ استجابة** — وهذا عطلٌ بلّغ عنه
             * مالك المنتج: «٤١٩ صفحة منتهية» بعد دقيقةٍ من فتح اللوحة.
             *
             * **والعلّة أنّ الرمز في `<meta>` يُرسم مرّةً واحدة** مع أوّل
             * وثيقة، **والدخولُ يجدّد الجلسة فيجدّد الرمز معها**. وInertia
             * لا تُعيد تحميل الوثيقة، فيبقى الوسمُ حاملاً رمزَ ما قبل
             * الدخول — ويسقط كلُّ طلبٍ لا يمرّ بـInertia.
             *
             * **وطلباتُ Inertia نفسُها سليمة**: تقرأ كوكي `XSRF-TOKEN`
             * وهو يتجدّد مع كلّ استجابة. فالساقطُ ما خرج عنها: نموذجُ
             * مبدّل اللغة، والفحصُ المسبق (T-16)، ومعاينةُ الهوية (T-85).
             *
             * ومؤجَّلةٌ كأختَيها أدناه — {@see share()} تُنادى قبل الوسائط.
             */
            'csrf_token' => fn (): string => csrf_token(),

            'locale' => fn (): string => app()->getLocale(),

            // اتّجاهُ الصفحة — تقرؤه القوقعة لتضع `dir` على `<html>` عند
            // تنقّل Inertia، فالقالبُ لا يُعاد رسمُه بعد أوّل تحميل (T-133).
            'direction' => fn (): string => Locale::parse(app()->getLocale())->direction(),

            // نصوص الواجهة من `lang/<لغة>` — SCREENS.md §القواعد العامّة: لا
            // نصّ مكتوب داخل مكوّن. وتُبثّ كاملةً لأنّها صغيرة وتُطلب في كل شاشة.
            'lang' => fn (): array => $this->translations(),
            'flash' => [
                'message' => fn (): ?string => $request->session()->get('message'),

                /*
                 * ★ **رابط الدعوة يُبثّ مرّةً ثمّ لا سبيل إليه** (T-33).
                 *
                 * فالجدول يحمل تعميةَ الرمز لا هو، فلا يُقرأ من صفحةٍ تُفتح
                 * بعدها. **ويُبثّ في الوميض لا في `props` الشاشة**: الدعوة
                 * تنتهي بتحويلٍ إلى `index`، ولو رُدّ في `props` الفعل
                 * لضاع في التحويل — وهي العلّة التي ظهرت عند التشغيل.
                 */
                'invitation_url' => fn (): ?string => $request->session()->get('invitation_url'),
            ],

            // شريط الحصّة السفليّ — SCREENS.md §هيكل الصفحة. ويُحسب من
            // `usage_ledger` وحده عبر `QuotaGuard`، لا بعدّ الصفوف (§4).
            'quota' => fn (): ?array => $this->quota($request),

            /*
             * **الشريط التحذيري ما دامت جلسة الانتحال قائمة** — SCREENS.md
             * §أ من لوحة المشرف. ويُبثّ مع كلّ استجابة لا في شاشةٍ بعينها:
             * المشرف المنتحل يتنقّل في شاشات الجهة كلِّها، وشريطٌ يظهر في
             * واحدةٍ منها يُنسى في البواقي — فيُظنّ ما يراه حالَ المنصّة.
             */
            'impersonating' => fn (): ?array => ImpersonationController::current($request),

            /*
             * **شريطُ وقف سقف الإنفاق** — T-151، وكشريط الانتحال أعلاه:
             * يُبثّ مع كلّ استجابة لا في شاشة الكلفة وحدها، فمن يفتح أيّ
             * شاشةٍ في لوحة المشرف يرى أنّ الطابور موقوف، لا من فتح
             * `/admin/costs` تحديداً وعرف أن ينظر.
             *
             * واسمُ الخاصّية غير `spend_cap` عمداً: تلك خاصّةُ شاشة الكلفة
             * وتحمل السقفين وما صُرف، وهذه حالة الوقف وحدها.
             */
            'spend_cap_halted' => fn (): ?array => $this->spendCapHalted(),

            // من الداخل الآن — يُعرض في الشريط العلوي مع مخرج الخروج.
            'auth' => [
                'admin' => fn (): ?array => ($admin = auth('admin')->user()) === null ? null : [
                    'name' => $admin->name,
                    'email' => $admin->email,
                ],
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'role' => $request->user()->role->value,
                    'tenant' => $request->user()->tenant?->name_ar,
                ],
            ],
        ];
    }

    /**
     * The tenant's monthly allowance, for the footer bar.
     *
     * ويعود `null` قبل تسجيل الدخول أو لجهةٍ بلا حدّ شهري — والشريط حينها
     * لا يُرسَم أصلاً، فلا يُعرض «٠ من ٠».
     *
     * @return array{used: int, limit: int}|null
     */
    private function quota(Request $request): ?array
    {
        $tenant = $request->user()?->tenant;

        if ($tenant === null || (int) $tenant->monthly_quota <= 0) {
            return null;
        }

        $decision = app(QuotaGuard::class)->monthlyQuota($tenant);

        return ['used' => $decision->used, 'limit' => $decision->allowance];
    }

    /** @return array{reason: string, halted_at: ?string}|null */
    private function spendCapHalted(): ?array
    {
        $cap = app(SpendCap::class);

        if (! $cap->isHalted()) {
            return null;
        }

        $details = $cap->haltDetails();

        return [
            'reason' => $details['reason'] ?? '',
            'halted_at' => $details['halted_at'] ?? null,
        ];
    }

    /**
     * The `lang/ar` files the interface reads from.
     *
     * `errors` تبقى خارجها: رسائل الأخطاء تُصاغ في الخادم وتصل جاهزةً في
     * الاستجابة، فبثُّها كلَّها إلى المتصفّح يكشف رسائل مسارات لا تخصّ
     * هذه الشاشة.
     *
     * @return array<string, array<string, mixed>>
     */
    private function translations(): array
    {
        $files = ['common', 'jobs', 'lectures', 'review', 'billing', 'auth', 'team', 'templates', 'locales', 'quiz'];

        $translations = array_combine(
            $files,
            array_map(static fn (string $file): array => (array) trans($file), $files),
        );

        /*
         * ★ **ولوحةُ المشرف عربيةٌ مهما كانت لغةُ المستخدم** — T-133.
         *
         * فهي أداةُ عملٍ داخلية لا واجهةُ زبون، ولم تُترجَم. ولو تُركت
         * تتبع اللغةَ لعادت `trans('admin')` بالمفتاح نفسِه على لغةٍ لا
         * ملفَّ لها، **فتُرسم اللوحةُ بمفاتيحَ عارية**. والتصريحُ بالعربية
         * هنا أصدقُ من الاتّكال على `fallback_locale`.
         */
        $translations['admin'] = (array) trans('admin', [], Locale::source()->value);

        // **وأداةُ «تحقّق» عربيةٌ كذلك** — T-181: المادّةُ التي تفحصها عربية.
        $translations['verify'] = (array) trans('verify', [], Locale::source()->value);

        return $translations;
    }
}
