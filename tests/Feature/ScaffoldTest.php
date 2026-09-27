<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;

/*
 * جذرُ الموقع صار صفحة التعريف العامّة (T-113)، و«/welcome» حُذفت معها.
 * ولوحةُ الجهة انتقلت كلُّها تحت «/panel».
 */

it('serves the public landing page at the site root', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('خُلاصات موثَّقة المصادر', escape: false);
});

it('serves the landing as right-to-left Arabic', function (): void {
    // الواجهة عربية RTL كاملة، وdir على <html> — CLAUDE.md §1 و SCREENS.md.
    $response = $this->get('/');

    expect($response->getContent())
        ->toContain('<html lang="ar" dir="rtl">')
        ->toContain('IBM+Plex+Sans+Arabic');
});

it('sends a signed-in user from the root to the panel index', function (): void {
    // من له حسابٌ لا يُعرض عليه بيعُ المنتج كلّما فتح الموقع.
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    $this->actingAs($user)->get('/')->assertRedirect('/panel');
});

it('keeps the landing free of real names when no published example is configured', function (): void {
    /*
     * ★ **قرارُ مالك المنتج، ١٢ أيلول ٢٠٢٦ (T-113) — نُسخ بعضُه في ١٦ أيلول (T-143).**
     * كانت الصفحةُ «صفرَ بياناتٍ حقيقية». وصار المحتوى الحقيقيُّ يدخلها
     * **من بابٍ واحد**: الخلاصةُ المنشورة المهيّأة في
     * `khulasah.landing.showcase` ({@see LandingShowcaseTest}). وما سواه
     * يبقى محروساً هنا: بلا مثالٍ منشور، مواضعُ موصوفةٌ لا غير.
     */
    config([
        'khulasah.landing.showcase.tenant_slug' => 'no-such-tenant',
        'khulasah.landing.showcase.summary_slug' => 'no-such-summary',
    ]);

    $content = $this->get('/')->getContent();

    expect($content)
        ->toContain('اسم المُلقي')
        ->toContain('مكان المحاضرة')
        ->not->toContain('جهة الاختبار')
        ->not->toContain('ملقي الاختبار')
        ->not->toContain('reference-summary');
});

it('answers the health check', function (): void {
    $this->get('/up')->assertOk();
});
