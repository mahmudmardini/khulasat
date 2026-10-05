<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Role;
use App\Http\Controllers\Admin\TenantController;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Guide\GuideBook;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * T-215 — دليلُ الاستخدام: ثلاثةُ أدلّة بحسب الدور، باللغات الأربع.
 *
 * **والمعرّفاتُ واحدةٌ في اللغات الأربع**: الرابطُ المنسوخ من العربية يفتح
 * القسمَ نفسه بالإنجليزية، وتبديلُ اللغة لا يُضيع موضعَ القارئ.
 */

/** @return list<array{0: string, 1: string}> */
function guideCombos(): array
{
    $combos = [];

    foreach (Locale::cases() as $locale) {
        foreach (GuideBook::ROLES as $role) {
            $combos[] = [$locale->value, $role];
        }
    }

    return $combos;
}

it('serves every guide in every locale without signing in', function (string $locale, string $role): void {
    $this->get("/guide/{$locale}/{$role}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Guide/Show')
            ->where('locale', $locale)
            ->where('role', $role)
            ->has('roles', 3)
            ->has('chapters', count((new GuideBook)->manifest()[$role])));
})->with(guideCombos());

it('lays every page out in the direction of its locale', function (): void {
    $this->get('/guide/en/owner')->assertSee('dir="ltr"', false);
    $this->get('/guide/ar/owner')->assertSee('dir="rtl"', false);
});

it('serves the role picker in every locale', function (string $locale): void {
    $this->get("/guide/{$locale}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Guide/Index')->has('roles', 3));
})->with(['ar', 'en', 'tr', 'ru']);

it('answers unknown roles and locales with 404', function (): void {
    $this->get('/guide/ar/viewer')->assertNotFound();
    $this->get('/guide/fr/owner')->assertNotFound();
});

it('opens each signed-in role on its own guide', function (): void {
    $tenant = Tenant::factory()->create();

    $owner = User::factory()->for($tenant)->create(['role' => Role::Owner, 'locale' => Locale::En]);
    $this->actingAs($owner)->get('/guide')->assertRedirect('/guide/en/owner');

    $editor = User::factory()->for($tenant)->create(['role' => Role::Editor, 'locale' => Locale::Ar]);
    $this->actingAs($editor)->get('/guide')->assertRedirect('/guide/ar/editor');
});

it('sends a visitor without an account to the role picker', function (): void {
    $this->get('/guide')->assertRedirect('/guide/ar');
});

it('keeps the same section anchors in every locale, so a copied link survives a language switch', function (string $role): void {
    $book = new GuideBook;
    $anchors = static fn (Locale $locale): array => collect($book->chapters($locale, $role))
        ->flatMap(fn (array $chapter): array => [$chapter['id'], ...array_column($chapter['topics'], 'id')])
        ->all();

    $arabic = $anchors(Locale::Ar);

    expect($arabic)->toHaveCount(count(array_unique($arabic)));

    foreach ([Locale::En, Locale::Tr, Locale::Ru] as $locale) {
        expect($anchors($locale))->toBe($arabic, "{$locale->value}/{$role}");
    }
})->with(GuideBook::ROLES);

it('has every chapter written in every locale', function (): void {
    $book = new GuideBook;
    $chapters = collect($book->manifest())->flatten()->unique();

    foreach (Locale::cases() as $locale) {
        foreach ($chapters as $chapter) {
            expect(resource_path("guide/{$locale->value}/{$chapter}.md"))->toBeFile();
        }
    }
});

it('leaves no placeholder, role block or missing screenshot behind', function (string $locale, string $role): void {
    $book = new GuideBook;

    foreach ($book->chapters(Locale::from($locale), $role) as $chapter) {
        expect($chapter['title'])->not->toBe('')
            ->and($chapter['html'])->not->toContain('{{')
            ->and($chapter['html'])->not->toContain(':::')
            ->and($chapter['html'])->not->toContain('shot:')
            ->and($chapter['html'])->not->toContain('[!');
    }
})->with(guideCombos());

it('has a screenshot on disk for every one the text points to', function (): void {
    $book = new GuideBook;

    foreach (glob(resource_path('guide/*/*.md')) as $file) {
        preg_match_all('~\(shot:([a-z0-9-]+)\)~', (string) file_get_contents($file), $found);
        $locale = Locale::from(basename(dirname($file)));

        foreach ($found[1] as $name) {
            expect($book->shot($locale, $name))->not->toBeNull("{$file}: {$name}");
        }
    }
});

it('links only to sections that exist in the same guide', function (string $locale, string $role): void {
    $chapters = (new GuideBook)->chapters(Locale::from($locale), $role);
    $anchors = collect($chapters)->flatMap(fn (array $chapter): array => [$chapter['id'], ...array_column($chapter['topics'], 'id')]);

    foreach ($chapters as $chapter) {
        preg_match_all('~href="#([^"]+)"~', $chapter['html'], $links);

        foreach ($links[1] as $anchor) {
            expect($anchors)->toContain($anchor);
        }
    }
})->with(guideCombos());

it('states the limits the code applies, not numbers typed into the text', function (): void {
    config(['khulasah.verify.per_hour' => 9]);

    $html = collect((new GuideBook)->chapters(Locale::En, 'owner'))->pluck('html')->implode('');

    expect($html)->toContain('9 requests');
});

it('gives each role only what concerns it', function (): void {
    $book = new GuideBook;
    $ids = static fn (string $role): array => array_column($book->chapters(Locale::Ar, $role), 'id');

    expect($ids('owner'))->toContain('team', 'brand')
        ->and($ids('editor'))->toContain('permissions')->not->toContain('team', 'brand')
        ->and($ids('admin'))->toContain('admin-tenants', 'admin-costs')->not->toContain('create');
});

it('reserves the guide slug for tenants', function (): void {
    $reserved = (new ReflectionClassConstant(TenantController::class, 'RESERVED_SLUGS'))->getValue();

    expect($reserved)->toContain('guide');
});
