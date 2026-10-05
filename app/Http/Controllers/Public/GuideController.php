<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\Locale;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Support\Guide\GuideBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * دليلُ الاستخدام — T-215. **عامٌّ بلا دخول**: يُعطى لمن لم يرَ النظام قبلُ،
 * ورابطُ قسمٍ منه يُرسل إلى من لا حساب له.
 *
 * **واللغةُ في الرابط لا في الجلسة** (`/guide/en/owner`)، كصفحة التعريف
 * (T-131) لا كاللوحة (T-133): الرابطُ المنسوخ يفتح عند مستلمه باللغة التي
 * نُسخ بها.
 */
class GuideController extends Controller
{
    public function __construct(private readonly GuideBook $book) {}

    /**
     * `/guide` ← دليلُ من فتحه بلغته، أو صفحةُ الاختيار لمن لا حساب له.
     *
     * فرابطُ «دليل الاستخدام» في اللوحة واحدٌ للأدوار كلِّها، ويبلغ كلٌّ دليلَه.
     */
    public function home(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $locale = $user?->locale ?? Locale::parse($request->session()->get('locale'));

        $role = match (true) {
            Auth::guard('admin')->check() => 'admin',
            $user === null => null,
            $user->role === Role::Owner => 'owner',
            default => 'editor',
        };

        return $role === null
            ? redirect()->route('guide.index', ['locale' => $locale->value])
            : redirect()->route('guide.show', ['locale' => $locale->value, 'role' => $role]);
    }

    public function index(string $locale): Response
    {
        $locale = $this->apply($locale);

        return Inertia::render('Guide/Index', [
            'locale' => $locale->value,
            'locales' => $this->locales(),
            'roles' => array_map(static fn (string $role): array => [
                'key' => $role,
                'title' => trans("guide.roles.{$role}.title"),
                'who' => trans("guide.roles.{$role}.who"),
                'covers' => trans("guide.roles.{$role}.covers"),
            ], GuideBook::ROLES),
        ]);
    }

    public function show(string $locale, string $role): Response
    {
        $locale = $this->apply($locale);

        return Inertia::render('Guide/Show', [
            'locale' => $locale->value,
            'role' => $role,
            'locales' => $this->locales(),
            'roles' => array_map(static fn (string $key): array => [
                'key' => $key,
                'title' => trans("guide.roles.{$key}.title"),
                'who' => trans("guide.roles.{$key}.who"),
            ], GuideBook::ROLES),
            'chapters' => $this->book->chapters($locale, $role),
        ]);
    }

    /**
     * لغةُ الصفحة كلِّها — نصوصُ الهيكل و`dir` و`lang` معاً — من الرابط.
     */
    private function apply(string $value): Locale
    {
        $locale = Locale::parse($value);

        app()->setLocale($locale->value);

        return $locale;
    }

    /** @return list<array{value: string, native: string}> */
    private function locales(): array
    {
        return array_map(
            static fn (Locale $locale): array => ['value' => $locale->value, 'native' => $locale->nativeName()],
            Locale::cases(),
        );
    }
}
