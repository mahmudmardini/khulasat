<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;

/*
 * لغةُ اللوحة وشاشات الباب — T-133.
 *
 * **والمقيسُ الآلةُ لا النصّ**: لا يُختبر أنّ عبارةً بعينها تُرجمت — تلك
 * تتبدّل بمراجعة المحرّر — بل أنّ اللغة تُحمل وتثبت، وأنّ المفاتيح متطابقة،
 * وأنّ لوحة المشرف لم تتبعها.
 */

it('يبدأ المستخدم على العربية ما لم يختر غيرها', function (): void {
    expect(User::factory()->create()->locale)->toBe(Locale::Ar);
});

it('يبدّل المستخدمُ لغته فتثبت على صفّه، فلا تضيع بخروجٍ ودخول', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/panel/locale', ['locale' => 'en'])->assertRedirect();

    expect($user->fresh()->locale)->toBe(Locale::En);
});

/*
 * ★ **وشاشاتُ الباب قبل الجلسة، فلا مستخدمَ يُسأل.** ومن فُتحت له اللوحةُ
 * بلغةٍ لا يقرؤها يحتاج التبديل **قبل** أن يدخل — فالمسار خارج حارس `auth`.
 */
it('يبدّل الضيفُ لغةَ شاشة الدخول بلا جلسة، وتتبعه الشاشة', function (): void {
    $this->post('/panel/locale', ['locale' => 'tr'])
        ->assertRedirect()
        ->assertSessionHas('locale', 'tr');

    $this->get('/panel/login')
        ->assertOk()
        ->assertSee('<html lang="tr" dir="ltr">', false);
});

it('يقدّم لغةَ المستخدم على ما اختاره ضيفاً، فاختيارُه المحفوظ أوثق', function (): void {
    $user = User::factory()->create(['locale' => 'ru']);

    $this->withSession(['locale' => 'tr'])
        ->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertSee('<html lang="ru" dir="ltr">', false);
});

it('يسقط إلى العربية على لغةٍ عابثة، ولا يرمي', function (): void {
    $this->post('/panel/locale', ['locale' => 'zz'])->assertRedirect();

    $this->get('/panel/login')->assertSee('<html lang="ar" dir="rtl">', false);
});

/*
 * ★★ **ولوحةُ المشرف عربيةٌ مهما كانت لغةُ المستخدم** — قرارُ T-133: أداةُ
 * عملٍ داخلية لم تُترجَم. ولو تُركت تتبع اللغةَ لعادت `trans('admin')`
 * بالمفتاح نفسِه فرُسمت اللوحةُ بمفاتيحَ عارية.
 */
it('يُبقي نصوص لوحة المشرف عربيةً ولو كانت لغة المستخدم غيرها', function (): void {
    app()->setLocale('en');

    expect(trans('admin', [], 'ar'))->toBeArray()
        ->and(trans('admin', [], 'ar'))->toBe(require lang_path('ar/admin.php'));
});

/*
 * مفاتيحُ اللغات الأربع متطابقة. **وهذا ما يمنع الانحدار الصامت**: مفتاحٌ
 * يُضاف إلى العربية ويُنسى في الثلاث يظهر للقارئ مفتاحاً خاماً
 * (`jobs.status.queued`) لا نصّاً — و`i18n.ts` تُرجع المفتاح عمداً لذلك.
 */
it('يحمل كلُّ ملفّ لغةٍ مفاتيحَ العربية نفسَها، بلا ناقصٍ ولا زائد', function (string $file): void {
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];

        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = array_merge($keys, is_array($value) ? $flatten($value, $path) : [$path]);
        }

        return $keys;
    };

    $arabic = $flatten(require lang_path("ar/{$file}.php"));

    foreach (['en', 'tr', 'ru'] as $locale) {
        $translated = $flatten(require lang_path("{$locale}/{$file}.php"));

        expect(array_diff($arabic, $translated))->toBe([], "{$locale}/{$file}: مفاتيح ناقصة")
            ->and(array_diff($translated, $arabic))->toBe([], "{$locale}/{$file}: مفاتيح زائدة");
    }
})->with(['common', 'jobs', 'lectures', 'review', 'billing', 'auth', 'team', 'templates', 'locales', 'errors', 'landing', 'quiz']);

/*
 * ★★ **الخاصّيتان المشتركتان تتبعان اللغة فعلاً** — عطلٌ لم يُكشف إلّا
 * بتشغيل التطبيق، ومرّ من تحت سبعةَ عشرَ اختباراً.
 *
 * `HandleInertiaRequests::share()` تُنادى **قبل** `$next($request)`، أي قبل
 * `SetAppLocale`. فكانت `locale` و`direction` تُجمَّدان على العربية بينما
 * `lang` تُصيب — لأنّها مغلَّفةٌ في دالّة. وكان الأثر صفحةً إنجليزيةَ النصّ
 * عربيةَ الاتّجاه: القالبُ يُرسل `lang="en"` ثمّ تُعيده القوقعةُ إلى `ar`.
 *
 * **والاختبارُ يقرأ الخاصّية نفسَها** لا الترجمةَ وحدها — فالقديمة كانت
 * تمرّ على ترجمةٍ صحيحة وخاصّيةٍ خاطئة.
 */
it('يبثّ locale وdirection بلغة المستخدم لا بلغة ما قبل الوسيط', function (string $locale, string $direction): void {
    $user = User::factory()->create(['locale' => $locale]);

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertSee('"locale":"'.$locale.'","direction":"'.$direction.'"', false);
})->with([
    ['ar', 'rtl'],
    ['en', 'ltr'],
    ['ru', 'ltr'],
]);

/*
 * ★★ **رمزُ CSRF لا يبيت بعد تجديد الجلسة** — بلاغُ مالك المنتج: «٤١٩
 * صفحة منتهية» بعد دقيقةٍ من فتح اللوحة.
 *
 * **والعلّة أنّ الرمز كان في `<meta>` وحده**، يُرسم مع أوّل وثيقة. والدخولُ
 * يجدّد الجلسة فيجدّد الرمز، وInertia لا تُعيد تحميل الوثيقة — فيبقى
 * الوسمُ حاملاً رمزَ ما قبل الدخول، ويسقط كلُّ طلبٍ لا يمرّ بـInertia:
 * نموذجُ مبدّل اللغة، والفحصُ المسبق (T-16)، ومعاينةُ الهوية (T-85).
 *
 * **ولا يُقاس هنا بـ419**: `VerifyCsrfToken` تتنحّى في الاختبارات، فيُقاس
 * ما يُبنى عليه — أن يكون المبثوثُ هو رمزُ الجلسة الحاضر لا رمزاً سابقاً.
 */
it('يبثّ رمزَ CSRF الحاضر بعد الدخول، لا رمزَ ما قبل تجديد الجلسة', function (): void {
    $user = User::factory()->create();

    $this->get('/panel/login')->assertOk();
    $before = session()->token();

    $this->post('/panel/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $after = session()->token();

    // الدخولُ يجدّد الجلسة فعلاً — وإلّا فلا معنى لما بعده.
    expect($after)->not->toBe($before);

    $this->actingAs($user)
        ->get('/panel')
        ->assertOk()
        ->assertSee('"csrf_token":"'.session()->token().'"', false);
});
