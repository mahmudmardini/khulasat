<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Brand\GenerateCarouselDesigns;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTenantCarouselDesigns;
use App\Models\Tenant;
use App\Support\Render\TenantCarouselDesigns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * قوالبُ الكاروسيل في «هوية الجهة» — T-173، المرحلة الثانية.
 *
 * الجهةُ تطلب قوالب لهويتها، فتُولَّد في الطابور مرشّحةً، ثمّ تعتمد منها ما
 * تشاء. **والتوليدُ وحده نداءٌ مدفوع**: يحرسه `quota:extra` في المسار (السقف
 * والتعليق، بلا حصّة ملخّص)، وما سواه قراءةٌ وكتابةٌ في `brand_kit`.
 */
class CarouselDesignController extends Controller
{
    public function generate(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);

        // الكاروسيل من شريحة مؤسسة فما فوق — SCREENS.md §3-ب، فقوالبُه كذلك.
        if (! $tenant->allowsRichOutputs()) {
            return back()->withErrors(['carousel_designs' => trans('jobs.carousel.locked_body')]);
        }

        // ضغطتان لا تُطلقان نداءين مدفوعين.
        if (TenantCarouselDesigns::status($tenant)['state'] !== 'generating') {
            GenerateCarouselDesigns::mark($tenant, 'generating');

            GenerateTenantCarouselDesigns::dispatch($tenant);
        }

        return back();
    }

    public function approve(Request $request, string $design): RedirectResponse
    {
        $tenant = $this->tenant($request);

        if (! TenantCarouselDesigns::approve($tenant, $design)) {
            return back()->withErrors(['carousel_designs' => trans('common.carousel_designs.cannot_approve', [
                'max' => TenantCarouselDesigns::MAX_APPROVED,
            ])]);
        }

        return back();
    }

    public function makeDefault(Request $request, string $design): RedirectResponse
    {
        TenantCarouselDesigns::makeDefault($this->tenant($request), $design);

        return back();
    }

    public function destroy(Request $request, string $design): RedirectResponse
    {
        TenantCarouselDesigns::remove($this->tenant($request), $design);

        return back();
    }

    /** القالبُ على كاروسيل العيّنة، يُعرض في إطار. */
    public function preview(Request $request, string $design): Response
    {
        $tenant = $this->tenant($request);
        $found = TenantCarouselDesigns::find($tenant, $design);

        abort_if($found === null, 404);

        return response(TenantCarouselDesigns::previewHtml($tenant, $found))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, 403);

        $this->authorize('updateBrand', $tenant);

        return $tenant;
    }
}
