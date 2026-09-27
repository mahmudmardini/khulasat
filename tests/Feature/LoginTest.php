<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * تسجيل الدخول — SCREENS.md §1، والمهمّة T-16ب.
 *
 * وهذه الثغرة كانت **تُقفل المنتج كلَّه**: `/` يحوّل إلى `/login` وهو 404،
 * فكلّ مسارٍ محروس طريقٌ مسدود ولا سبيل لأحدٍ أن يدخل.
 */

beforeEach(function (): void {
    RateLimiter::clear('login:owner@tenant-a.test|127.0.0.1');

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create([
        'email' => 'owner@tenant-a.test',
        'password' => Hash::make('password'),
    ]);
});

it('serves a login screen instead of a dead end', function (): void {
    $this->get('/panel/login')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Auth/Login'));
});

it('ships the show/hide password labels to the login screen', function (): void {
    $this->get('/panel/login')->assertInertia(fn (Assert $p) => $p
        ->where('lang.auth.show_password', 'إظهار كلمة المرور')
        ->where('lang.auth.hide_password', 'إخفاء كلمة المرور'));
});

it('sends a guest who asks for a guarded page to the login screen', function (): void {
    $this->get('/panel')->assertRedirect('/panel/login');
});

it('lets a tenant user in and lands them on the index', function (): void {
    $this->post('/panel/login', ['email' => 'owner@tenant-a.test', 'password' => 'password'])
        ->assertRedirect('/panel');

    $this->assertAuthenticatedAs($this->user);
});

it('sends the super admin to their own panel', function (): void {
    User::factory()->superAdmin()->create([
        'email' => 'admin@khulasah.app',
        'password' => Hash::make('password'),
    ]);

    $this->post('/panel/login', ['email' => 'admin@khulasah.app', 'password' => 'password'])
        ->assertRedirect('/admin');
});

it('gives one message for a wrong password and an unknown email alike', function (): void {
    // ★ التفريق بينهما يُخبر المهاجم أيّ البُرد مسجَّل عندنا، فيصير نموذج
    //   الدخول أداةَ استطلاع. فالرسالة واحدة.
    $wrongPassword = $this->post('/panel/login', ['email' => 'owner@tenant-a.test', 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    $unknownEmail = $this->post('/panel/login', ['email' => 'ghost@nowhere.test', 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->get('email'))->toBe(['البريد أو كلمة المرور غير صحيحة.'])
        ->and($wrongPassword)->not->toBeNull()
        ->and($unknownEmail)->not->toBeNull();

    $this->assertGuest();
});

it('throttles a burst of guesses instead of letting them run', function (): void {
    foreach (range(1, 5) as $ignored) {
        $this->post('/panel/login', ['email' => 'owner@tenant-a.test', 'password' => 'nope']);
    }

    $this->post('/panel/login', ['email' => 'owner@tenant-a.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    // ★ وحتى كلمة المرور الصحيحة لا تمرّ أثناء الحظر.
    $this->assertGuest();
});

it('rotates the session id on the way in', function (): void {
    $this->get('/panel/login');
    $before = session()->getId();

    $this->post('/panel/login', ['email' => 'owner@tenant-a.test', 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});

it('lets a signed-in user out again', function (): void {
    $this->actingAs($this->user)->post('/panel/logout')->assertRedirect('/panel/login');

    $this->assertGuest();
});

it('offers no self-registration route, since accounts come by invitation', function (): void {
    $this->get('/register')->assertNotFound();
});

/*
 * بابُ الدخول مقطوع — T-129. عطلان بلّغ عنهما المستخدم، وكلاهما من
 * نقل المصادقة تحت `/panel` في T-113.
 */

it('sends the conventional /login to the door under /panel', function (): void {
    // ★ T-113 نقل الباب، والرابطُ المتعارف عليه بقي يُكتب ويُحفظ ويُرسل.
    //   و`/login` مقطعٌ واحد فلا يبلغه الجذرُ العامّ — كان 404 وحده.
    $this->get('/login')->assertRedirect('/panel/login');
});

it('sends a signed-in admin who opens the login screen to their own panel', function (): void {
    $admin = User::factory()->superAdmin()->create();

    // ★ الدورة: حارس `guest` كان يصرفه إلى `/`، و`/` تفحص الحارس
    //   الافتراضي وحده فلا تراه، فتُعيد صفحةَ التعريف التي جاء منها.
    $this->actingAs($admin, 'admin')->get('/panel/login')->assertRedirect('/admin');
});

it('sends a signed-in tenant user who opens the login screen to their index', function (): void {
    $this->actingAs($this->user)->get('/panel/login')->assertRedirect('/panel');
});

it('sends a signed-in admin who opens the landing to their own panel', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin, 'admin')->get('/')->assertRedirect('/admin');
});

it('sends a signed-in tenant user who opens the landing to their index', function (): void {
    $this->actingAs($this->user)->get('/')->assertRedirect('/panel');
});

it('still shows the landing page to a visitor with no session', function (): void {
    // بالمفتاح لا بنصّه — النصُّ التسويقيّ يتبدّل، والمقيسُ أنّ الصفحة ظهرت (T-138).
    $this->get('/')->assertOk()->assertSee(__('landing.invite.alt_anatomy'));
});
