<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Quota\SpendCap;

/*
 * وقفُ سقف الإنفاق من لوحة المشرف — T-151.
 *
 * **بلاغُ المستخدم الذي بدأ المهمّة:** وقفٌ وقع لأجل اختبارٍ (`halt('اختبار')`)
 * بقي قائماً أيّاماً بلا أن يراه أحد في اللوحة، ولا طريق إلى رفعه إلّا
 * `tinker`. فهذا الاختبار يقيس الحالتين معاً: **الرؤية** (الشريط يُبثّ مع كلّ
 * استجابة) **والتحكّم** (نموذجٌ في اللوحة، بسببٍ مكتوب، ومُقيَّدٌ في السجلّ).
 */

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->cap = app(SpendCap::class);
    $this->cap->release();
});

it('shares no spend-cap halt while the queue runs', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertInertia(fn ($page) => $page->where('spend_cap_halted', null));
});

// والشريط يُبثّ مع كلّ استجابة — لا شاشة الكلفة وحدها — SCREENS.md §أ.
it('shares the halt reason on any admin screen once the queue is stopped', function (): void {
    $this->cap->halt('اختبار');

    $this->actingAs($this->admin, 'admin')
        ->get('/admin')
        ->assertInertia(fn ($page) => $page->where('spend_cap_halted.reason', 'اختبار'));
});

it('halts the queue from the panel with a written reason, and records it', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put('/admin/spend-cap', [
            'halted' => true,
            'note' => 'مراجعة تشغيلية مجدولة',
        ])
        ->assertRedirect();

    expect($this->cap->isHalted())->toBeTrue();

    $event = AuditEvent::query()->where('action', AuditAction::SpendCapHalted->value)->sole();

    expect($event->changes['halted'])->toEqual(['from' => false, 'to' => true])
        ->and($event->note)->toBe('مراجعة تشغيلية مجدولة')
        ->and(AuditAction::SpendCapHalted->isSensitive())->toBeTrue();
});

it('releases the queue from the panel with a written reason, and records it', function (): void {
    $this->cap->halt('اختبار');

    $this->actingAs($this->admin, 'admin')
        ->put('/admin/spend-cap', [
            'halted' => false,
            'note' => 'نُظر في السبب — لا خطر إنفاق فعليّ',
        ])
        ->assertRedirect();

    expect($this->cap->isHalted())->toBeFalse();

    $event = AuditEvent::query()->where('action', AuditAction::SpendCapReleased->value)->sole();

    expect($event->changes['halted'])->toEqual(['from' => true, 'to' => false]);
});

// كبقية الأفعال المالية في هذه اللوحة — لا يمرّ تغييرٌ بلا سببٍ مكتوب.
it('refuses to change the halt state without a written reason', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put('/admin/spend-cap', ['halted' => true, 'note' => ''])
        ->assertSessionHasErrors('note');

    expect($this->cap->isHalted())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe(0);
});

// وحفظٌ بلا تبديل ليس تعديلاً — لا يُقيَّد.
it('writes nothing when the request asks for the state already in effect', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put('/admin/spend-cap', ['halted' => false, 'note' => 'لا حاجة'])
        ->assertSessionHasNoErrors();

    expect(AuditEvent::query()->count())->toBe(0);
});

// 404 لا 403 — كما تفعل بقية شاشات لوحة المشرف.
it('hides the control from a tenant user', function (): void {
    $owner = User::factory()->for_(Tenant::factory()->create())->owner()->create();

    $this->actingAs($owner)
        ->put('/admin/spend-cap', ['halted' => true, 'note' => 'محاولة'])
        ->assertNotFound();

    expect($this->cap->isHalted())->toBeFalse();
});
