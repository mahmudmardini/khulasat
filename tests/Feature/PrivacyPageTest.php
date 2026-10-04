<?php

declare(strict_types=1);

use App\Actions\Compliance\RequestAccountClosure;
use App\Http\Controllers\Admin\TenantController;
use App\Support\Arabic;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * T-207 — سياسة الخصوصية المعلنة (بندُ الخصوصية في وثيقة المرجعية).
 *
 * **ما تقوله الصفحة يتبع الإعداد**: مدّةُ حذف نصوص «تحقّق» تُغيَّر فتتغيّر
 * الجملةُ معها، فلا تَعِد الصفحةُ بما لا يفعله الكود.
 */
it('serves the privacy policy without signing in', function (): void {
    $this->get('/privacy')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Public/Privacy')
            ->where('title', 'سياسة الخصوصية')
            ->has('sections', 6));
});

it('states the retention periods the code actually applies', function (): void {
    config(['khulasah.verify.retention_days' => 9, 'khulasah.transcript.upload.retention_days' => 2]);

    $items = collect($this->get('/privacy')->viewData('page')['props']['sections'])->pluck('items')->flatten()->implode("\n");

    expect($items)->toContain('٩ أيّام')
        ->and($items)->toContain('٢ أيّام')
        ->and($items)->toContain(Arabic::toArabicIndicDigits(RequestAccountClosure::GRACE_DAYS).' يوماً')
        ->and($items)->not->toContain(':');
});

it('reserves the privacy slug for tenants', function (): void {
    $reserved = (new ReflectionClassConstant(TenantController::class, 'RESERVED_SLUGS'))->getValue();

    expect($reserved)->toContain('privacy');
});

it('links the privacy policy from the landing page', function (): void {
    $this->get('/')->assertSee('/privacy', false);
});
