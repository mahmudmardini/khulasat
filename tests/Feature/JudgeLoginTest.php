<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Auth\JudgeAccess;
use App\Support\TenantContext;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * دخولُ لجنة التحكيم بنقرة — T-186، قرار مالك المنتج.
 *
 * ★ **والحارسُ أهمّ من الزرّ**: زرُّ «مشرف المنصّة» لكلّ زائرٍ على خادمٍ عامّ
 * يفتح لوحة المشرف ومفاتيحَ المزوّدين لأيّ أحد. فأكثرُ ما هنا يفحص الإغلاق.
 */

function judgeUser(string $email): ?User
{
    return app(TenantContext::class)->withoutScope(fn (): ?User => User::query()->where('email', $email)->first());
}

beforeEach(function (): void {
    config(['khulasah.judges.enabled' => true, 'khulasah.judges.key' => 'secret-key']);
    $this->artisan('khulasah:seed-judges')->assertSuccessful();
});

it('creates the trial institution and the three accounts, and rerunning keeps their passwords', function (): void {
    $tenant = Tenant::query()->where('slug', JudgeAccess::TENANT_SLUG)->sole();

    expect($tenant->monthly_quota)->toBe(30)
        ->and(judgeUser('admin@judges.test')->isSuperAdmin())->toBeTrue()
        ->and(judgeUser('owner@judges.test')->tenant_id)->toBe($tenant->id)
        ->and(judgeUser('owner@judges.test')->role)->toBe(Role::Owner)
        ->and(judgeUser('editor@judges.test')->role)->toBe(Role::Editor);

    $hash = judgeUser('owner@judges.test')->password;
    $this->artisan('khulasah:seed-judges')->assertSuccessful();
    expect(judgeUser('owner@judges.test')->password)->toBe($hash);

    $this->artisan('khulasah:seed-judges', ['--reset' => true])->assertSuccessful();
    expect(judgeUser('owner@judges.test')->password)->not->toBe($hash);
});

it('shows the buttons only with the right key', function (): void {
    $this->get('/panel/login')->assertInertia(fn (Assert $p) => $p->where('judges', null));
    $this->get('/panel/login?judge=wrong')->assertInertia(fn (Assert $p) => $p->where('judges', null));

    $this->get('/panel/login?judge=secret-key')->assertInertia(fn (Assert $p) => $p
        ->where('judges.key', 'secret-key')
        ->where('judges.roles', ['admin', 'owner', 'editor'])
        ->where('lang.auth.judges.roles.admin', 'مشرف المنصّة'));
});

it('signs each role in on its own guard and panel', function (): void {
    $this->post('/panel/login/judge', ['role' => 'owner', 'judge' => 'secret-key'])->assertRedirect('/panel');
    $this->assertAuthenticatedAs(judgeUser('owner@judges.test'), 'web');

    $this->post('/panel/logout');

    $this->post('/panel/login/judge', ['role' => 'admin', 'judge' => 'secret-key'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs(judgeUser('admin@judges.test'), 'admin');
});

it('refuses a wrong key, a missing key and an unknown role as if there were no door', function (): void {
    $this->post('/panel/login/judge', ['role' => 'admin', 'judge' => 'wrong'])->assertNotFound();
    $this->post('/panel/login/judge', ['role' => 'admin'])->assertNotFound();
    $this->post('/panel/login/judge', ['role' => 'viewer', 'judge' => 'secret-key'])->assertNotFound();

    $this->assertGuest('web');
    $this->assertGuest('admin');
});

it('never signs in an account that is not one of the three', function (): void {
    // دورٌ صحيح وبريدُ حسابه محذوف: لا يُبحث عن غيره.
    judgeUser('editor@judges.test')->delete();

    $this->post('/panel/login/judge', ['role' => 'editor', 'judge' => 'secret-key'])->assertNotFound();
    $this->get('/panel/login?judge=secret-key')->assertInertia(fn (Assert $p) => $p->where('judges.roles', ['admin', 'owner']));
});

it('stays shut without a key outside local, and when switched off', function (): void {
    config(['khulasah.judges.key' => null]);

    // الاختبارات تعمل في `testing` لا `local` — خادمٌ نُسي رمزُه مغلق.
    $this->post('/panel/login/judge', ['role' => 'admin'])->assertNotFound();

    app()->detectEnvironment(fn (): string => 'local');
    $this->get('/panel/login')->assertInertia(fn (Assert $p) => $p->where('judges.key', null));

    config(['khulasah.judges.enabled' => false]);
    $this->get('/panel/login')->assertInertia(fn (Assert $p) => $p->where('judges', null));

    // ‏`local` يُعيد حارس CSRF في الاختبار، فيُردّ قبل أن يبلغ الباب.
    app()->detectEnvironment(fn (): string => 'testing');
    $this->post('/panel/login/judge', ['role' => 'admin'])->assertNotFound();
});
