<?php

declare(strict_types=1);

namespace App\Actions\Render;

use App\Actions\Publish\PublishSummary;
use App\Actions\Stages\CondenseForCarousel;
use App\Domain\Summary\JobState;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Services\Render\CarouselRenderer;
use App\Support\Render\BrandKit;
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
 */
final class RenderCarousel
{
    public function __construct(
        private readonly CondenseForCarousel $condense,
        private readonly RenderOutput $render,
        private readonly PublishSummary $publish,
        private readonly ViewFactory $views,
    ) {}

    public function handle(SummaryJob $job, bool $recondense = false): RenderedOutput
    {
        $content = ContentObject::fromJob($job);

        $deck = $recondense ? null : $this->stored($job);
        $deck ??= $this->condense->handle($job, $content);

        $output = $this->render->handle(
            $job,
            new CarouselRenderer($this->views, $deck, $this->pageUrl($job)),
        );

        /*
         * **ولا يُنشر إلّا ما كان منشوراً.** فمهمّةٌ لم تُنشر بعدُ لو مرّت
         * على {@see PublishSummary} لانتقلت إلى `published` بكاروسيلٍ وحده
         * وبلا صفحة — نشرٌ لم يطلبه أحد. والرسم قبل النشر جائزٌ للمعاينة،
         * والنشر قرارٌ مستقلّ.
         */
        if ($job->state === JobState::Published) {
            $this->publish->handle($job, [$output->type->value => $output->contents]);
        }

        return $output;
    }

    /**
     * المعاينة: ترسم من الشرائح المحفوظة **ولا تكتب شيئاً**.
     *
     * و`handle` لا تصلح للمعاينة: تُقيّد الصفّ وتنشر الملفّ. وطلبُ `GET`
     * يُعاد بكلّ تحديث صفحة، فينشر مخرَجاً لم يطلب أحدٌ نشره ويكتب
     * `rendered_at` جديداً في كلّ فتحة. **والقراءة لا تُغيّر حالاً.**
     *
     * وتعود `null` قبل أن تُبنى الشرائح، فلا شيء يُعاين.
     */
    public function preview(SummaryJob $job): ?string
    {
        $deck = $this->stored($job);

        if ($deck === null) {
            return null;
        }

        return (new CarouselRenderer($this->views, $deck, $this->pageUrl($job)))
            ->render(ContentObject::fromJob($job), BrandKit::forTenant($job->tenant, $job->lecture))
            ->contents;
    }

    /** الشرائح المحفوظة من تكثيفٍ سابق، إن وُجدت. */
    private function stored(SummaryJob $job): ?SlideDeck
    {
        $meta = (array) ($this->output($job)?->meta ?? []);

        $deck = SlideDeck::fromArray($meta['slides'] ?? null);

        return $deck->count() === 0 ? null : $deck;
    }

    /** رابط الصفحة الكاملة للشريحة الأخيرة — ويغيب قبل النشر. */
    private function pageUrl(SummaryJob $job): ?string
    {
        $page = $job->outputs()->where('type', OutputType::Page->value)->first();

        return $page?->public_url;
    }

    private function output(SummaryJob $job): ?Output
    {
        return $job->outputs()->where('type', OutputType::Carousel->value)->first();
    }
}
