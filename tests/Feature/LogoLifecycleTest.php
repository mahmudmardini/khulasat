<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Render\BrandKit;

/*
 * الشعار اختياريّ، والقديمُ بلا شعار — T-99، مراجعة مالك المنتج لـT-98.
 */

const LIFECYCLE_LOGO = 'data:image/png;base64,iVBORw0KGgo=';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name_ar' => 'جهة الاختبار',
        'brand_kit' => ['palette' => 'indigo', 'logo_data_uri' => LIFECYCLE_LOGO],
    ]);
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Owner->value]);
});

/*
 * ─── زرّ الإزالة ─────────────────────────────────────────────────────
 */

it('removes the saved logo when the owner asks, and keeps the rest of the identity', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand', ['name_ar' => 'جهة الاختبار', 'palette' => 'indigo', 'remove_logo' => '1'])
        ->assertSessionHasNoErrors();

    $kit = $this->tenant->refresh()->brand_kit;

    expect($kit)->not->toHaveKey('logo_data_uri')
        ->and($kit['palette'])->toBe('indigo');
});

// والحفظُ بلا طلب إزالةٍ ولا ملفٍّ جديد لا يمسّ الشعار.
it('keeps the saved logo when the identity is saved without touching it', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand', ['name_ar' => 'جهة المعاينة', 'palette' => 'indigo'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_kit['logo_data_uri'])->toBe(LIFECYCLE_LOGO);
});

it('shows the removal in the live preview before saving, and saves nothing', function (): void {
    $draft = fn (array $extra): string => (string) $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', ['name_ar' => 'جهة الاختبار', ...$extra])
        ->assertOk()
        ->getContent();

    expect($draft([]))->toContain('class="brand-mark"')
        ->and($draft(['remove_logo' => '1']))->not->toContain('class="brand-mark"');

    expect($this->tenant->refresh()->brand_kit['logo_data_uri'])->toBe(LIFECYCLE_LOGO);
});

/*
 * ─── القديمُ بلا شعار ────────────────────────────────────────────────
 */

it('draws the logo only for lectures created after logos reached the page', function (): void {
    $old = Lecture::factory()->create(['tenant_id' => $this->tenant->id, 'show_logo' => false]);
    $new = Lecture::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(BrandKit::forTenant($this->tenant, $old)->logoDataUri)->toBeNull()
        ->and(BrandKit::forTenant($this->tenant, $new->fresh())->logoDataUri)->toBe(LIFECYCLE_LOGO)
        // ومعاينةُ الهوية بلا محاضرة: تُري الشعار لتُعين على اختياره.
        ->and(BrandKit::forTenant($this->tenant)->logoDataUri)->toBe(LIFECYCLE_LOGO);
});

it('marks every lecture that predates the migration as logo-less, and new ones not', function (): void {
    $before = Lecture::factory()->create(['tenant_id' => $this->tenant->id]);

    $migration = require database_path('migrations/2026_09_11_120000_add_show_logo_to_lectures.php');
    $migration->down();
    $migration->up();

    $after = Lecture::factory()->create(['tenant_id' => $this->tenant->id]);

    expect($before->fresh()->showsLogo())->toBeFalse()
        ->and($after->fresh()->showsLogo())->toBeTrue();
});

/*
 * ─── حذفُ الشعارات المحفوظة ─────────────────────────────────────────
 */

it('clears stored logos from every tenant and touches nothing else', function (): void {
    $other = Tenant::factory()->create(['brand_kit' => ['template' => 'modern', 'logo_data_uri' => LIFECYCLE_LOGO]]);
    $bare = Tenant::factory()->create(['brand_kit' => ['palette' => 'plum']]);

    (require database_path('migrations/2026_09_11_120100_remove_stored_tenant_logos.php'))->up();

    expect($this->tenant->refresh()->brand_kit)->toBe(['palette' => 'indigo'])
        ->and($other->refresh()->brand_kit)->toBe(['template' => 'modern'])
        ->and($bare->refresh()->brand_kit)->toBe(['palette' => 'plum']);
});
