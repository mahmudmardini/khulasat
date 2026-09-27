<?php

declare(strict_types=1);

use App\Actions\Auth\ResolveLandingDestination;
use App\Actions\Landing\FindShowcaseSummaryUrl;
use App\Actions\Landing\LoadShowcaseSummary;
use App\Http\Controllers\Admin\AdminJobController;
use App\Http\Controllers\Admin\CostController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ImpersonationController;
// باسمٍ بديل: البابان يحملان الاسمَ نفسه — نموذجُ الزائر في `Public`
// وشاشةُ المشرف في `Admin` (T-135). واللاحقةُ هنا لا في اسم الصنف.
use App\Http\Controllers\Admin\InviteRequestController as AdminInviteRequestController;
use App\Http\Controllers\Admin\ModelConfigController;
use App\Http\Controllers\Admin\SpendCapController;
use App\Http\Controllers\Admin\TakedownController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Public\ComplaintController;
use App\Http\Controllers\Public\InviteRequestController;
use App\Http\Controllers\Public\PageViewController;
use App\Http\Controllers\Public\ShareCardController;
use App\Http\Controllers\Public\ShowPublishedSummaryController;
use App\Http\Middleware\SetAppLocale;
use App\Http\Middleware\SetLandingLocale;
use App\Support\Landing\LandingView;
use Illuminate\Support\Facades\Route;

/*
 * صفحة التعريف العامّة — **جذرُ الموقع** (T-113).
 *
 * Blade لا Inertia: صفحةٌ واحدة ساكنة لزائرٍ لا حساب له، وتحميلُ قوقعة
 * React كاملةً لأجلها كلفةٌ بلا مقابل. ولا مصادقة عليها بحال.
 *
 * والمسجَّلُ يُصرف إلى فهرسه، فلا يرى صفحةَ بيعٍ كلّما فتح الموقع.
 */
Route::get('/', function (
    FindShowcaseSummaryUrl $showcaseUrl,
    LoadShowcaseSummary $showcase,
    ResolveLandingDestination $destination,
) {
    return ($to = $destination->handle()) !== null
        ? redirect($to)
        : response()->view('landing', LandingView::data($showcaseUrl, $showcase));
})->middleware(SetLandingLocale::class)->name('home');

/*
 * صفحةُ التعريف بالإنجليزية والتركية والروسية — T-131.
 *
 * **والجذرُ يبقى للعربية بلا بادئة.** فهو الرابطُ المنشور و`canonical`
 * القائم، ووضعُ العربية تحت `/ar` يكسر كلَّ رابطٍ سبق.
 *
 * **ومقطعٌ واحد لا يزاحم الصفحةَ المنشورة**: مسارُها آخرَ الملفّ يشترط
 * مقطعين (`/{tenantSlug}/{summarySlug}`)، فـ`/en` لا يبلغه أصلاً.
 * وتبقى `/en/خلاصة` لجهةٍ سبيكتُها `en` على حالها.
 *
 * ★ **وصرفُ المصادَق واحدٌ في الموضعين** — `ResolveLandingDestination`
 * نفسُها التي يناديها الجذر (T-129). فالمشرفُ يفتح `/en` فيبلغ `/admin`
 * كما لو فتح `/`، ولا يبقى حارسٌ يعرفه في مسارٍ ويجهله في آخر.
 */
Route::get('/{locale}', function (
    string $locale,
    FindShowcaseSummaryUrl $showcaseUrl,
    LoadShowcaseSummary $showcase,
    ResolveLandingDestination $destination,
) {
    return ($to = $destination->handle()) !== null
        ? redirect($to)
        : response()->view('landing', LandingView::data($showcaseUrl, $showcase));
})
    ->whereIn('locale', ['en', 'tr', 'ru'])
    ->middleware(SetLandingLocale::class)
    ->name('home.locale');

/*
 * `/login` إلى بابه تحت `/panel` — T-129.
 *
 * T-113 نقل المصادقة كلَّها تحت `/panel`، **والرابطُ المتعارف عليه بقي
 * يُكتب ويُحفظ ويُرسل**. و`/login` مقطعٌ واحد فلا يبلغه حتى الجذرُ العامّ
 * في آخر هذا الملفّ — يشترط مقطعين — فكان يسقط في 404 وحده.
 *
 * وتحويلٌ دائم لا نسخةُ مسارٍ ثانية: البابُ واحدٌ حيث وضعه T-113، وهذا
 * لافتةٌ إليه.
 */
Route::permanentRedirect('/login', '/panel/login');

/*
 * الاعتراض العامّ — **بلا تسجيل دخول** (T-24). ورابطه في تذييل كل صفحة
 * منشورة، ومن يعترض غالباً ليس زبوناً: شيخٌ نُسب إليه كلام، أو قارئٌ رأى
 * تخريجاً خطأً. واشتراطُ التسجيل يُغلق الباب على من فُتح لأجله.
 */
Route::get('/complaint', [ComplaintController::class, 'create'])->name('complaint.create');
Route::post('/complaint', [ComplaintController::class, 'store'])
    ->middleware('throttle:10,60')
    ->name('complaint.store');

/*
 * شاهدة عدّ الفتحات — **بلا تسجيل دخول** (T-31)، كمسار الاعتراض.
 *
 * فالطالب قارئُ الصفحة المنشورة لا صاحبُها، وهو على نطاق الجهة أو على
 * الحافّة. **ولا كوكي ولا بصمة**: الصفّ المكتوب مجموعٌ يوميّ لا أثرَ فيه
 * لمن فتح.
 *
 * والخنق عالٍ عمداً: صفحةٌ تُشارَك في مجموعةٍ تُفتح مئاتٍ في الدقيقة من
 * مخارج شبكةٍ واحدة، **وحدٌّ ضيّق هنا يُسقط عدّاً صحيحاً** لا هجوماً.
 */
/*
 * ★ **واللسانُ في المسار — T-140.** فصفحاتُ اللغات كانت تطلب الشاهدةَ
 * نفسَها، فيُجمع الثلاثةُ في صفٍّ ولا يُعرف أيُّ لسانٍ قُرئ.
 */
Route::get('/v/{job}/{type}/{locale}.gif', PageViewController::class)
    ->where(['job' => '[0-9]+', 'type' => '[a-z_]+', 'locale' => '[a-z]{2}'])
    ->middleware('throttle:600,1')
    ->name('page-views.record');

/*
 * **والصيغةُ القديمة تبقى ولا تُحوَّل** (T-140): ملفّاتٌ منشورةٌ قبلها
 * تحملها على الأقراص وفي أيدي الناس، **ومنها ما شاركه الناس فلا يُكسر**.
 * وما تكتبه يقع في «غير مبيَّنة» لا في لسانٍ مخمَّن — و`khulasah:refresh-pages`
 * يُنهيها متى أُعيد رسمُ المنشور كلِّه.
 */
Route::get('/v/{job}/{type}.gif', PageViewController::class)
    ->where(['job' => '[0-9]+', 'type' => '[a-z_]+'])
    ->middleware('throttle:600,1')
    ->name('page-views.record.legacy');

/*
 * بطاقةُ مشاركة الخلاصة المنشورة — T-144. **تُجيب بصورةٍ دائماً** لخلاصةٍ
 * منشورة (المولَّدةِ أو بطاقةِ المنصّة)، و٤٠٤ لما سواها. والخنقُ عالٍ كالشاهدة:
 * رابطٌ يُشارَك في مجموعة تستدعيه مُعايناتُ التطبيقات دفعةً.
 */
Route::get('/share/{job}/{locale}.png', ShareCardController::class)
    ->where(['job' => '[0-9]+', 'locale' => '[a-z]{2}'])
    ->middleware('throttle:600,1')
    ->name('share-card');

/*
 * طلبُ الدعوة من صفحة التعريف — **بلا تسجيل دخول** (T-113).
 *
 * ويبقى على الجذر كالاعتراض: نموذجُ الصفحة العامّة، وصاحبُه لا حساب له
 * فلا معنى لوضعه تحت `/panel`.
 */
Route::post('/invite', [InviteRequestController::class, 'store'])
    ->middleware(['throttle:10,60', SetLandingLocale::class])
    ->name('invite.store');

/*
 * لوحةُ الجهة تحت `/panel` — T-113. والملفُّ في `routes/panel.php`.
 */
Route::prefix('panel')
    ->middleware(SetAppLocale::class)
    ->group(base_path('routes/panel.php'));

/*
 * لوحة المشرف العام — حارس منفصل، المواصفة §10 وSCREENS.md القسم الثالث (T-21).
 *
 * **وكلّ ما يغيّر حالاً هنا يُقيَّد في `admin_audit_log`.** ولا بوّابة دفع
 * في هذه المرحلة، فشاشةُ الجهة هي كامل آلية التفعيل والترقية — ورفعُ حصّةٍ
 * بعد تحويلٍ بنكيّ حدثٌ ماليّ لا ضبطُ إعداد.
 */
Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('home');

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::put('/tenants/{tenant}/limits', [TenantController::class, 'updateLimits'])->name('tenants.limits');

    // تطبيق شريحة — T-23. حدثٌ ماليّ كالحدود، ويُقيَّد بسببه المكتوب.
    Route::put('/tenants/{tenant}/plan', [TenantController::class, 'applyPlan'])->name('tenants.plan');
    Route::put('/tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');
    Route::put('/tenants/{tenant}/verification', [TenantController::class, 'updateVerification'])->name('tenants.verification');

    /*
     * «صلاحية خطيرة تُراقَب لا تُمنع» — SCREENS.md §أ. فالبدء والانتهاء
     * كلاهما يُقيَّد، ومعه كم دامت الجلسة.
     */
    Route::post('/tenants/{tenant}/impersonate', [ImpersonationController::class, 'start'])->name('impersonate.start');

    Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', [AdminJobController::class, 'show'])->name('jobs.show');
    // إعادةُ المشرف لا تُخصم من الجهة — العطل عندنا. **ويبقى سقفُ الإنفاق.**
    Route::post('/jobs/{job}/retry', [AdminJobController::class, 'retry'])
        ->middleware('quota:cap')
        ->name('jobs.retry');
    Route::post('/jobs/{job}/cancel', [AdminJobController::class, 'cancel'])->name('jobs.cancel');

    /*
     * الاعتراضات — SCREENS.md §هـ (T-28). **وهي النصف الغائب من T-24**:
     * الشكوى كانت تُسجَّل ولا يراها أحد، ومهلةُ الـ48 ساعة تمضي بلا علم.
     */
    Route::get('/takedowns', [TakedownController::class, 'index'])->name('takedowns.index');
    Route::put('/takedowns/{complaint}', [TakedownController::class, 'update'])->name('takedowns.update');

    /*
     * طلباتُ الدعوة — T-135، والنصفُ الغائب من T-113.
     *
     * وموضعُها في «التشغيل» مع الاعتراضات لا في «المنصّة»: صندوقٌ يُفرَّغ
     * كلّ يوم، وصاحبُ الطلب ينتظر جواباً.
     */
    Route::get('/invites', [AdminInviteRequestController::class, 'index'])->name('invites.index');
    Route::put('/invites/{inviteRequest}', [AdminInviteRequestController::class, 'update'])->name('invites.update');

    Route::get('/models', [ModelConfigController::class, 'index'])->name('models.index');
    Route::put('/models/{config}', [ModelConfigController::class, 'update'])->name('models.update');

    Route::get('/costs', CostController::class)->name('costs.index');

    // وقفُ سقف الإنفاق أو رفعُه — T-151. `quota:cap` لا يحرسه: هو الحاجزُ نفسُه.
    Route::put('/spend-cap', [SpendCapController::class, 'update'])->name('spend-cap.update');
});

/*
 * إنهاء الانتحال — **خارج حارس `admin` عمداً وبمسار `/admin`**.
 *
 * فالمشرف حين ينتحل يتصفّح شاشات الجهة، وزرّ «اخرج من الهوية» يُضغط من
 * هناك. ولو حُرس بـ`EnsureSuperAdmin` لعمل — الجلستان قائمتان معاً — لكنّ
 * الحارس الحقيقيّ هنا وجودُ جلسة انتحالٍ أصلاً، وهو ما يفحصه الفعل.
 */
Route::post('/admin/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->name('admin.impersonate.stop');

/*
 * الصفحة المنشورة — رابطٌ نظيف، T-127. **آخر المسارات عمداً**: بلا هذا
 * الترتيب يبتلع الجذرُ العامّ مسارات `/admin` و`/panel` وأخواتها، لأنّ
 * Laravel يُطابق أوّل مسارٍ يوافق لا الأدقّ.
 *
 * والألفاظُ المحجوزة أعلاه (`admin`, `panel`, `complaint`, `invite`,
 * `v`, `up`, `storage`, `build`) ممنوعةٌ على `tenants.slug` في
 * `TenantController::store` للسبب نفسه بالاتجاه المعاكس.
 */
Route::get('/{tenantSlug}/{summarySlug}/{rest?}', ShowPublishedSummaryController::class)
    ->where([
        'tenantSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'summarySlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'rest' => '.*',
    ])
    ->name('summary.published');
