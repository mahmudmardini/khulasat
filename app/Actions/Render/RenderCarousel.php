<?php

declare(strict_types=1);

namespace App\Actions\Render;

use App\Actions\Stages\CondenseForCarousel;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Services\Render\CarouselRenderer;
use App\Support\Render\CarouselDesign;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedOutput;
use App\Support\Render\SlideDeck;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * يرسم كاروسيل المهمّة — T-19، والمواصفة §8-أ.
 *
 * ★ **وهذه هي التي تجعل «إعادة العرض لا تُحتسب» صحيحةً لا شعاراً.**
 *
 * فالتكثيف نداءُ نموذجٍ يُصرف مالاً، والرسم بعده مجّانيّ. ولو نادى كلُّ رسمٍ
 * النموذجَ لصار تبديلُ لوحةِ ألوانٍ أو تصحيحُ شعارٍ **حدثاً بكلفة**، ولخالف
 * §8-أ نصّاً. ولذلك تُقيَّد الشرائح في `outputs.meta` عند أوّل تكثيف،
 * وتُقرأ بعدها من هناك.
 *
 * و`$recondense` هو الاستثناء الصريح الوحيد: من أراد نصّاً آخر طلبه، ودفع
 * ثمنه — ولا يُدفع ثمنٌ بلا طلب.
 *
 * **ولا يُنشر ما يُرسم هنا** — T-204: الشرائح تُنزَّل صوراً
 * ({@see RenderImageSet})، ويُقيَّد المخرَج لنصوصه وقالبه.
 */
final class RenderCarousel
{
    public function __construct(
        private readonly CondenseForCarousel $condense,
        private readonly RenderOutput $render,
        private readonly ViewFactory $views,
    ) {}

    /**
     * @param  string|null  $design  قالبٌ جديدٌ لشرائح الملخّص (معرّفُ قالبٍ معتمد، أو
     *                               `default`) — T-204. وغيابُه قالبُه الحاليّ.
     */
    public function handle(SummaryJob $job, bool $recondense = false, ?string $design = null): RenderedOutput
    {
        $content = ContentObject::fromJob($job);

        $deck = $recondense ? null : $this->stored($job);
        $deck ??= $this->condense->handle($job, $content);

        return $this->render->handle(
            $job,
            // **بقالب الملخّص لا بافتراضيّ الجهة** — T-204: وبه تُرسم صورُه.
            new CarouselRenderer(
                $this->views,
                $deck,
                $this->pageUrl($job),
                $design === null ? CarouselDesign::forJob($job) : CarouselDesign::forTenant($job->tenant, $design),
            ),
        );
    }

    /** الشرائح المحفوظة من تكثيفٍ سابق، إن وُجدت. */
    private function stored(SummaryJob $job): ?SlideDeck
    {
        $meta = (array) ($this->output($job)?->meta ?? []);

        $deck = SlideDeck::fromArray($meta['slides'] ?? null);

        return $deck->count() === 0 ? null : $deck;
    }

    /**
     * رابط الصفحة الكاملة للشريحة الأخيرة — ويغيب قبل النشر.
     *
     * **باللغة الأولى** ({@see SummaryJob::primaryPage()}) — T-216: أوّلُ صفّ
     * صفحةٍ بلا لغة كان يضع رابطَ `/en` في الكاروسيل العربيّ.
     */
    private function pageUrl(SummaryJob $job): ?string
    {
        return $job->primaryPage()?->public_url;
    }

    private function output(SummaryJob $job): ?Output
    {
        return $job->outputs()->where('type', OutputType::Carousel->value)->first();
    }
}
