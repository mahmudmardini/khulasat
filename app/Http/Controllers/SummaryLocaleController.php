<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Summary\AddOutputLocale;
use App\Enums\Locale;
use App\Exceptions\LocaleNotAddable;
use App\Models\SummaryJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «أضف لغة» — T-166. من المعاينة ومن شاشة الملخّص معاً.
 */
class SummaryLocaleController extends Controller
{
    public function store(Request $request, SummaryJob $job, AddOutputLocale $add): RedirectResponse
    {
        // صرفٌ ونشرٌ معاً، فلمن ينشر وحده — المواصفة §10.
        abort_unless($request->user()?->role->canPublish() ?? false, 403);

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::enum(Locale::class)],
        ]);

        $locale = Locale::from($validated['locale']);

        try {
            $add->handle($job, $locale);
        } catch (LocaleNotAddable $refused) {
            return back()->withErrors(['locale' => $refused->getMessage()]);
        }

        return back()->with('message', trans('jobs.add_locale.queued', ['locale' => $locale->label()]));
    }
}
