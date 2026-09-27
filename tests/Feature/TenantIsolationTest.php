<?php

declare(strict_types=1);

use App\Exceptions\TenantMismatch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\TenantDocument;

beforeEach(function (): void {
    Schema::create('tenant_documents', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->timestamps();
    });

    Route::middleware('web')->get('/_test/documents/{document}', function (string $document) {
        return TenantDocument::findOrFail($document);
    });

    /*
     * ★ ومسارٌ **بربط ضمني** — وهو ما لم يكن مختبَراً.
     *
     * المسار أعلاه يستعلم **داخل** المغلَّف، فيجري بعد `ResolveTenant`
     * فيُطبَّق الحاجز. أمّا الربط الضمني فيجري في `SubstituteBindings`،
     * **وكان ملحَقاً بعده في الترتيب الافتراضي** — فيستعلم بلا جهة، فيمرّ
     * سجلُّ جهةٍ أخرى برقمه. انظر `bootstrap/app.php`.
     */
    Route::middleware('web')->get('/_test/bound/{document}', fn (TenantDocument $document) => $document);

    $this->tenantA = Tenant::factory()->create(['slug' => 'alpha']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'beta']);

    $this->userA = User::factory()->for_($this->tenantA)->owner()->create();
    $this->userB = User::factory()->for_($this->tenantB)->owner()->create();
});

// ── الاختبار الحاكم — المواصفة §10: «غير قابل للتفاوض» ──────────

it('answers 404 — not 403 — when reaching for another tenant record', function (): void {
    // 404 لا 403 عمداً: 403 تُقرّ بوجود السجلّ، فتكشف للمهاجم أنّ المعرّف
    // صحيح وأنّ خلفه بيانات. و404 لا تقول شيئاً.
    $document = tenantDocument($this->tenantB, 'وثيقة الجهة ب');

    $this->actingAs($this->userA)
        ->get("/_test/documents/{$document->id}")
        ->assertNotFound();
});

it('answers 404 for another tenant record bound from the route as well', function (): void {
    // ★ **الحاجز يقرأ من `TenantContext`، فترتيب الوسائط جزءٌ منه.**
    //   ولو جرى الربط قبل أن تُعرَف الجهة لجرى بلا جهة، والحاجزُ قائمٌ
    //   ولا يحجب شيئاً. كشفه اختبار T-16 على `/jobs/{job}`.
    $document = tenantDocument($this->tenantB, 'وثيقة الجهة ب');

    $this->actingAs($this->userA)
        ->get("/_test/bound/{$document->id}")
        ->assertNotFound();
});

it('serves its own tenant record bound from the route', function (): void {
    $document = tenantDocument($this->tenantA, 'وثيقة الجهة أ');

    $this->actingAs($this->userA)
        ->get("/_test/bound/{$document->id}")
        ->assertOk()
        ->assertJsonPath('title', 'وثيقة الجهة أ');
});

it('serves a record from the actor own tenant', function (): void {
    $document = tenantDocument($this->tenantA, 'وثيقة الجهة أ');

    $this->actingAs($this->userA)
        ->get("/_test/documents/{$document->id}")
        ->assertOk()
        ->assertJsonPath('title', 'وثيقة الجهة أ');
});

// ── الحاجز على مستوى الاستعلام ──────────────────────────────────

it('hides other tenants rows from every query', function (): void {
    tenantDocument($this->tenantA, 'أ');
    tenantDocument($this->tenantB, 'ب-١');
    tenantDocument($this->tenantB, 'ب-٢');

    app(TenantContext::class)->set($this->tenantA->id);

    expect(TenantDocument::count())->toBe(1)
        ->and(TenantDocument::pluck('title')->all())->toBe(['أ']);
});

it('hides other tenants users as well', function (): void {
    app(TenantContext::class)->set($this->tenantA->id);

    expect(User::find($this->userB->id))->toBeNull()
        ->and(User::find($this->userA->id))->not->toBeNull();
});

it('lifts the barrier only when asked explicitly', function (): void {
    tenantDocument($this->tenantA, 'أ');
    tenantDocument($this->tenantB, 'ب');

    app(TenantContext::class)->set($this->tenantA->id);

    expect(TenantDocument::count())->toBe(1)
        ->and(TenantDocument::acrossTenants()->count())->toBe(2);
});

// ── ملء tenant_id ورفض الكتابة لجهة أخرى ────────────────────────

it('fills tenant_id from the context on create', function (): void {
    app(TenantContext::class)->set($this->tenantA->id);

    $document = TenantDocument::create(['title' => 'بلا جهة صريحة']);

    expect($document->tenant_id)->toBe($this->tenantA->id);
});

it('refuses to create a record for another tenant', function (): void {
    app(TenantContext::class)->set($this->tenantA->id);

    TenantDocument::create([
        'title' => 'محاولة كتابة باسم الجهة ب',
        'tenant_id' => $this->tenantB->id,
    ]);
})->throws(TenantMismatch::class);

it('refuses to move an existing record to another tenant', function (): void {
    app(TenantContext::class)->set($this->tenantA->id);
    $document = TenantDocument::create(['title' => 'أ']);

    $document->tenant_id = $this->tenantB->id;
    $document->save();
})->throws(TenantMismatch::class);

// ── المشرف العام ────────────────────────────────────────────────

it('leaves the super admin unconfined', function (): void {
    tenantDocument($this->tenantA, 'أ');
    tenantDocument($this->tenantB, 'ب');

    $admin = User::factory()->superAdmin()->create();

    app(TenantContext::class)->set($admin->tenant_id);

    expect($admin->isSuperAdmin())->toBeTrue()
        ->and(TenantDocument::count())->toBe(2);
});

// ── مساعد ───────────────────────────────────────────────────────

function tenantDocument(Tenant $tenant, string $title): TenantDocument
{
    return app(TenantContext::class)->withoutScope(
        fn (): TenantDocument => TenantDocument::create([
            'tenant_id' => $tenant->id,
            'title' => $title,
        ])
    );
}
