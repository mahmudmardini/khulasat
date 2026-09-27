<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceQuota;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * ★ **الوسيطُ الأماميّ موثوق، ومضيفُه لا** — T-124.
         *
         * التطبيق خلف nginx ينهي TLS ويمرّر `X-Forwarded-Proto` (DEPLOY.md
         * §5). وبلا هذا يرى Laravel الطلبَ `http` فيبني روابط مطلقة بـ`http`،
         * **ولا يضع `Secure` على كعكة الجلسة** وإن طُلب — فالكعكةُ الآمنة
         * على اتّصالٍ يُرى غيرَ آمن لا تُرسَل.
         *
         * و`*` تصحّ هنا **بشرطٍ منصوصٍ في DEPLOY.md §3**: الحاوية تُصغي على
         * `127.0.0.1:8000` وحدها، فلا يبلغها طلبٌ إلّا من nginx. ومن نشر
         * بلا هذا الشرط فتح لكلّ زائرٍ أن يزعم عنواناً وبروتوكولاً.
         *
         * ★ **و`X-Forwarded-Host` مستثنًى عمداً** — وهو في افتراض Laravel
         * موثوق. وnginx يمرّر ما لم يعرفه كما جاء، فترويسةٌ ملفَّقة تجعل
         * `url()` تبني على مضيف المهاجم: **رابطُ استعادة كلمة السرّ يصل
         * صاحبَه بنطاقٍ ليس نطاقنا**. والمضيفُ الصحيح يصل في `Host` نفسه
         * (`proxy_set_header Host $host`)، فلا حاجة إليها أصلاً.
         */
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        /*
         * ★ **`ResolveTenant` قبل `SubstituteBindings`** — وهذا ترتيبٌ حاكم
         * لا تفصيل.
         *
         * ربطُ المسار بالنموذج (`/jobs/{job}`) يجري في `SubstituteBindings`،
         * وهو يستعلم بـ`newQuery()` فيُطبَّق حاجزُ `BelongsToTenant`. **لكنّ
         * الحاجز يقرأ من `TenantContext`، وهو فارغٌ حتى يملأه `ResolveTenant`
         * من المستخدم.** فلو أُلحِق بعده لجرى الربط بلا جهة، **فحمّلت جهةٌ
         * مهمّةَ جهةٍ أخرى برقمها** — والحاجز قائمٌ ولا يحجب شيئاً.
         *
         * وكان هذا واقعاً حتى كشفه اختبار T-16. ويُنزَع الربط من موضعه
         * الافتراضي ويُعاد بعد `ResolveTenant`، ويبقى الاثنان بعد
         * `StartSession` لأنّ المستخدم يُقرأ من الجلسة.
         */
        $middleware->web(
            remove: [SubstituteBindings::class],
            append: [
                // بعد المصادقة عمداً — انظر ResolveTenant.
                ResolveTenant::class,
                SubstituteBindings::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        $middleware->alias([
            'admin' => EnsureSuperAdmin::class,

            /*
             * **الحصّة وسقف الإنفاق قبل الطابور** — المواصفة §11 وCLAUDE.md
             * §2 القاعدة الخامسة (T-29).
             *
             * وكان الحاجز مبنيّاً غيرَ مسجَّل، فيُرى في الشيفرة ولا يحرس
             * شيئاً. **وحاجزٌ موجودٌ لا يعمل أخطر من غيابه.**
             *
             * ويُعلَّق على المسارات التي تضع مهمّةً في الطابور وحدها،
             * ويحرس ذلك اختبارٌ يقرأ مواضع `RunSummaryPipeline::dispatch`
             * من الشيفرة نفسها — فمسارٌ جديد يضع في الطابور بلا حاجز
             * يسقط الاختبار، ولا يُنتظر أن ينتبه مراجع.
             */
            'quota' => EnforceQuota::class,
        ]);

        // شاشة الدخول من عمل مهمّة أخرى (SCREENS.md §1)، والمسار يُذكر
        // هنا نصّاً لا باسم مسارٍ غير موجود: `auth` بلا هذا ترمي استثناءً
        // بدل أن تُحوّل، فيرى الزائرُ خطأ خادم مكان صفحة دخول.
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
