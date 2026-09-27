<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Render\RenderCarousel;
use App\Enums\OutputType;
use App\Exceptions\ModelCallFailed;
use App\Models\Output;
use App\Models\SummaryJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use RuntimeException;

/**
 * شرائح إنستغرام — SCREENS.md §6 «الكاروسيل»، والمهمّة T-19.
 *
 * **والجهة تنشر على إنستغرام قبل موقعها**، فهذه ليست زينةً على الصفحة بل
 * المخرَج الذي يُشارَك فعلاً. ولذلك تُعرض نصوصُ الشرائح للنسخ اليدويّ إلى
 * جانب المعاينة: من أراد الصور انتظر T-20، ومن أراد النشر اليوم نسخ النصّ.
 */
class CarouselController extends Controller
{
    public function show(SummaryJob $job): InertiaResponse
    {
        return Inertia::render('Jobs/Carousel', $this->payload($job));
    }

    /**
     * يبني الشرائح أو يعيد رسمها.
     *
     * **وإعادة الرسم لا تُحتسب من حصّة إعادة التوليد** — §8-أ. ولذلك لا
     * تمرّ على `RequestRegeneration` كما يمرّ زرّ «أعد المحاولة»: ذاك يُعيد
     * تشغيل الخطّ من أوّله، وهذا يرسم من أصلٍ موجود.
     */
    public function store(Request $request, SummaryJob $job, RenderCarousel $carousel): RedirectResponse
    {
        /*
         * **الشريحة تحرس المخرَج لا الشاشة** — SCREENS.md §3-ب: الكاروسيل
         * «من شريحة مؤسسة فما فوق». والشاشة تبقى مفتوحةً تشرح ما ينقص،
         * «فرؤية ما لا تملكه دافع للترقية، وإخفاؤه يمنع معرفته».
         */
        if (! $job->tenant?->allowsRichOutputs()) {
            return back()->withErrors(['carousel' => trans('jobs.carousel.locked_body')]);
        }

        // النصّ الجديد يُطلب صراحةً — وهو وحده ما يستدعي النموذج ثانيةً.
        $recondense = $request->boolean('recondense');

        try {
            $carousel->handle($job, $recondense);
        } catch (ModelCallFailed $failed) {
            return back()->withErrors(['carousel' => $this->message($failed)]);
        } catch (RuntimeException $refused) {
            return back()->withErrors(['carousel' => $refused->getMessage()]);
        }

        return back();
    }

    /**
     * المعاينة بالقالب الحقيقي، تُعرض في إطار — كما في شاشة الهوية.
     *
     * **ولا تستدعي نموذجاً ولا تكتب صفّاً**: الشرائح محفوظة، والرسم منها
     * مجّانيّ. وطلبُ `GET` يُعاد مع كلّ تحديثِ صفحة، فلا يجوز أن ينشر.
     */
    public function preview(SummaryJob $job, RenderCarousel $carousel): Response
    {
        $html = $carousel->preview($job);

        abort_if($html === null, 404);

        return response($html)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store');
    }

    /** @return array<string, mixed> */
    private function payload(SummaryJob $job): array
    {
        $job->loadMissing('lecture');

        $output = $this->output($job);
        $meta = (array) ($output?->meta ?? []);

        return [
            'job' => [
                'id' => $job->id,
                'title' => $job->lecture?->title_ar,
                'state' => $job->state->value,
                'pending_evidence' => $job->pendingEvidenceCount(),
                // معطَّلٌ لا مخفيّ — SCREENS.md §3-ب.
                'locked' => ! $job->tenant?->allowsRichOutputs(),
            ],
            'carousel' => $output === null ? null : [
                'slides' => $meta['slides'] ?? [],
                'plain_text' => $meta['plain_text'] ?? '',
                'public_url' => $output->public_url,
                'rendered_at' => $output->rendered_at?->toIso8601String(),
                'renderer_version' => $output->renderer_version,
            ],
        ];
    }

    private function output(SummaryJob $job): ?Output
    {
        return $job->outputs()->where('type', OutputType::Carousel->value)->first();
    }

    /**
     * سبب الإخفاق بالعربية.
     *
     * **ورسالةُ فشل المخطّط تُعرض كما هي**: هي التي تقول «الشريحة الثالثة
     * تجاوزت أربعين كلمة»، وهي معلومةٌ نافعة لمن سيضغط «نصّ جديد». وما عداها
     * رسالةٌ عامّة، فلا يُعرض رمزُ مزوّدٍ على مدير محتوى.
     */
    private function message(ModelCallFailed $failed): string
    {
        return $failed->errorCode === 'schema_validation_failed'
            ? trans('jobs.carousel.rejected', ['reason' => $failed->getMessage()])
            : trans('jobs.carousel.failed');
    }
}
