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
use App\Http\Controllers\Admin\TenantCarouselDesignController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Public\ComplaintController;
use App\Http\Controllers\Public\GuideController;
use App\Http\Controllers\Public\InviteRequestController;
use App\Http\Controllers\Public\PageViewController;
use App\Http\Controllers\Public\PrivacyController;
use App\Http\Controllers\Public\QuizController;
use App\Http\Controllers\Public\ShareCardController;
use App\Http\Controllers\Public\ShowPublishedSummaryController;
use App\Http\Controllers\VerifyController;
use App\Http\Middleware\SetAppLocale;
use App\Http\Middleware\SetLandingLocale;
use App\Support\Guide\GuideBook;
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
// سياسة الخصوصية المعلنة — T-207، بلا تسجيل دخول.
Route::get('/privacy', PrivacyController::class)->name('privacy');

Route::get('/complaint', [ComplaintController::class, 'create'])->name('complaint.create');
Route::post('/complaint', [ComplaintController::class, 'store'])
    ->middleware('throttle:10,60')
    ->name('complaint.store');

/*
 * أداة «تحقّق» — **بلا تسجيل دخول** (T-181). يلصق الباحث نصّاً فيرى كلّ آيةٍ
 * وحديثٍ فيه بحكمه. وحدُّ المعدّل في `SubmitVerifyCheck` لا هنا: يُعدّ بالطلب
 * المقبول، ويشترك فيه هذا المسار والواجهةُ البرمجية بمفتاحٍ واحد.
 */
Route::get('/verify', [VerifyController::class, 'create'])->name('verify.create');
Route::post('/verify', [VerifyController::class, 'store'])->name('verify.store');
Route::get('/verify/{check}', [VerifyController::class, 'show'])->whereUuid('check')->name('verify.show');
Route::get('/verify/{check}/status', [VerifyController::class, 'status'])->whereUuid('check')->name('verify.status');

/*
 * دليلُ الاستخدام — **بلا تسجيل دخول** (T-215): يُعطى لمن لم يرَ النظام،
 * ورابطُ قسمٍ منه يُرسل إلى من لا حساب له. واللغةُ في الرابط ليفتح عند
 * مستلمه بلغة من نسخه.
 *
 * و`guide` في `RESERVED_SLUGS`: `/guide/ar/owner` على صورة المسار الجامع
 * آخرَ الملفّ، فلا تأخذه جهةٌ فيلتبس.
 */
Route::get('/guide', [GuideController::class, 'home'])->name('guide');
Route::get('/guide/{locale}', [GuideController::class, 'index'])
    ->whereIn('locale', ['ar', 'en', 'tr', 'ru'])
    ->name('guide.index');
Route::get('/guide/{locale}/{role}', [GuideController::class, 'show'])
    ->whereIn('locale', ['ar', 'en', 'tr', 'ru'])
    ->whereIn('role', GuideBook::ROLES)
    ->name('guide.show');

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
 * اختبارُ الفهم برابطٍ يُشارَك — T-195. **عامٌّ بلا دخولٍ ولا اسم**.
 *
 * و`q` في `RESERVED_SLUGS`، فلا تأخذه جهةٌ فيبتلعه المسارُ الجامع آخرَ
 * الملفّ. والبدءُ وحفظُ الأجوبة محدودان (`quiz-start` و`quiz-answer`) —
 * فالرابطُ عامّ، وبلا حدٍّ يُغرق آليٌّ تقاريرَ الجهة بمحاولاتٍ وهمية.
 */
Route::prefix('/q/{token}')
    ->where(['token' => '[a-z0-9]{12}', 'attempt' => '[A-Za-z0-9]{40}'])
    ->name('quiz.')
    ->group(function (): void {
        Route::get('/', [QuizController::class, 'show'])->name('show');
        Route::post('/', [QuizController::class, 'start'])->middleware('throttle:quiz-start')->name('start');
        Route::get('/a/{attempt}', [QuizController::class, 'attempt'])->name('attempt');
        Route::post('/a/{attempt}/answer', [QuizController::class, 'answer'])->middleware('throttle:quiz-answer')->name('answer');
        Route::post('/a/{attempt}/finish', [QuizController::class, 'finish'])->middleware('throttle:quiz-answer')->name('finish');
        Route::get('/a/{attempt}/result', [QuizController::class, 'result'])->name('result');
    });

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
     * قوالبُ كاروسيل الجهة — T-173. تعليماتُ توليدٍ خاصّة بالجهة، وإعادةُ
     * توليدٍ بها. والتوليدُ إنفاقٌ فيحرسه السقف (`quota:cap`) كسائر النداءات.
     */
    Route::put('/tenants/{tenant}/carousel-designs/prompt', [TenantCarouselDesignController::class, 'updatePrompt'])
        ->name('tenants.carousel-designs.prompt');
    Route::post('/tenants/{tenant}/carousel-designs', [TenantCarouselDesignController::class, 'generate'])
        ->middleware('quota:cap')
        ->name('tenants.carousel-designs.generate');
    Route::get('/tenants/{tenant}/carousel-designs/{design}/preview', [TenantCarouselDesignController::class, 'preview'])
        ->name('tenants.carousel-designs.preview');

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
 * `v`, `up`, `storage`, `build`, `verify`, `api`, `privacy`, `guide`) ممنوعةٌ على `tenants.slug` في
 * `TenantController::store` للسبب نفسه بالاتجاه المعاكس.
 */
Route::get('/{tenantSlug}/{summarySlug}/{rest?}', ShowPublishedSummaryController::class)
    ->where([
        'tenantSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'summarySlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
        'rest' => '.*',
    ])
    ->name('summary.published');
