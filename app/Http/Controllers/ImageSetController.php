<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Render\RenderImageSet;
use App\Enums\OutputType;
use App\Jobs\GenerateImageSet;
use App\Models\SummaryJob;
use App\Support\Render\ImageSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * حزمةُ صور الكاروسيل — T-173. والتنزيلُ مع سائر المخرجات في
 * `PreviewController::download()`.
 *
 * **ولا تُحرَس بحصّة إعادة التوليد**: التقاطٌ من شرائح محفوظة، بلا نموذج
 * ولا كلفة — كإعادة رسم الكاروسيل (§8-أ).
 */
class ImageSetController extends Controller
{
    /** يُنشئ الحزمة أو يُعيدها — في الطابور، والشاشةُ تنتظر. */
    public function store(Request $request, SummaryJob $job): RedirectResponse
    {
        // حدُّ الشريحة على المخرَج كالكاروسيل — SCREENS.md §3-ب.
        if (! $job->tenant?->allowsRichOutputs()) {
            return back()->withErrors(['images' => trans('jobs.carousel.locked_body')]);
        }

        if (! ImageSet::enabled()) {
            return back()->withErrors(['images' => trans('jobs.images.disabled')]);
        }

        if (! $job->outputs()->where('type', OutputType::Carousel->value)->exists()) {
            return back()->withErrors(['images' => trans('jobs.images.no_carousel')]);
        }

        if (($pending = $job->pendingEvidenceCount()) > 0) {
            return back()->withErrors(['images' => trans('jobs.images.pending', ['count' => $pending])]);
        }

        RenderImageSet::mark($job, 'rendering');

        // القالبُ المختار — T-173. ومعرّفٌ لا تعرفه الجهة يسقط إلى افتراضيّها
        // في `CarouselDesign::forTenant()`، فلا يُرفض طلبٌ لقالبٍ حُذف للتوّ.
        $design = $request->string('design')->toString();

        GenerateImageSet::dispatch($job, $design === '' ? null : $design);

        return back();
    }

    /**
     * صورةُ شريحةٍ واحدة، للمعاينة.
     *
     * **ومن قرصٍ خاصّ عبر اللوحة**: `{job}` مقيَّدٌ بجهة المستخدم، فلا تُرى
     * صورُ جهةٍ من جهةٍ أخرى ولو خُمّن رقمُ الملخّص.
     */
    public function show(SummaryJob $job, int $slide): Response
    {
        $disk = ImageSet::disk();
        $path = ImageSet::slidePath($job, $slide);

        abort_unless($disk->exists($path), 404);

        return response((string) $disk->get($path), 200, [
            'Content-Type' => 'image/png',
            // الرابطُ يحمل وقتَ الإنشاء (`?v=`)، فتتجدّد الصورة بإعادة إنشائها.
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
