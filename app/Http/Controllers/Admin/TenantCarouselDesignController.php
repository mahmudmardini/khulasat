<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordAudit;
use App\Actions\Brand\GenerateCarouselDesigns;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateTenantCarouselDesigns;
use App\Models\Tenant;
use App\Support\Render\TenantCarouselDesigns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * قوالبُ كاروسيل الجهة من لوحة المشرف — T-173، بطلب @HasanSiwi.
 *
 * المشرف يكتب لجهةٍ **تعليماتِ توليدٍ خاصّة** تحلّ محلّ الافتراضية، ثمّ يعيد
 * توليد قوالبها بها، ويرى المرشّحة والمعتمدة كما تراها الجهة. **والاعتمادُ
 * يبقى للجهة**: ما يولّده المشرف مرشَّحٌ في شاشتها حتى تعتمده.
 *
 * وكلاهما يُقيَّد في السجلّ: التعليماتُ نصٌّ يصل نموذجاً مدفوعاً باسم الجهة،
 * والتوليدُ إنفاقٌ عليها.
 */
class TenantCarouselDesignController extends Controller
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function updatePrompt(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'prompt' => ['nullable', 'string', 'max:20000'],
        ]);

        // فارغٌ يعني الافتراضية، لا نصّاً فارغاً يصل النموذج بلا تعليمات.
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $tenant->carousel_design_prompt = $prompt === '' ? null : $prompt;

        $changes = $this->audit->diff($tenant, ['carousel_design_prompt']);

        if ($changes === []) {
            return back();
        }

        $tenant->save();

        $this->audit->handle(AuditAction::TenantCarouselPrompt, $tenant, $changes);

        return back()->with('message', trans('common.carousel_designs.prompt_saved'));
    }

    public function generate(Tenant $tenant): RedirectResponse
    {
        if (TenantCarouselDesigns::status($tenant)['state'] !== 'generating') {
            GenerateCarouselDesigns::mark($tenant, 'generating');

            GenerateTenantCarouselDesigns::dispatch($tenant);

            $this->audit->handle(AuditAction::TenantCarouselDesigns, $tenant);
        }

        return back();
    }

    public function preview(Tenant $tenant, string $design): Response
    {
        $found = TenantCarouselDesigns::find($tenant, $design);

        abort_if($found === null, 404);

        return response(TenantCarouselDesigns::previewHtml($tenant, $found))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }
}
