<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Brand\LogoSanitizer;
use App\Support\Render\Palette;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

/*
 * هوية الجهة ورفع الشعار — T-18، والمواصفة §12 المخطر الثاني.
 *
 * **وأخطر ما هنا رفع SVG.** فهو وثيقةُ XML تُنفَّذ في سياق الصفحة التي
 * تعرضها: شعارٌ من جهةٍ يسرق جلسة مديرِ محتوى جهةٍ أخرى إن عُرض بلا تنقية.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['name_ar' => 'جهة الاختبار']);
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Owner->value]);
    $this->editor = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => Role::Editor->value]);
});

function svgFile(string $body): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'svg').'.svg';
    file_put_contents($path, $body);

    return new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true);
}

/*
 * ─── تنقية SVG ───────────────────────────────────────────────────────
 */

/** **السكربت لا يصل إلى التخزين** — معيار القبول الثالث. */
it('يمنع السكربت في SVG من بلوغ التخزين', function (string $payload): void {
    $sanitizer = app(LogoSanitizer::class);

    $attempt = static fn (): array => $sanitizer->sanitize(svgFile(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'.$payload.'</svg>'
    ));

    try {
        $clean = $attempt()['contents'];
    } catch (RuntimeException) {
        // الرفض مقبولٌ كالتنقية: كلاهما يمنع البلوغ.
        return;
    }

    expect(strtolower($clean))
        ->not->toContain('<script')
        ->not->toContain('alert(')
        ->not->toContain('javascript:');

    expect(preg_match('/\son[a-z]+\s*=/i', $clean))->toBe(0);
})->with([
    'وسم سكربت' => '<script>alert(1)</script>',
    'معالج حدث' => '<rect width="10" height="10" onload="alert(1)"/>',
    'معالج نقر' => '<circle cx="5" cy="5" r="4" onclick="alert(1)"/>',
    'رابط جافاسكربت' => '<a href="javascript:alert(1)"><rect width="10" height="10"/></a>',
    'كائن أجنبي' => '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject>',
    'سكربت في CDATA' => '<script><![CDATA[alert(1)]]></script>',
]);

/** والمرجع الخارجي يُمنع: شعارٌ يطلب مورداً بعيداً يُسرّب زيارة كل قارئ. */
it('يمنع المراجع الخارجية في SVG', function (): void {
    $clean = app(LogoSanitizer::class)->sanitize(svgFile(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
        .'<image href="https://tracker.test/pixel.png" width="10" height="10"/></svg>'
    ))['contents'];

    expect($clean)->not->toContain('tracker.test');
});

/** والشعار السليم يمرّ ولا يُفسد. */
it('يقبل SVG سليماً ويُبقي رسمه', function (): void {
    $result = app(LogoSanitizer::class)->sanitize(svgFile(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" fill="#1B4D3E"/></svg>'
    ));

    expect($result['mime'])->toBe('image/svg+xml')
        ->and($result['contents'])->toContain('circle')
        ->and($result['contents'])->toContain('#1B4D3E');
});

/*
 * ─── النوع بالمحتوى لا بالامتداد ─────────────────────────────────────
 */

/**
 * **`logo.png` قد يكون SVG.** والامتداد يكتبه الرافع، فلا يُبنى عليه حكم.
 */
it('يكشف النوع بالمحتوى لا بالامتداد', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'fake').'.png';
    file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    $file = new UploadedFile($path, 'logo.png', 'image/png', null, true);

    $result = app(LogoSanitizer::class)->sanitize($file);

    // عُومل معاملة SVG لا PNG، فنُقّي.
    expect($result['mime'])->toBe('image/svg+xml')
        ->and($result['contents'])->not->toContain('<script');
});

it('يرفض ما ليس PNG ولا SVG', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'bad').'.svg';
    file_put_contents($path, '%PDF-1.4 fake pdf content');

    expect(fn () => app(LogoSanitizer::class)->sanitize(
        new UploadedFile($path, 'logo.svg', 'image/svg+xml', null, true)
    ))->toThrow(RuntimeException::class);
});

it('يرفض ما تجاوز خمسمئة كيلوبايت', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'big').'.png';
    file_put_contents($path, "\x89PNG\r\n\x1a\n".str_repeat('x', 520_000));

    expect(fn () => app(LogoSanitizer::class)->sanitize(
        new UploadedFile($path, 'logo.png', 'image/png', null, true)
    ))->toThrow(RuntimeException::class);
});

/*
 * ─── اللوحات ─────────────────────────────────────────────────────────
 */

it('يعرض ست لوحات كبطاقات ولا حقل HEX', function (): void {
    $this->actingAs($this->owner)
        ->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings/Brand')
            ->has('palettes', 6)
            ->has('palettes.0.swatches', 3));
});

it('يرفض لوحةً ليست من الستّ', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand', ['name_ar' => 'جهة الاختبار', 'palette' => 'لا-وجود-لها'])
        ->assertSessionHasErrors('palette');
});

it('يحفظ اللوحة المختارة', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand', ['name_ar' => 'جهة الاختبار', 'palette' => 'indigo'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_kit['palette'])->toBe('indigo');
});

/*
 * ─── اللوح خلف الشعار — T-125 ───────────────────────────────────────
 */

/** الشعارُ الفاتح لا يحتاج اللوح الذي وضعته T-90 ليبقى مرئياً — اختياريّ. */
it('يحفظ إزالة اللوح خلف الشعار', function (): void {
    $this->actingAs($this->owner)
        ->post('/panel/settings/brand', ['name_ar' => 'جهة الاختبار', 'palette' => 'indigo', 'logo_transparent' => '1'])
        ->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_kit['logo_transparent'])->toBeTrue();
});

/** والافتراض يبقى اللوح — جهةٌ قائمة لا يتغيّر شعارها بصرياً بلا فعلٍ منها. */
it('يعرض الشاشة اختيار اللوح الحالي', function (): void {
    $this->tenant->update(['brand_kit' => ['logo_transparent' => true]]);

    $this->actingAs($this->owner)->get('/panel/settings/brand')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenant.logo_transparent', true));
});

/*
 * ─── المعاينة الحيّة ─────────────────────────────────────────────────
 */

/** **بالقالب الحقيقي لا بمحاكاة** — الشاشة 8. */
it('يعاين بالقالب الحقيقي', function (): void {
    $response = $this->actingAs($this->owner)->get('/panel/settings/brand/preview');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and($html)->toContain('class="unwan"')
        ->and($html)->toContain('class="colophon"')
        ->and($html)->toContain('--emerald:#1B4D3E');
});

/** واللوحة تُجرَّب قبل الحفظ، فيرى الأثر قبل أن يلتزم به. */
it('يعاين لوحةً لم تُحفظ بعد', function (): void {
    $html = $this->actingAs($this->owner)
        ->get('/panel/settings/brand/preview?palette=plum')
        ->getContent();

    expect($html)->toContain('--emerald:'.Palette::find('plum')->vars['emerald'])
        // ولم تُحفظ فعلاً.
        ->and($this->tenant->refresh()->brand_kit['palette'] ?? null)->toBeNull();
});

/*
 * ─── الصلاحيات والعزل ────────────────────────────────────────────────
 */

/** **الهوية للمالك وحده.** والمحرّر ينشئ الملخّصات ولا يبدّل الهوية. */
it('يمنع المحرّر من تعديل الهوية', function (): void {
    $this->actingAs($this->editor)->get('/panel/settings/brand')->assertForbidden();

    $this->actingAs($this->editor)
        ->post('/panel/settings/brand', ['name_ar' => 'اسم آخر', 'palette' => 'indigo'])
        ->assertForbidden();
});

/**
 * غير المسجّل لا يبلغ الشاشة.
 *
 * ويُقاس بالوسيط لا بالاستجابة: **مسار `login` لا وجود له بعد** — شاشته
 * الأولى في T-16 — فحارس `auth` يرمي عند محاولة التحويل إليه. والضمانة
 * التي تخصّ هذه المهمّة أنّ الحارس **مطبَّق**، لا إلى أين يحوّل.
 */
it('يحرس الشاشة بوسيط المصادقة', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'panel/settings/brand'));

    // الرابع معاينةُ ما لم يُحفظ (`POST`) — T-85. والخمسة بعده قوالبُ الكاروسيل
    // (T-173): توليدٌ واعتمادٌ وافتراضيٌّ وحذفٌ ومعاينة، وكلُّها خلف الحارس نفسه.
    expect($routes)->toHaveCount(9);

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('auth');
    }
});

/** ولا يعدّل مالكُ جهةٍ جهةً أخرى — العزل شرطٌ أوّل (§10). */
it('يعزل الجهات: كل مالك يعدّل جهته وحدها', function (): void {
    $other = Tenant::factory()->create(['name_ar' => 'جامع آخر']);
    $otherOwner = User::factory()->create(['tenant_id' => $other->id, 'role' => Role::Owner->value]);

    $this->actingAs($otherOwner)
        ->post('/panel/settings/brand', ['name_ar' => 'اسمٌ مدسوس', 'palette' => 'teal'])
        ->assertSessionHasNoErrors();

    // كُتب على جهته هو، ولم تُمسّ الأولى.
    expect($other->refresh()->name_ar)->toBe('اسمٌ مدسوس')
        ->and($this->tenant->refresh()->name_ar)->toBe('جهة الاختبار');
});
