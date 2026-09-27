<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

/*
 * صفحةُ التعريف بأربع لغات — T-131.
 *
 * **والمقيسُ هنا الهيكلُ لا النصّ.** لا يُختبر أنّ عبارةً بعينها تُرجمت —
 * تلك تتبدّل بمراجعة المحرّر ولا تُقاس بالاختبار — بل: أنّ الاتّجاه يتبع
 * اللغة، وأنّ `hreflang` متبادلةٌ بين الأربع، وأنّ العربيةَ لم تتسرّب إلى
 * صفحةٍ غيرِها.
 */

it('يخدم العربية على الجذر بلا بادئة، وباتّجاه RTL', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee(__('landing.hero.h1'), false);
});

it('يخدم اللغات الثلاث على بادئةِ مسارها، وباتّجاه LTR', function (string $locale): void {
    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee(sprintf('<html lang="%s" dir="ltr">', $locale), false);
})->with(['en', 'tr', 'ru']);

it('يردّ 404 على لغةٍ لا نعرفها، فلا يبتلع المقطعُ الواحد ما ليس له', function (): void {
    $this->get('/de')->assertNotFound();
    $this->get('/ar')->assertNotFound(); // العربيةُ على الجذر، ولا نسخةَ لها تحت بادئة
});

it('يضع canonical على رابط اللغة نفسها لا على الجذر دائماً', function (): void {
    $this->get('/')->assertSee('<link rel="canonical" href="'.route('home').'">', false);

    $this->get('/en')->assertSee(
        '<link rel="canonical" href="'.route('home.locale', ['locale' => 'en']).'">',
        false,
    );
});

it('يربط اللغات الأربع بـhreflang متبادلة، وx-default على العربية', function (string $path): void {
    $response = $this->get($path);

    foreach (Locale::all() as $locale) {
        $url = $locale->isSource() ? route('home') : route('home.locale', ['locale' => $locale->value]);

        $response->assertSee(
            sprintf('<link rel="alternate" hreflang="%s" href="%s">', $locale->value, $url),
            false,
        );
    }

    $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.route('home').'">', false);
})->with(['/', '/en', '/tr', '/ru']);

it('لا يُسرّب نصّ الواجهة العربية إلى صفحةٍ غيرِ عربية', function (string $locale): void {
    $arabicUi = [
        __('landing.hero.h1'),
        __('landing.nav.contact'),
        __('landing.faq.h2'),
        __('landing.problem.h2'),
    ];

    $response = $this->get("/{$locale}");

    foreach ($arabicUi as $needle) {
        $response->assertDontSee($needle, false);
    }
})->with(['en', 'tr', 'ru']);

/*
 * ★ **والشاهدُ الشرعيّ مستثنًى بنصّ القرار** (T-131): لفظُ الحديث عربيٌّ
 * في كلّ لغة، والترجمةُ تحته موسومةً — كما يُنشر الملخّصُ غيرُ العربي
 * فعلاً (T-38). وترجمةٌ بلا وسمٍ تُقرأ حديثاً بلغةٍ أخرى.
 */
it('يُبقي لفظ الحديث عربياً في الصفحة غير العربية، ويضع تحته وسمَ ترجمة المعنى', function (string $locale): void {
    $this->get("/{$locale}")
        ->assertSee('إنّما الأعمالُ بالنيّاتِ', false)
        ->assertSee(Locale::from($locale)->meaningLabel(), false);
})->with(['en', 'tr', 'ru']);

it('يعرض مبدّل اللغة بروابط اللغات الأربع', function (): void {
    $response = $this->get('/');

    foreach (Locale::all() as $locale) {
        $response->assertSee($locale->nativeName(), false);
    }
});

it('يردّ خطأ نموذج الدعوة بلغة الصفحة التي أُرسل منها', function (): void {
    $this->from('/en')
        ->post('/invite', ['locale' => 'en'])
        ->assertSessionHasErrors(['name' => __('landing.invite.errors.name', [], 'en')]);

    $this->from('/')
        ->post('/invite', [])
        ->assertSessionHasErrors(['name' => __('landing.invite.errors.name', [], 'ar')]);
});

/*
 * ★ **وصرفُ المصادَق واحدٌ في الموضعين** — T-131 بنى `/{locale}` بمطابقةٍ
 * مستقلّة عن الجذر، فكان المشرفُ يُصرف من `/` ولا يُصرف من `/en`. وقد
 * وُحِّدا على `ResolveLandingDestination` (T-129)، وهذا ما يقفل الباب.
 */
it('يصرف صاحبَ الجلسة من صفحة اللغة كما يصرفه من الجذر', function (string $path): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertRedirect(route('lectures.index'));
})->with(['/', '/en', '/tr', '/ru']);

/*
 * ★ **ولا يُحمَّل خطٌّ لا تنتفع به اللغة.** «ريم كوفي» لا لاتينيةَ فيه
 * أصلاً، و«بلكس عربي» لا تنتفع به الكيريلّية — وحملُهما على صفحةٍ لاتينية
 * ثلاثةُ طلباتٍ خارجية بلا مقابل. وT-114 تعالج الخطوط كلَّها، وهذا يمنع
 * الارتداد قبلها.
 */
it('لا يحمّل خطوط العربية على صفحةٍ لاتينية، ويحمّل أميري في الحالين للشاهد', function (): void {
    $this->get('/')
        ->assertSee('Reem+Kufi', false)
        ->assertSee('IBM+Plex+Sans+Arabic', false)
        ->assertSee('Amiri', false);

    $this->get('/en')
        ->assertDontSee('Reem+Kufi', false)
        ->assertDontSee('IBM+Plex+Sans+Arabic', false)
        ->assertSee('Amiri', false);
});

/*
 * ★ **والقوسان المزهران للعربية وحدها** — بلاغُ مالك المنتج بلقطةٍ تركية.
 *
 * `U+FD3F` يفتح و`U+FD3E` يُغلق **بالترتيب المنطقيّ للعربية**، فتنعكس
 * صورتُهما في سطرٍ لاتينيّ: يُرسم المفتوحُ مغلقاً في أوّل السطر. وهما
 * أصلاً علامةُ نصٍّ قرآنيّ عربيّ لا إطارُ زينةٍ يُلبَس أيَّ نصّ — فنزعُهما
 * تصحيحُ دلالةٍ قبل أن يكون تصحيحَ رسم.
 */
it('لا يضع القوسين المزهرين حول نصٍّ غيرِ عربيّ', function (string $locale): void {
    $copy = require lang_path("{$locale}/landing.php");

    $flat = json_encode($copy, JSON_UNESCAPED_UNICODE);

    expect($flat)->not->toContain("\u{FD3E}")->and($flat)->not->toContain("\u{FD3F}");
})->with(['en', 'tr', 'ru']);

it('يُبقيهما في العربية، فهي موضعُهما', function (): void {
    $copy = require lang_path('ar/landing.php');

    expect($copy['demo']['ayah'])->toContain("\u{FD3F}")->toContain("\u{FD3E}");
});

/*
 * ★★ **لغةُ صفحة التعريف تصحب الزائرَ إلى الباب** — بلاغُ مالك المنتج.
 *
 * كان من يقرأ `/en` ثمّ يضغط «دخول» يجد الشاشةَ عربية: التعريفُ يقرأ لغته
 * من الرابط ولا يكتبها، واللوحةُ تقرأ من الجلسة — ولا شيء يصل بينهما.
 */
it('يحمل لغةَ صفحة التعريف إلى شاشة الدخول', function (string $path, string $locale, string $direction): void {
    $this->get($path)->assertOk();

    $this->get('/panel/login')
        ->assertOk()
        ->assertSee(sprintf('<html lang="%s" dir="%s">', $locale, $direction), false);
})->with([
    ['/en', 'en', 'ltr'],
    ['/tr', 'tr', 'ltr'],
    ['/ru', 'ru', 'ltr'],
    ['/', 'ar', 'rtl'],
]);

/*
 * **ولا تطغى زيارةُ التعريف على اختيارٍ محفوظ**: من ضبط لغة لوحته لا
 * يُبدّلها مرورُه بصفحةِ تعريفٍ بغيرها — {@see SetAppLocale} تقدّم
 * لغةَ المستخدم على الجلسة.
 */
it('لا تُبدّل زيارةُ التعريف لغةَ لوحةِ مستخدمٍ اختارها', function (): void {
    $user = User::factory()->create(['locale' => 'en']);

    $this->get('/')->assertOk(); // صفحةُ تعريفٍ عربية

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr">', false);
});
