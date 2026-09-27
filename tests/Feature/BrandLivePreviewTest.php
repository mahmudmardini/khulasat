<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Render\Palette;
use Illuminate\Http\UploadedFile;

/*
 * المعاينة الحيّة لما لم يُحفظ — T-85.
 *
 * **وُجد في T-82:** كانت المعاينة تتبع اللوحة والقالب وحدهما، والاسمُ
 * والتنويه والشعار لا تظهر إلّا بعد الحفظ — فوعدُ SCREENS §8 «أثرُ كلّ
 * تغيير فوراً» نصفُه منفَّذ.
 *
 * ★ **والمقياس الحاكم: الشعار يُنقّى قبل أن يُرسم ولو لم يُحفظ.** فالإطار
 * على أصل اللوحة نفسه، وSVG بسكربت يُرسم فيه يسرق جلسة من يعاين.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name_ar' => 'جهة الاختبار']);
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Owner->value]);
    $this->editor = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Editor->value]);
});

function draftLogo(string $body, string $name = 'logo.svg', string $mime = 'image/svg+xml'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'logo');
    file_put_contents($path, $body);

    return new UploadedFile($path, $name, $mime, null, true);
}

it('shows an unsaved name in the real template, and saves nothing', function (): void {
    $html = $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', ['name_ar' => 'جهة المعاينة', 'palette' => 'plum'])
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->getContent();

    expect($html)->toContain('جهة المعاينة')
        ->and($html)->toContain('--emerald:'.Palette::find('plum')->vars['emerald']);

    $saved = $this->tenant->refresh();

    expect($saved->name_ar)->toBe('جهة الاختبار')
        ->and($saved->brand_kit['palette'] ?? null)->toBeNull();
});

/*
 * ★ **الشعارُ المختار ولم يُحفظ يُرسم** — T-90 وT-98. كان يُهمَل هنا لأنّ
 * قالب الصفحة لم يرسم شعاراً أصلاً؛ فلمّا رسمه صار يُرسم في المعاينة **بعد
 * أن ينقّيه المنقّي نفسه** — والإطارُ على أصل اللوحة، فسكربتٌ فيه يسرق
 * جلسة من يعاين. ولا يُحفظ منه شيء.
 */
it('draws a chosen logo in the live preview only after sanitizing it, and saves nothing', function (): void {
    $html = (string) $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', ['name_ar' => 'جهة الاختبار', 'logo' => draftLogo(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
            .'<script>alert(1)</script><rect width="10" height="10"/></svg>'
        )])
        ->assertOk()
        ->getContent();

    // يُقرأ الشعار من وسمه لا من غياب `data:image/svg+xml`: القوالب تضمّ زخارفها بها.
    preg_match('#<div class="brand-mark"><img src="data:image/svg\+xml;base64,([^"]+)"#', $html, $drawn);

    expect($drawn)->toHaveCount(2);

    expect(base64_decode($drawn[1], true))->toContain('<rect')
        ->not->toContain('<script')
        ->not->toContain('alert(1)');

    expect($this->tenant->refresh()->brand_kit['logo_data_uri'] ?? null)->toBeNull();
});

/*
 * اللوح خلف الشعار اختياريّ — T-125. شعارٌ فاتحٌ أصلاً لا يحتاج اللوح
 * الذي وضعته T-90 ليبقى مرئياً على الترويسة الداكنة.
 */
it('يرسم الشعار بلا لوح حين تُختار الشفافية في المعاينة الحيّة', function (): void {
    $html = (string) $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', [
            'name_ar' => 'جهة الاختبار',
            'logo' => draftLogo('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'),
            'logo_transparent' => '1',
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('class="brand-mark no-plate"');
});

it('يبقي اللوح خلف الشعار افتراضياً في المعاينة الحيّة', function (): void {
    $html = (string) $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', [
            'name_ar' => 'جهة الاختبار',
            'logo' => draftLogo('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'),
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('class="brand-mark"')
        ->not->toContain('class="brand-mark no-plate"');
});

// وشعارٌ يُرفض لا يُوقف المعاينة: تُرسم بلا شعار، والحفظُ يقول سببَ رفضه.
it('keeps drawing the live preview when the chosen logo is refused', function (): void {
    $html = (string) $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', [
            'name_ar' => 'جهة المعاينة',
            'logo' => draftLogo('ليس صورة', 'logo.png', 'image/png'),
        ])
        ->assertOk()
        ->getContent();

    expect($html)->toContain('جهة المعاينة')
        ->not->toContain('class="brand-mark"');
});

/*
 * **والحدُّ في التحقّق لا في المنقّي وحده** — T-124.
 *
 * كانت القاعدة هنا `['nullable','file']` بلا `max`، فتستقبل المعاينة ملفّاً
 * بحجم `upload_max_filesize` (٥١٢م عندنا) ثمّ يقول المنقّي «كبير». والحفظُ
 * ({@see UpdateBrandRequest}) يحدّ بـ٥٠٠ك، فاختلف البابان على الشيء نفسه.
 */
it('refuses an oversized logo in the preview as the save path does', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand/preview', [
            'name_ar' => 'جهة المعاينة',
            'logo' => UploadedFile::fake()->create('logo.png', 900),
        ])
        ->assertInvalid('logo');
});

it('keeps the live preview to the owner', function (): void {
    $this->actingAs($this->editor)
        ->post('/panel/settings/brand/preview', ['name_ar' => 'اسم آخر'])
        ->assertForbidden();
});

it('hands the saved logo to the form so it can be seen before replacing it', function (): void {
    $this->tenant->update(['brand_kit' => ['logo_data_uri' => 'data:image/png;base64,iVBORw0KGgo=']]);

    $this->actingAs($this->owner)->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenant.logo_url', 'data:image/png;base64,iVBORw0KGgo='));
});
