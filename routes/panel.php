<?php

declare(strict_types=1);

/*
 * مسارات لوحة الجهة — **كلّها تحت `/panel`** (T-113).
 *
 * ولماذا نُقلت من `web.php`: جذرُ الموقع `/` صار صفحةَ التعريف العامّة،
 * فبقاءُ فهرس الجهة عليه يمنع ذلك. والفصلُ في ملفٍّ يُبقي `web.php`
 * للعامّ وحده — صفحةُ التعريف، والاعتراض، وشاهدةُ العدّ — ولوحةُ المشرف
 * على حارسها المنفصل تحت `/admin` كما كانت.
 *
 * **وأسماءُ المسارات لم تتغيّر**، فكلّ `route('lectures.index')` وأخواتها
 * تظلّ تعمل، ويتبدّل الرابط وحده.
 *
 * وما بقي على الجذر عمداً: `/complaint` و`/v/{job}/{type}.gif`. الصفحاتُ
 * المنشورة على الـCDN تحمل الرابطين مطلقَين في ذيلها، ونقلُهما يُبطل
 * صفحاتٍ منشورةً بالفعل.
 */

use App\Http\Controllers\AppearancePreviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CarouselController;
use App\Http\Controllers\EvidenceReviewController;
use App\Http\Controllers\ImageSetController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LectureController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PreflightController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\BrandController;
use App\Http\Controllers\Settings\CarouselDesignController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\SummaryJobController;
use App\Http\Controllers\SummaryLocaleController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
 * شاشات الجهة — SCREENS.md §٢ و§٣ و§٤ (T-16).
 *
 * كلّها خلف المصادقة: الحاجز في `BelongsToTenant` يقرأ من `TenantContext`،
 * وهو يُملأ من المستخدم. فطلبٌ بلا مستخدم يرى كلّ الجهات لا شيئاً منها —
 * ولذلك المصادقة شرطٌ لا زينة.
 */
/*
 * تبديلُ لغة اللوحة — T-133. **خارج حارس `auth` عمداً**: مبدّلُ شاشة
 * الدخول يُضغط قبل الجلسة، فحارسٌ هنا يجعل اللغة حكراً على من دخل.
 */
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('auth')->group(function (): void {
    Route::get('/', [LectureController::class, 'index'])->name('lectures.index');
    Route::get('/lectures/create', [LectureController::class, 'create'])->name('lectures.create');
    // **الحصّة تُفحص قبل الإنشاء لا بعده** — §11 وCLAUDE.md §2 القاعدة ٥.
    Route::post('/lectures', [LectureController::class, 'store'])
        ->middleware('quota')
        ->name('lectures.store');

    // الفحص المسبق قبل صرف أيّ مورد — §5-أ-1.
    Route::post('/lectures/preflight', PreflightController::class)->name('lectures.preflight');

    /*
     * رفعُ ملفّ الدرس أجزاءً — §5-أ-4-ب. **والحصّة تُفحص عند البدء**: لا
     * يُرفع نصفُ غيغابايت ثمّ يُقال «نفدت حصّتكم». والأجزاءُ بحدٍّ واسع:
     * درسٌ واحد خمسون جزءاً، وإعادةُ الساقط منها جزءٌ من العمل لا إساءة.
     */
    Route::post('/uploads', [UploadController::class, 'store'])
        ->middleware(['quota', 'throttle:30,60'])
        ->name('uploads.store');
    Route::get('/uploads/{upload}', [UploadController::class, 'show'])->whereUuid('upload')->name('uploads.show');
    Route::put('/uploads/{upload}/chunks/{index}', [UploadController::class, 'chunk'])
        ->whereUuid('upload')
        ->whereNumber('index')
        ->middleware('throttle:600,1')
        ->name('uploads.chunk');
    Route::post('/uploads/{upload}/complete', [UploadController::class, 'complete'])->whereUuid('upload')->name('uploads.complete');
    Route::delete('/uploads/{upload}', [UploadController::class, 'destroy'])->whereUuid('upload')->name('uploads.destroy');

    // «عاين المظهر» في شاشة الإنشاء — T-95. للمحرّر كالمالك، وبجهة الطالب وحدها، ولا تكتب شيئاً.
    Route::get('/lectures/appearance-preview', AppearancePreviewController::class)->name('lectures.appearance-preview');

    Route::get('/jobs/{job}', [SummaryJobController::class, 'show'])->name('jobs.show');
    Route::get('/jobs/{job}/status', [SummaryJobController::class, 'status'])->name('jobs.status');
    /*
     * «أعد المحاولة» تُنشئ مهمّةً جديدة وتضعها في الطابور، **فهي عملٌ جديد
     * بكلّ حدوده** — لا إعادةَ محاولةٍ آلية. وكان `RequestRegeneration`
     * يفحص عدّاد إعادة التوليد وحده، **فيمرّ الطلب على جهةٍ معلَّقة أو على
     * حصّةٍ نفدت أو وسقفُ الإنفاق مفتوح** (T-29).
     */
    Route::post('/jobs/{job}/retry', [SummaryJobController::class, 'retry'])
        ->middleware('quota')
        ->name('jobs.retry');

    // إلغاء العالق — و`cancelled` مسموحة من كلّ حالةٍ غير نهائية (§5)، كإلغاء المشرف بلا حصّة.
    Route::post('/jobs/{job}/cancel', [SummaryJobController::class, 'cancel'])->name('jobs.cancel');

    /*
     * المعاينة والنشر — SCREENS.md §6 و§7، والمهمّة T-30.
     *
     * **والمعاينة والتنزيل `GET` قراءةٌ محضة**: تُعاد بكلّ تحديثِ صفحة،
     * فلو كتبت أو رفعت لصار فتحُ الشاشة نشراً لم يطلبه أحد.
     *
     * **والنشر وإلغاؤه بلا حاجز حصّة** (T-29): كلاهما رفعُ ملفٍّ مرسوم،
     * وإعادةُ الرسم لا تُحتسب — §8-أ. والمصروف صُرف يوم وُلّد المتن.
     */
    Route::get('/jobs/{job}/preview', [PreviewController::class, 'show'])->name('jobs.preview');
    Route::get('/jobs/{job}/preview/page', [PreviewController::class, 'page'])->name('jobs.preview.page');
    Route::get('/jobs/{job}/download/{type}', [PreviewController::class, 'download'])->name('jobs.download');

    Route::get('/summaries/{job}', [PublicationController::class, 'show'])->name('summaries.show');
    Route::post('/summaries/{job}', [PublicationController::class, 'store'])->name('summaries.publish');
    Route::delete('/summaries/{job}/publication', [PublicationController::class, 'destroy'])->name('summaries.unpublish');
    Route::delete('/summaries/{job}', [PublicationController::class, 'purge'])->name('summaries.destroy');

    /*
     * «أضف لغة» لملخّصٍ قائم — T-166. نداءُ ترجمةٍ واحد لا الخطّ كلُّه،
     * **ولا يُخصم من الحصّة**؛ وسقفُ الإنفاق وتعليقُ الاشتراك قبل الطابور.
     */
    Route::post('/jobs/{job}/locales', [SummaryLocaleController::class, 'store'])
        ->middleware('quota:extra')
        ->name('jobs.locales.store');

    /*
     * الكاروسيل — SCREENS.md §6، والمهمّة T-19.
     *
     * **ولا يُحرَس بحصّة إعادة التوليد**: رسمٌ من أصلٍ موجود، وكلفتُه صفر
     * ما لم يُطلب نصٌّ جديد صراحةً — المواصفة §8-أ.
     *
     * ★ **ولا صفحةَ له منفصلة** — T-204: الشرائحُ وصورُها وقالبُها في تبويب
     * الشرائح بالمعاينة. وحُذفت الصفحةُ ومعاينتُها بلا تحويل، فرابطُها القديم
     * «غير موجود» — ولذلك صار البناءُ على `carousel/build` لا على عنوانها.
     */
    Route::post('/jobs/{job}/carousel/build', [CarouselController::class, 'store'])->name('jobs.carousel.store');

    /*
     * حزمةُ صور الكاروسيل — T-173. تُنشأ في الطابور، وتُرى صورُها من قرصٍ
     * خاصّ عبر اللوحة، وتُنزَّل حزمةً من `jobs.download`.
     */
    Route::post('/jobs/{job}/images', [ImageSetController::class, 'store'])->name('jobs.images.store');
    Route::get('/jobs/{job}/images/{slide}.png', [ImageSetController::class, 'show'])
        ->whereNumber('slide')
        ->name('jobs.images.show');

    /*
     * بوّابة المراجعة — SCREENS.md الشاشة 5. وهي التي يستأنف بها الخطّ
     * بعد حسم الشواهد، فلا تُنشر صفحةٌ لم يوقّع عليها إنسان.
     */
    Route::get('/jobs/{job}/review', [EvidenceReviewController::class, 'show'])->name('jobs.review');
    Route::post('/jobs/{job}/evidence/{item}/decide', [EvidenceReviewController::class, 'decide'])->name('jobs.evidence.decide');
    /*
     * والاستئناف بعد المراجعة **عملٌ دُفع ثمنُه سلفاً**: خصمُ حصّةٍ ثانيةٍ
     * عنه خصمٌ مرّتين عن ملخّصٍ واحد. ويبقى سقفُ الإنفاق لأنّه «يوقف
     * الطابور كلّه» (§11) ولا يستثني عملاً قائماً.
     */
    Route::post('/jobs/{job}/resume', [EvidenceReviewController::class, 'resume'])
        ->middleware('quota:cap')
        ->name('jobs.resume');
});

/*
 * إعدادات الجهة — SCREENS.md القسم الثاني.
 */
/*
 * تسجيل الدخول — SCREENS.md §1. **ولا مسار `register`**: الحسابات بدعوة.
 */
Route::middleware('guest:web,admin')->group(function (): void {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    /*
     * استعادة كلمة المرور — SCREENS.md §1 («حقلان وزر **واستعادة كلمة
     * المرور**»)، والمهمّة T-32.
     *
     * ★ **وهي البابُ الوحيد لمن نسي كلمته**: الحسابات بدعوة ولا تسجيل
     * ذاتي، فمن نسي لا مدخل له إلى المنتج البتّة.
     *
     * والخنق طبقتان: حدٌّ في المتحكّم لكلّ (بريد + عنوان)، وحدٌّ على
     * المسار لكلّ عنوان — فلا يُستعمل النموذج لقصف بريدٍ واحد ولا لجسّ
     * البُرد بالجملة.
     */
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:10,60')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:10,60')
        ->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    // الحارسان: المشرف ليس على `web`، فحارسٌ واحد يمنعه من الخروج أصلاً.
    ->middleware('auth:web,admin')
    ->name('logout');

/*
 * قبول دعوة الفريق — **بلا مصادقة** (T-33): المدعوّ ليس في المنتج بعد.
 *
 * **وهذا هو المسار الوحيد الذي يُنشأ به حساب.** ولا تسجيل ذاتي (§1)،
 * فالرابط بديلُه — ولا يُنشأ حسابٌ إلّا بدعوةٍ من مالك جهة.
 */
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations', [InvitationController::class, 'store'])
    ->middleware('throttle:10,60')
    ->name('invitations.accept');

Route::middleware(['auth'])->prefix('settings')->name('settings.')->group(function (): void {
    Route::get('/brand', [BrandController::class, 'edit'])->name('brand.edit');
    Route::post('/brand', [BrandController::class, 'update'])->name('brand.update');

    /*
     * قوالبُ الكاروسيل — T-173. والتوليدُ وحده نداءٌ مدفوع، فيحرسه السقفُ
     * والتعليق (`quota:extra`). والمعاينةُ قالبٌ على كاروسيل العيّنة في إطار.
     */
    Route::post('/brand/carousel-designs', [CarouselDesignController::class, 'generate'])
        ->middleware('quota:extra')
        ->name('brand.carousel-designs.generate');
    Route::post('/brand/carousel-designs/{design}/approve', [CarouselDesignController::class, 'approve'])
        ->name('brand.carousel-designs.approve');
    Route::post('/brand/carousel-designs/{design}/default', [CarouselDesignController::class, 'makeDefault'])
        ->name('brand.carousel-designs.default');
    Route::delete('/brand/carousel-designs/{design}', [CarouselDesignController::class, 'destroy'])
        ->name('brand.carousel-designs.destroy');
    Route::get('/brand/carousel-designs/{design}/preview', [CarouselDesignController::class, 'preview'])
        ->name('brand.carousel-designs.preview');

    // المعاينة الحيّة تُرسم بالقالب الحقيقي وتُعرض في إطار — الشاشة 8.
    Route::get('/brand/preview', [BrandController::class, 'preview'])->name('brand.preview');

    // وما لم يُحفظ بعد، ومعه الشعار ملفّاً — T-85. `POST` لأنّ الملفّ لا يُحمل
    // في رابط، والخنق لأنّ كلّ طلبٍ رسمٌ كاملٌ للقالب.
    Route::post('/brand/preview', [BrandController::class, 'draft'])
        ->middleware('throttle:120,1')
        ->name('brand.preview.draft');

    /*
     * الاشتراك والاستهلاك — SCREENS.md الشاشة 9 (T-23). **قراءةٌ وحدها**:
     * لا بوّابة دفع في هذه المرحلة، والحدود تُضبط من لوحة المشرف بعد
     * التحويل البنكي.
     */
    Route::get('/billing', [BillingController::class, 'show'])->name('billing');

    /*
     * الفريق — SCREENS.md §10 (T-33). **و`owner` وحده يديره**، والحارس
     * `UserPolicy` القائمة منذ T-02 — وكانت بقواعدها كاملةً ولا شاشة
     * تناديها.
     */
    Route::get('/team', [TeamController::class, 'index'])->name('team');
    Route::post('/team/invitations', [TeamController::class, 'invite'])->name('team.invite');
    Route::delete('/team/invitations/{invitation}', [TeamController::class, 'revoke'])->name('team.revoke');
    Route::put('/team/members/{member}', [TeamController::class, 'updateRole'])->name('team.role');
    Route::delete('/team/members/{member}', [TeamController::class, 'remove'])->name('team.remove');
});
