<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Models\Lecture;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Render\BrandKit;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * إعدادات المخرَج لكل ملخّص — طلبُ مالك المنتج، ٩ أيلول ٢٠٢٦.
 *
 * **والمقياس الحاكم أنّ `null` تعني «كما في إعدادات الجهة» لا قيمةً
 * منسوخة.** فمن بدّل افتراضَ جهته تبدّل معه كلُّ ملخّصٍ لم يختر لنفسه —
 * والافتراضُ افتراضٌ حيّ لا لحظةُ نسخ. ولو نُسخت القيمةُ ساعةَ الإنشاء
 * لتجمّدت مئاتُ الملخّصات على قالبٍ هجرته الجهة.
 */

it('يرث الملخّص افتراضَ جهته حين لا يختار', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'journal', 'locales' => ['ar', 'tr']]]);
    $lecture = Lecture::factory()->for_($tenant)->create(['template' => null, 'locales' => null]);

    expect($lecture->template())->toBe(SummaryTemplate::Journal)
        ->and($lecture->outputLocales())->toBe([Locale::Ar, Locale::Tr]);
});

// ★ الافتراضُ حيّ: تبديلُ إعدادات الجهة يتبدّل معه الملخّصُ القديم.
it('يتبع الملخّصُ افتراضَ الجهة ولو تبدّل بعد إنشائه', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'modern']]);
    $lecture = Lecture::factory()->for_($tenant)->create(['template' => null]);

    expect($lecture->template())->toBe(SummaryTemplate::Modern);

    $tenant->update(['brand_kit' => ['template' => 'lesson']]);

    expect($lecture->fresh()->template())->toBe(SummaryTemplate::Lesson);
});

it('يتجاوز اختيارُ الملخّص افتراضَ جهته', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'classic', 'locales' => ['ar']]]);
    $lecture = Lecture::factory()->for_($tenant)->create([
        'template' => 'brief',
        'locales' => ['ar', 'ru'],
    ]);

    expect($lecture->template())->toBe(SummaryTemplate::Brief)
        ->and($lecture->outputLocales())->toBe([Locale::Ar, Locale::Ru])
        // والهويةُ المرسومة تتبع الملخّص لا الجهة.
        ->and(BrandKit::forTenant($tenant, $lecture)->template)->toBe(SummaryTemplate::Brief)
        // وبلا ملخّصٍ تبقى على افتراض الجهة.
        ->and(BrandKit::forTenant($tenant)->template)->toBe(SummaryTemplate::Classic);
});

it('يعرض شاشةُ الإنشاء القوالبَ واللغاتِ وافتراضَ الجهة', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'research', 'locales' => ['ar', 'en']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->get('/panel/lectures/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Lectures/Create')
            ->has('templates', 6)
            ->has('locales', 4)
            ->where('defaults.template', 'research')
            ->where('defaults.locales', ['ar', 'en'])
        );
});

// ★★ **ما طابق الافتراضَ لا يُكتب** — وإلّا تجمّد الملخّص على قيمةٍ نسخها.
it('لا يكتب تجاوزاً حين يطابق الاختيارُ الافتراض', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'modern', 'locales' => ['ar', 'tr']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'template' => 'modern',
        'locales' => ['ar', 'tr'],
    ]);

    $lecture = Lecture::query()->latest('id')->first();

    expect($lecture->getRawOriginal('template'))->toBeNull()
        ->and($lecture->locales)->toBeNull()
        // ويبقى الأثرُ واحداً: القالبُ المحسوب هو نفسه.
        ->and($lecture->template())->toBe(SummaryTemplate::Modern);
});

it('يكتب التجاوز حين يخالف الاختيارُ الافتراض', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['template' => 'modern', 'locales' => ['ar']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'template' => 'brief',
        'locales' => ['tr'],
    ]);

    $lecture = Lecture::query()->latest('id')->first();

    expect($lecture->getRawOriginal('template'))->toBe('brief')
        ->and($lecture->locales)->toBe(['tr']);
});

it('يرفض قالباً أو لغةً خارج القائمة', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'template' => 'قالبٌ مخترَع',
        'locales' => ['de'],
    ])->assertSessionHasErrors(['template', 'locales.0']);
});

// ★★ **العربيةُ تُنزع كغيرها** — طلبُ مالك المنتج، ٩ أيلول ٢٠٢٦: «لمّا بده
// بس بالإنجليزي يطلع بس بالإنجليزي».
it('يقبل ملخّصاً بالإنجليزية وحدها بلا عربية', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['locales' => ['ar']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'locales' => ['en'],
    ]);

    $lecture = Lecture::query()->latest('id')->first();

    expect($lecture->locales)->toBe(['en'])
        ->and($lecture->outputLocales())->toBe([Locale::En]);
});

/*
 * ══ لوحةُ الألوان تنضمّ إلى إعدادات الملخّص — T-60 ══
 *
 * والقياسُ الحاكم نفسُه: `null` تعني «كما في إعدادات الجهة» لا قيمةً
 * منسوخة، فالافتراضُ حيّ لا لحظةُ نسخ.
 */

it('يرث الملخّص لوحةَ جهته حين لا يختار', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'plum']]);
    $lecture = Lecture::factory()->for_($tenant)->create(['palette' => null]);

    expect($lecture->palette()->key)->toBe('plum');
});

it('يتبع الملخّصُ لوحةَ الجهة ولو تبدّلت بعد إنشائه', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'indigo']]);
    $lecture = Lecture::factory()->for_($tenant)->create(['palette' => null]);

    expect($lecture->palette()->key)->toBe('indigo');

    $tenant->update(['brand_kit' => ['palette' => 'sepia']]);

    expect($lecture->fresh()->palette()->key)->toBe('sepia');
});

it('يتجاوز اختيارُ الملخّص لوحةَ جهته', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'emerald']]);
    $lecture = Lecture::factory()->for_($tenant)->create(['palette' => 'plum']);

    expect($lecture->palette()->key)->toBe('plum')
        ->and(BrandKit::forTenant($tenant, $lecture)->palette->key)->toBe('plum')
        ->and(BrandKit::forTenant($tenant)->palette->key)->toBe('emerald');
});

it('يعرض شاشةُ الإنشاء لوحاتٍ ستّاً وافتراضَ الجهة', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'sepia']]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->get('/panel/lectures/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('palettes', 6)
            ->where('defaults.palette', 'sepia')
        );
});

it('لا يكتب تجاوز لوحةٍ حين يطابق الاختيارُ الافتراض', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'indigo']]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'palette' => 'indigo',
    ]);

    $lecture = Lecture::query()->latest('id')->first();

    expect($lecture->getRawOriginal('palette'))->toBeNull();
});

it('يكتب تجاوز اللوحة حين يخالف الاختيارُ الافتراض', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['palette' => 'indigo']]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'palette' => 'plum',
    ]);

    $lecture = Lecture::query()->latest('id')->first();

    expect($lecture->getRawOriginal('palette'))->toBe('plum');
});

it('يرفض لوحةً خارج القائمة', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->post('/panel/lectures', [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 300),
        'title_ar' => 'درس',
        'speaker_name' => 'الملقي',
        'venue_mode' => 'institution',
        'palette' => 'لوحةٌ مخترَعة',
    ])->assertSessionHasErrors('palette');
});
