<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

/*
 * نصوص الواجهة المبثوثة مع كل استجابة Inertia — HandleInertiaRequests.
 *
 * **كلّ ملفّ `lang/ar/*.php` تقرؤه شاشةٌ يجب أن يكون في قائمة `translations()`.**
 * من نُسي منها يعود مفتاحُه خاماً في الواجهة («templates.label» بدل
 * «قالب الصفحة») — وهذا وقع فعلاً بعد إضافة `lang/ar/templates.php`.
 */
it('يبثّ ملفّ templates ضمن نصوص الواجهة', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => Role::Owner->value]);

    $this->actingAs($owner)
        ->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('lang.templates.label', 'قالب الصفحة')
            ->has('lang.templates.classic.label'));
});
