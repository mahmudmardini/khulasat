<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Actions\Stages\CondenseForCarousel;
use App\Contracts\Renderer;
use App\Enums\OutputType;
use App\Support\Publish\Beacon;
use App\Support\Render\BrandKit;
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
 * ١٠٨٠×١٣٥٠ بقياسٍ ثابت بالبكسل — فـ`ImageRenderer` (T-20) يلتقطها كما هي
 * بلا حساب.
 */
class CarouselRenderer implements Renderer
{
    private const VERSION = '1.1.0';

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
        $majlis = $content->majlis;

        $html = $this->views->make('carousel.layout', [
            'brand' => $brand,
            'palette' => $brand->palette,
            'deck' => $this->deck,
            'title' => (string) ($majlis['title'] ?? ''),
            'sheikh' => $majlis['sheikh_full'] ?? $majlis['sheikh'] ?? null,
            'pageUrl' => $this->pageUrl,
            'sizeClass' => static fn (Slide $slide): string => self::sizeClass($slide),

            // شاهدة العدّ — T-31. والشرائح مخرَجٌ منشور، فتُعدّ كالصفحة.
            // واللسانُ فيها كذلك (T-140): الكاروسيل يُبنى بلغةٍ بعينها.
            'beacon' => Beacon::for($content->summaryJobId, OutputType::Carousel, $content->locale),
        ])->render();

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
            ],
        );
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
