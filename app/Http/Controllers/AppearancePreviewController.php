<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SummaryTemplate;
use App\Enums\VenueMode;
use App\Http\Controllers\Settings\BrandController;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\Palette;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * «عاين المظهر» في شاشة الإنشاء — T-95.
 *
 * القالبُ واللوحة يُختاران لهذا الملخّص قبل أن يوجد، فيُريان **بالقالب
 * الحقيقي على محتوى العيّنة** نفسه الذي تُعاين عليه صفحةُ الهوية
 * ({@see BrandController::sampleContent()}) — لا بصورٍ مصغّرة، وقد رفضها
 * مالك المنتج في T-60.
 *
 * ★ **ومفتوحٌ للمحرّر لا للمالك وحده.** معاينةُ الهوية خلف `updateBrand`
 * لأنّها تُجرّب ما قد يُحفظ في الجهة؛ وهذه لا تُجرّب إلّا ما يُختار لملخّصٍ
 * يُنشئه المحرّر نفسه — ولا تقرأ من الجهة إلّا جهةَ من يطلب.
 *
 * **ولا تكتب شيئاً**: نسخةٌ من الجهة في الذاكرة تُرسم وتُرمى.
 */
class AppearancePreviewController extends Controller
{
    public function __invoke(Request $request, PageRenderer $renderer): Response
    {
        $tenant = $request->user()?->tenant;

        abort_if($tenant === null, 403);

        $input = $request->validate([
            'template' => ['nullable', 'string', Rule::in(array_column(SummaryTemplate::cases(), 'value'))],
            'palette' => ['nullable', 'string', Rule::in(array_keys(Palette::all()))],
            // نمطُ النسبة — T-126: اختيارُ الخطوة الأولى يُسقط «المكان» من
            // هذه المعاينة كما يُسقطه من الملخّص الحقيقي.
            'venue_mode' => ['nullable', 'string', Rule::in(array_column(VenueMode::cases(), 'value'))],
        ]);

        $kit = (array) ($tenant->brand_kit ?? []);
        $kit['palette'] = $input['palette'] ?? $kit['palette'] ?? Palette::DEFAULT;
        $kit['template'] = SummaryTemplate::parse($input['template'] ?? $kit['template'] ?? null)->value;

        $preview = clone $tenant;
        $preview->brand_kit = $kit;

        $venueMode = isset($input['venue_mode']) ? VenueMode::from($input['venue_mode']) : null;

        return response($renderer->render(BrandController::sampleContent($venueMode), BrandKit::forTenant($preview))->contents)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // تُعرض في إطارٍ داخل اللوحة، ولا تُفتح من موقعٍ آخر.
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }
}
