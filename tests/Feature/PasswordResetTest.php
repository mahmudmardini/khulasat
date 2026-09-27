<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * استعادة كلمة المرور — SCREENS.md §1، والمهمّة T-32.
 *
 * ★ **وهي البابُ الوحيد لمن نسي كلمته.** فالحسابات بدعوة ولا تسجيل ذاتي،
 * فمن نسي لا مدخل له إلى المنتج البتّة — لا استعادة ولا حسابٌ بديل.
 *
 * **والمقياس الثاني: لا يُفرَّق في الردّ بين بريدٍ مسجَّل وغير مسجَّل.**
 * وهي عين علّة رسالة الدخول الواحدة: التفريق يجعل النموذج أداةَ استطلاع.
 */

beforeEach(function (): void {
    Notification::fake();
    RateLimiter::clear('reset:owner@tenant-a.test|127.0.0.1');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create([
        'email' => 'owner@tenant-a.test',
        'password' => Hash::make('old-password-1'),
    ]);
});

// ── الشاشات ─────────────────────────────────────────────────────

it('يفتح شاشتَي الاستعادة للزائر', function (): void {
    $this->get('/panel/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('Auth/ForgotPassword'));

    $this->get('/panel/reset-password/some-token?email=owner@tenant-a.test')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Auth/ResetPassword')
            ->where('token', 'some-token')
            // والبريد يصل من الرابط فلا يُطلب كتابتُه ثانيةً.
            ->where('email', 'owner@tenant-a.test')
        );
});

/** **ورابط الاستعادة في شاشة الدخول** — §1 يعدّه من الشاشة نفسها. */
it('يضع مدخل الاستعادة في شاشة الدخول', function (): void {
    $login = (string) file_get_contents(resource_path('js/Pages/Auth/Login.tsx'));

    expect($login)->toContain('/panel/forgot-password');
});

it('يمنع شاشات الاستعادة عمّن هو داخلٌ أصلاً', function (): void {
    // حاجز `guest`: من دخل لا يُعرض عليه بابُ الدخول ولا استعادتُه.
    $this->actingAs($this->user)->get('/panel/forgot-password')->assertRedirect();
});

// ── الإرسال ─────────────────────────────────────────────────────

it('يرسل رابطاً بالعربية إلى بريدٍ مسجّل', function (): void {
    $this->post('/panel/forgot-password', ['email' => 'owner@tenant-a.test'])
        ->assertRedirect()
        ->assertSessionHas('message');

    Notification::assertSentTo($this->user, ResetPassword::class);
});

/**
 * ★ **الاختبار الحاكم في هذا الملفّ.**
 *
 * فلو اختلف الردّان لصار النموذج أداةَ استطلاع: من عرف أنّ بريد فلانٍ
 * مسجَّل عرف أنّ لفلانٍ جهةً عندنا — **وهو وحده تسريب**.
 */
it('يقول الشيء نفسه لبريدٍ غير مسجّل، فلا يكشف من عندنا', function (): void {
    $known = $this->post('/panel/forgot-password', ['email' => 'owner@tenant-a.test']);

    RateLimiter::clear('reset:ghost@nowhere.test|127.0.0.1');

    $unknown = $this->post('/panel/forgot-password', ['email' => 'ghost@nowhere.test']);

    expect($unknown->status())->toBe($known->status())
        ->and(session('message'))->not->toBeNull();

    $unknown->assertSessionHasNoErrors();

    // ولا رسالةَ ثانية أُرسلت: الأولى وحدها للمسجَّل.
    Notification::assertCount(1);
});

it('يخنق الطلب المتكرّر على البريد نفسه', function (): void {
    foreach (range(1, 3) as $ignored) {
        $this->post('/panel/forgot-password', ['email' => 'owner@tenant-a.test'])->assertSessionHasNoErrors();
    }

    // **والخنق على (بريد + عنوان)**، فلا يُقصف بريدُ أحدٍ برسائل.
    $this->post('/panel/forgot-password', ['email' => 'owner@tenant-a.test'])
        ->assertSessionHasErrors('email');
});

// ── التبديل ─────────────────────────────────────────────────────

it('يبدّل كلمة المرور برابطٍ صحيح', function (): void {
    $token = Password::createToken($this->user);

    $this->post('/panel/reset-password', [
        'token' => $token,
        'email' => 'owner@tenant-a.test',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ])->assertRedirect(route('login'));

    expect(Hash::check('a-long-fresh-secret-9', $this->user->refresh()->password))->toBeTrue();
});

/**
 * ★ **ولا دخول تلقائيّ بعد التبديل.**
 *
 * فالمنتج بحارسين (`web` للجهة و`admin` للمشرف — المواصفة §10)، ووسيطُ
 * الاستعادة على مزوّد `users` وحده. **فدخولٌ تلقائيّ هنا يُدخل المشرفَ
 * العامّ على حارس الجهة**، فيرى لوحةً ليست له.
 */
it('لا يُدخل صاحبه تلقائياً بعد التبديل', function (): void {
    $token = Password::createToken($this->user);

    $this->post('/panel/reset-password', [
        'token' => $token,
        'email' => 'owner@tenant-a.test',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ]);

    expect(auth('web')->check())->toBeFalse()
        ->and(auth('admin')->check())->toBeFalse();
});

it('يُبطل الرابط بعد استعماله مرّةً', function (): void {
    $token = Password::createToken($this->user);

    $payload = [
        'token' => $token,
        'email' => 'owner@tenant-a.test',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ];

    $this->post('/panel/reset-password', $payload)->assertRedirect(route('login'));

    $this->post('/panel/reset-password', [...$payload, 'password' => 'another-fresh-secret-9', 'password_confirmation' => 'another-fresh-secret-9'])
        ->assertSessionHasErrors('email');

    // والكلمة الأولى باقية: المحاولة الثانية لم تُبدّل شيئاً.
    expect(Hash::check('a-long-fresh-secret-9', $this->user->refresh()->password))->toBeTrue();
});

it('يرفض رمزاً غير صحيح برسالةٍ عربية تقول ما العمل', function (): void {
    $this->post('/panel/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'owner@tenant-a.test',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ])->assertSessionHasErrors(['email' => trans('auth.reset.invalid')]);

    expect(Hash::check('old-password-1', $this->user->refresh()->password))->toBeTrue();
});

it('يرفض كلمةً قصيرة أو غير مؤكَّدة', function (string $password, string $confirmation): void {
    $token = Password::createToken($this->user);

    $this->post('/panel/reset-password', [
        'token' => $token,
        'email' => 'owner@tenant-a.test',
        'password' => $password,
        'password_confirmation' => $confirmation,
    ])->assertSessionHasErrors('password');

    expect(Hash::check('old-password-1', $this->user->refresh()->password))->toBeTrue();
})->with([
    'قصيرة' => ['short1', 'short1'],
    'لا تطابق التأكيد' => ['a-long-fresh-secret-9', 'a-long-fresh-secret-8'],
]);

/**
 * **وتُبطَل جلسة «أبقني داخلاً» القديمة.**
 *
 * فمن بدّل كلمته بدّلها غالباً لأنّ جهازاً ضاع أو وصولاً يُخشى منه —
 * **وكوكي «أبقني داخلاً» على ذلك الجهاز يبقى يعمل** ما لم يُبدَّل الرمز.
 */
it('يُبطل جلسات «أبقني داخلاً» القديمة', function (): void {
    $before = $this->user->refresh()->remember_token;

    $this->post('/panel/reset-password', [
        'token' => Password::createToken($this->user),
        'email' => 'owner@tenant-a.test',
        'password' => 'a-long-fresh-secret-9',
        'password_confirmation' => 'a-long-fresh-secret-9',
    ])->assertRedirect(route('login'));

    expect($this->user->refresh()->remember_token)->not->toBe($before);
});

// ── الرسالة ─────────────────────────────────────────────────────

/**
 * **والرسالة عربية RTL، لا قالب لارافل الإنجليزي.**
 *
 * فرسالةٌ إنجليزية مقلوبة الاتّجاه تصل جهةً عربية **تُقرأ رسالةَ احتيال**
 * فتُحذف، ويبقى صاحبها خارج حسابه — وهو عين ما بُنيت الاستعادة لمنعه.
 */
it('يبني رسالة عربية فيها الرابط ومهلته', function (): void {
    $mail = (new ResetPassword('tok-123'))->toMail($this->user);

    $rendered = (string) $mail->render();

    expect($mail->subject)->toBe(trans('auth.reset.mail_subject'))
        ->and($rendered)->toContain('dir="rtl"')
        ->and($rendered)->toContain('lang="ar"')
        ->and($rendered)->toContain('/panel/reset-password/tok-123')
        // البريد في الرابط: الوسيط يبحث به ثمّ يوازن الرمز.
        ->and($rendered)->toContain('email=owner%40tenant-a.test')
        ->and($rendered)->toContain(trans('auth.reset.mail_action'))
        // ومن لم يطلب لا يلزمه شيء — فلا يظنّ حسابه اختُرق.
        ->and($rendered)->toContain(trans('auth.reset.mail_ignore'))
        // **والأرقام في النثر عربية هندية** — §الخطوط. و«60» في جملةٍ عربية
        // تُقرأ كسراً في السطر.
        ->and($rendered)->toContain('٦٠ دقيقة')
        ->and($rendered)->not->toContain('60 دقيقة');

    // ولا كلمة إنجليزية من قالب لارافل.
    expect($rendered)->not->toContain('Reset Password')
        ->and($rendered)->not->toContain('Regards');
});

/** والرابط نصّاً كذلك، لمن لا يفتح الأزرار في بريده. */
it('يضع الرابط نصّاً إلى جانب الزرّ', function (): void {
    $rendered = (string) (new ResetPassword('tok-123'))->toMail($this->user)->render();

    expect(substr_count($rendered, '/panel/reset-password/tok-123'))->toBeGreaterThanOrEqual(2);
});
