<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\I18n\PageStrings;
use App\Support\Render\Palette;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * شاشة الإنشاء — T-95.
 *
 * ★ **«عاين المظهر» بالقالب الحقيقي، مفتوحٌ للمحرّر، ولا يكتب شيئاً.**
 * فالمحرّر يُنشئ الملخّصات ويختار مظهرها، ومعاينةُ الهوية خلف `updateBrand`
 * للمالك وحده. **ولا يرى من الجهة إلّا جهتَه.**
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name_ar' => 'جهة الاختبار', 'name_ar_full' => 'جهة الاختبار']);
    $this->editor = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Editor->value]);
});

it('lets an editor preview an unsaved palette with the real template, saving nothing', function (): void {
    $html = $this->actingAs($this->editor)
        ->get('/panel/lectures/appearance-preview?palette=plum')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->getContent();

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->toContain('--emerald:'.Palette::find('plum')->vars['emerald'])
        ->toContain('جهة الاختبار');

    expect($this->tenant->refresh()->brand_kit['palette'] ?? null)->toBeNull();
});

it('draws the chosen template, not the saved one', function (): void {
    $saved = (string) $this->actingAs($this->editor)->get('/panel/lectures/appearance-preview')->assertOk()->getContent();
    $chosen = (string) $this->actingAs($this->editor)->get('/panel/lectures/appearance-preview?template=modern')->assertOk()->getContent();

    expect($chosen)->not->toBe($saved);
});

it('refuses a palette or a template it does not know', function (string $query): void {
    $this->actingAs($this->editor)->getJson('/panel/lectures/appearance-preview?'.$query)->assertUnprocessable();
})->with(['unknown palette' => 'palette=hotpink', 'unknown template' => 'template=poster', 'unknown venue mode' => 'venue_mode=charity']);

/*
 * وُجد في T-126: كانت «عاين المظهر» تتجاهل نمط النسبة كلّياً، فتُري «المكان»
 * دائماً بصرف النظر عمّا اختاره مدير المحتوى في الخطوة الأولى.
 */
it('drops the venue row from the appearance preview when the chosen attribution hides it', function (): void {
    $withVenue = (string) $this->actingAs($this->editor)
        ->get('/panel/lectures/appearance-preview')
        ->assertOk()
        ->getContent();

    $withoutVenue = (string) $this->actingAs($this->editor)
        ->get('/panel/lectures/appearance-preview?venue_mode=publisher_only')
        ->assertOk()
        ->getContent();

    expect($withVenue)->toContain('جهة الاختبار');
    expect($withoutVenue)->not->toContain('جهة الاختبار');
});

it('shows each tenant its own identity only', function (): void {
    Tenant::factory()->create(['name_ar' => 'مركز آخر', 'name_ar_full' => 'مركز آخر للدراسات']);

    $html = (string) $this->actingAs($this->editor)->get('/panel/lectures/appearance-preview')->getContent();

    expect($html)->toContain('جهة الاختبار')->not->toContain('مركز آخر');
});

it('keeps a guest out of the appearance preview', function (): void {
    $this->get('/panel/lectures/appearance-preview')->assertRedirect('/panel/login');
});

it('hands the create page each language with its approved Quran translation', function (): void {
    $this->actingAs($this->editor)->get('/panel/lectures/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('locales', fn (Collection $locales): bool => $locales->firstWhere('key', 'en')['quran_translation']
                === Locale::En->quranTranslationName()
                && Locale::En->quranTranslationName() !== null)
        );
});

it('hands the attribution line in the words the page itself prints', function (): void {
    // «ما يُرى تحت نمط النسبة هو ما يُطبع في الصفحة» — `summary/partials/attrib`.
    $this->actingAs($this->editor)->get('/panel/lectures/create')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('attribution.lecture_by', PageStrings::of('lecture_by', Locale::Ar))
            ->where('attribution.lecture_at', PageStrings::of('lecture_at', Locale::Ar))
            ->where('attribution.venue', 'جهة الاختبار')
        );
});
