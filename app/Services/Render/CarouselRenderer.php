<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Actions\Stages\CondenseForCarousel;
use App\Contracts\Renderer;
use App\Enums\OutputType;
use App\Support\Render\BrandKit;
use App\Support\Render\CarouselDesign;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedOutput;
use App\Support\Render\Slide;
use App\Support\Render\SlideDeck;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * شرائح إنستغرام — المواصفة §8-أ، والمهمّة T-19.
 *
 * **ولا نموذج يُنادى هنا.** التكثيف فعلٌ قائم بذاته
 * ({@see CondenseForCarousel})، ومخرَجُه يُقيَّد في
 * `outputs.meta`. فإعادة رسم الشرائح — بلوحةٍ أخرى، أو بعد تصحيح شعار —
 * تقرأ الشرائح المحفوظة **ولا تصرف توكناً واحداً**، وهو نصّ §8-أ: «إعادة
 * العرض لا تُعيد تشغيل الخطّ ولا تُحتسب من حصّة إعادة التوليد».
 *
 * والمخرَج ملفٌّ واحد قائم بذاته كالصفحة: الشرائح متجاورة، وكلّ شريحة
 * ١٠٨٠×١٣٥٠ بقياسٍ ثابت بالبكسل — فـ`RenderImageSet` (T-173) يلتقطها كما هي
 * بلا حساب، كلَّ شريحةٍ في وثيقتها ({@see self::slides()}).
 *
 * **وشكلُها من مواصفة الجهة** ({@see CarouselDesign}) — T-173. وغيابُها هو
 * المواصفة الافتراضية: القالب كما كان قبلها.
 */
class CarouselRenderer implements Renderer
{
    private const VERSION = '1.2.0';

    public function __construct(
        private readonly ViewFactory $views,
        private readonly SlideDeck $deck,

        /**
         * رابط الصفحة الكاملة، يظهر في الشريحة الأخيرة — §8-أ.
         *
         * **ويغيب حتى تُنشر الصفحة**، فيغيب سطرُه. ورابطٌ مخمَّن قبل النشر
         * يُطبع على شريحةٍ تُنشر على إنستغرام ثمّ لا يفتح شيئاً.
         */
        private readonly ?string $pageUrl = null,
        private readonly ?CarouselDesign $design = null,
    ) {}

    public function type(): OutputType
    {
        return OutputType::Carousel;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function render(ContentObject $content, BrandKit $brand): RenderedOutput
    {
        // **بلا شاهدة عدّ** — T-204: الشرائح لا تُنشر صفحةً، فلا قارئ يُعدّ.
        $html = $this->views->make('carousel.layout', $this->data($content, $brand, $this->deck))->render();

        return new RenderedOutput(
            type: $this->type(),
            format: $this->type()->format(),
            contents: $html,
            rendererVersion: self::VERSION,
            meta: [
                'palette' => $brand->palette->key,
                'slide_count' => $this->deck->count(),

                /*
                 * **نصوص الشرائح تُحفظ مع المخرَج** — T-19 البند ٢: «يحفظ
                 * JSON بنصوص الشرائح في outputs.meta لينسخها المستخدم يدوياً
                 * عند الحاجة». وهي كذلك ما يُقرأ عند إعادة الرسم، فلا يُنادى
                 * النموذج مرّتين على المحتوى نفسه.
                 */
                'slides' => $this->deck->toArray(),
                'plain_text' => $this->deck->toPlainText(),
                'page_url' => $this->pageUrl,
                'design' => $this->design()->toArray(),
            ],
        );
    }

    /**
     * كلُّ شريحةٍ في وثيقتها، بمقاسها وبلا هامش — لتُلتقط صورةً (T-173).
     *
     * @return list<string>
     */
    public function slides(ContentObject $content, BrandKit $brand): array
    {
        return array_map(fn (Slide $slide): string => $this->views->make('carousel.layout', [
            ...$this->data($content, $brand, new SlideDeck([$slide])),
            'capture' => true,
        ])->render(), $this->deck->slides);
    }

    /**
     * شرائحُ متراصّةٌ عموداً في وثيقةٍ واحدة، تُلتقط بلقطةٍ واحدة — T-197.
     *
     * كلُّ شريحةٍ بمقاسها الثابت بلا هامشٍ ولا فاصل، فتُقصّ اللقطةُ صوراً بقسمة
     * ارتفاعها، وتخرج كلُّ صورةٍ كما تخرج من التقاط الشريحة وحدها.
     */
    public function strip(ContentObject $content, BrandKit $brand, int $offset, int $length): string
    {
        return $this->views->make('carousel.layout', [
            ...$this->data($content, $brand, new SlideDeck(array_slice($this->deck->slides, $offset, $length))),
            'capture' => true,
            'strip' => true,
        ])->render();
    }

    /**
     * الكاروسيلُ في إطارٍ داخل اللوحة — T-196.
     *
     * **مصغَّراً ليُرى في إطاره على أيّ عرض**: القالبُ يصغّر نفسه تحت ١٠٨٠ وحده،
     * وإطارُ اللوحة على شاشةٍ عريضة أعرضُ منه، فكانت الشريحةُ تُرى بمقاسها
     * ولا تُرى الأولى إلّا بالتمرير.
     */
    public function preview(ContentObject $content, BrandKit $brand): string
    {
        return $this->views->make('carousel.layout', [
            ...$this->data($content, $brand, $this->deck),
            'fit' => true,
        ])->render();
    }

    /** @return array<string, mixed> ما يشترك فيه الكاروسيل وشرائحُه الملتقَطة. */
    private function data(ContentObject $content, BrandKit $brand, SlideDeck $deck): array
    {
        $majlis = $content->majlis;

        return [
            'brand' => $brand,
            'palette' => $brand->palette,
            'deck' => $deck,
            'design' => $this->design(),
            'title' => (string) ($majlis['title'] ?? ''),
            'sheikh' => $majlis['sheikh_full'] ?? $majlis['sheikh'] ?? null,
            'pageUrl' => $this->pageUrl,
            'sizeClass' => static fn (Slide $slide): string => self::sizeClass($slide),
        ];
    }

    private function design(): CarouselDesign
    {
        return $this->design ?? CarouselDesign::default();
    }

    /**
     * صنف القياس بحسب طول المتن.
     *
     * **والقياس يصغُر ولا يُقتطع النصّ.** شاهدٌ طويل يُعرض بخطٍّ أصغر ويبقى
     * كاملاً، وذاك القيد الذي لا يُخالف في §8-أ.
     */
    private static function sizeClass(Slide $slide): string
    {
        $length = mb_strlen($slide->body);

        return match (true) {
            $length > 420 => 'sz-xs',
            $length > 260 => 'sz-sm',
            $length > 140 => 'sz-md',
            default => 'sz-lg',
        };
    }
}
