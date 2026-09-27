<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\QuranTranslation;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * لغات المخرَج — T-38.
 *
 * **والمقياس الحاكم هنا أنّ العربية لا تُنزع**: هي لغةُ المصدر، وعليها يجري
 * التحقّق مرّةً واحدة. وملخّصٌ بلا نسختها ليس له أصلٌ يُراجَع — فلا واجهةٌ
 * تُسقطها ولا طلبٌ محرَّف.
 */

// ★★ **لغةُ المصدر ليست لغةَ نشرٍ مفروضة** — T-51، ونقضُ ما كان في T-38.
// فالتحقّق يجري على العربيّ في الحالين، **ونشرُه قرارُ صاحب المحتوى**: من
// أراد ملخّصاً إنجليزياً وحده لا يُجبَر على نشر العربيّ معه.
it('يحترم اختيار اللغات ولا يفرض العربية', function (): void {
    expect(Locale::normalizeSet(['en']))->toBe(['en'])
        ->and(Locale::normalizeSet(['tr']))->toBe(['tr'])
        ->and(Locale::normalizeSet(['en', 'ar']))->toBe(['ar', 'en']);
});

// **ولا تعود فارغة**: ملخّصٌ بلا لغةٍ ليس مخرَجاً، فمن لم يختر يُنشر له
// بلغة المصدر.
it('يسقط إلى لغة المصدر حين لا اختيار', function (): void {
    expect(Locale::normalizeSet(null))->toBe(['ar'])
        ->and(Locale::normalizeSet([]))->toBe(['ar'])
        ->and(Locale::normalizeSet('نصّ لا قائمة'))->toBe(['ar']);
});

it('يُسقط ما ليس لغةً ولا يكسر', function (): void {
    expect(Locale::normalizeSet(['tr', 'de', 'لغة مخترَعة', 42, null]))->toBe(['tr'])
        ->and(Locale::normalizeSet(['de']))->toBe(['ar']);
});

// الترتيب ثابتٌ كترتيب الحالات — فلا يتبدّل المخرَج بترتيب ما وصل من
// المتصفّح، ولا تُعاد صفحةٌ لأنّ الجهة أعادت ترتيب اختيارها.
it('يُرتّب اللغات ترتيباً ثابتاً', function (): void {
    expect(Locale::normalizeSet(['ru', 'en', 'tr']))->toBe(['en', 'tr', 'ru'])
        ->and(Locale::normalizeSet(['tr', 'ru', 'en']))->toBe(['en', 'tr', 'ru']);
});

// **الأولى تُنشر على الجذر** — ولكلّ ملخّصٍ رابطٌ جذر يُشارَك ويُفهرَس.
it('يعرف اللغة الأولى ولو لم تكن العربية', function (): void {
    expect(Locale::primaryOf([Locale::En, Locale::Tr]))->toBe(Locale::En)
        ->and(Locale::primaryOf([Locale::Ar, Locale::En]))->toBe(Locale::Ar)
        ->and(Locale::primaryOf([]))->toBe(Locale::Ar);
});

it('يقلب الاتّجاه للغات اللاتينية وحدها', function (): void {
    expect(Locale::Ar->direction())->toBe('rtl')
        ->and(Locale::En->direction())->toBe('ltr')
        ->and(Locale::Tr->direction())->toBe('ltr')
        ->and(Locale::Ru->direction())->toBe('ltr');
});

/*
 * ★★ **ولا يترجم نموذجٌ آيةً** — قرار مالك المنتج، 8 أيلول 2026. فلكلّ لغةٍ
 * مترجَمة معرّفُ ترجمةٍ معتمدة، وللعربية `null` لأنّها الأصل لا ترجمة.
 */
it('يعرف لكلّ لغةٍ ترجمةَ قرآنٍ معتمدة', function (): void {
    expect(Locale::Ar->quranTranslationId())->toBeNull()
        ->and(Locale::En->quranTranslationId())->toBe(20)
        ->and(Locale::Ru->quranTranslationId())->toBe(45)
        ->and(Locale::Tr->quranTranslationId())->toBe(77);

    // والاسم يُنسب في الصفحة، فلا تُنشر ترجمةٌ مجهولةُ المترجم.
    foreach (Locale::translatable() as $locale) {
        expect($locale->quranTranslationName())->not->toBeNull();
    }
});

// **قراءةُ الآية حتميّة**: من الجدول لا من نموذج. و`null` جوابٌ صحيح لا
// إخفاق — فمن استدعاها عرض العربيَّ وحده ولم يخترع نصّاً.
it('يقرأ ترجمة الآية من الجدول ولا يخترعها', function (): void {
    QuranTranslation::query()->create([
        'surah' => 16, 'ayah' => 97, 'locale' => 'tr',
        'translation_id' => 77, 'text' => 'Kadın, erkek…',
    ]);

    expect(QuranTranslation::lookup(Locale::Tr, 16, 97)?->text)->toBe('Kadın, erkek…')
        // لغةُ المصدر لا ترجمة لها.
        ->and(QuranTranslation::lookup(Locale::Ar, 16, 97))->toBeNull()
        // ولغةٌ لم تُبذَر بعدُ كذلك.
        ->and(QuranTranslation::lookup(Locale::Ru, 16, 97))->toBeNull();
});

it('يعرض لغات المخرَج في شاشة الهوية', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['locales' => ['tr']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('locales', 4)
            ->where('tenant.locales', ['tr'])
            ->where('locales.0.is_source', true)
            ->where('locales.0.name', 'العربية')
            // ★ تُقال للجهة الترجمةُ المعتمدة، فتعرف ما يُوثق به.
            ->where('locales.2.quran_translation', 'Diyanet İşleri')
        );
});

it('يحفظ لغات المخرَج ويطبّعها', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)
        ->post('/panel/settings/brand', [
            'name_ar' => $tenant->name_ar,
            'palette' => 'emerald',
            'locales' => ['ru', 'tr'],
        ])
        ->assertRedirect();

    expect($tenant->fresh()->brand_kit['locales'])->toBe(['tr', 'ru']);
});

it('يرفض لغةً خارج الأربع ولا يحفظها', function (): void {
    $tenant = Tenant::factory()->create(['brand_kit' => ['locales' => ['ar', 'tr']]]);
    $user = User::factory()->owner()->for_($tenant)->create();

    $this->actingAs($user)
        ->post('/panel/settings/brand', [
            'name_ar' => $tenant->name_ar,
            'palette' => 'emerald',
            'locales' => ['ar', 'de'],
        ])
        ->assertSessionHasErrors('locales.1');

    expect($tenant->fresh()->brand_kit['locales'])->toBe(['ar', 'tr']);
});
