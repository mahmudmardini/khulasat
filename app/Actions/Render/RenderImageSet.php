<?php

declare(strict_types=1);

namespace App\Actions\Render;

use App\Contracts\OverflowProbe;
use App\Contracts\ShareCardCapturer;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Services\Render\CarouselRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\CarouselDesign;
use App\Support\Render\ContentObject;
use App\Support\Render\ImageSet;
use App\Support\Render\SlideDeck;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * حزمةُ صور الكاروسيل — T-173، وتستوعب T-20.
 *
 * تلتقط كلَّ شريحةٍ من الكاروسيل المحفوظ صورةً بمقاس إنستغرام، ثمّ تحزمها
 * ZIP ومعها نصُّ المنشور. **ولا نموذج يُنادى هنا**: الشرائحُ محفوظة، وشكلُها
 * من مواصفة الجهة ({@see CarouselDesign})، والالتقاطُ بمتصفّحٍ لا بكلفة.
 *
 * ★ **وكلُّها أو لا شيء.** شريحةٌ تعذّر التقاطُها تُسقط الحزمة: حزمةٌ ناقصةٌ
 * تُنشر على إنستغرام فيضيع ترتيبُ الكاروسيل ولا يدري أحد.
 */
final class RenderImageSet
{
    public function __construct(
        private readonly ShareCardCapturer $capturer,
        private readonly ViewFactory $views,
        private readonly OverflowProbe $probe,
    ) {}

    /**
     * حالُ الحزمة في صفّها، لتراها الشاشة: `rendering` ثمّ `ready` أو `failed`.
     *
     * **ولا تُمسّ الملفّات هنا**: إعادةُ الإنشاء تبدأ بـ«جارية» والصورُ القديمة
     * باقيةٌ حتى تحلّ الجديدة محلّها في {@see self::handle()}.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function mark(SummaryJob $job, string $state, ?string $error = null, array $extra = []): Output
    {
        $output = Output::query()->firstOrNew([
            'summary_job_id' => $job->id,
            'type' => OutputType::ImageSet->value,
            'locale' => Locale::source()->value,
        ]);

        $output->forceFill([
            'tenant_id' => $job->tenant_id,
            'format' => OutputType::ImageSet->format()->value,
            // `at` لا `updated_at`: جدولُ المخرجات بلا طوابع، وبه تُعرف «جاريةٌ» تعثّرت (T-197).
            'meta' => [...(array) ($output->meta ?? []), ...$extra, 'state' => $state, 'error' => $error, 'at' => now()->toIso8601String()],
            'renderer_version' => ImageSet::VERSION,
        ])->save();

        return $output;
    }

    /**
     * @param  string|null  $design  معرّفُ قالبٍ معتمد، أو `default` للأصل. وغيابُه افتراضيُّ الجهة.
     *
     * @throws RuntimeException سببُه يُعرض على المستخدم كما هو.
     */
    public function handle(SummaryJob $job, ?string $design = null): Output
    {
        // الصورُ تُنشر على إنستغرام بيد الجهة، فحكمُها حكمُ المنشور (§2-٤).
        $pending = $job->pendingEvidenceCount();

        if ($pending > 0) {
            throw new RuntimeException(trans('jobs.images.pending', ['count' => $pending]));
        }

        $tenant = $job->tenant ?? throw new RuntimeException(trans('jobs.images.failed'));

        $carousel = $job->outputs()->where('type', OutputType::Carousel->value)->first();
        $deck = SlideDeck::fromArray($carousel?->meta['slides'] ?? null);

        if ($deck->count() === 0) {
            throw new RuntimeException(trans('jobs.images.no_carousel'));
        }

        $chosen = CarouselDesign::forTenant($tenant, $design);
        $pageUrl = $job->outputs()->where('type', OutputType::Page->value)->first()?->public_url;

        $renderer = new CarouselRenderer($this->views, $deck, $pageUrl, $chosen);
        $content = ContentObject::fromJob($job);
        $brand = BrandKit::forTenant($tenant, $job->lecture);

        // ★ **الفيضُ قبل الالتقاط** — T-173. الشريحةُ تقصّ ما فاض صامتة، فنصٌّ
        // لا يسعها يخرج في الصورة مبتوراً ويُنشر. وبلا شاهدة عدّ: المتصفّحُ
        // القائس يطلبها كما يطلبها القارئ.
        $over = $this->probe->overflowing($renderer->render($content->withoutBeacon(), $brand)->contents);

        if ($over !== null && $over !== []) {
            throw new RuntimeException(trans('jobs.images.overflow', ['slides' => implode('، ', $over)]));
        }

        $images = $this->capture($job, $renderer, $content, $brand, $deck->count());

        $disk = ImageSet::disk();

        // **تُمسح القديمة كلُّها أوّلاً**: كاروسيلٌ قصُر من عشرٍ إلى ثمانٍ يترك
        // `09.png` و`10.png` من قبله، فتُعرض شريحتان لم تعودا منه.
        $disk->deleteDirectory(ImageSet::directory($job));

        foreach ($images as $name => $png) {
            $disk->put(ImageSet::directory($job).'/'.$name, $png);
        }

        $zip = $this->zip($images, $deck->toPlainText());
        $disk->put(ImageSet::zipPath($job), $zip);

        $output = self::mark($job, 'ready', null, [
            'count' => count($images),
            'design' => $chosen->id,
            'bytes' => strlen($zip),
        ]);

        $output->forceFill(['rendered_at' => now()])->save();

        return $output;
    }

    /**
     * الصورُ بأسمائها المرقّمة — T-197.
     *
     * **دفعاتٌ لا شريحةٌ شريحة**: متصفّحٌ لكلّ شريحة يأخذ ثانيتين، فعشرُ شرائح
     * عشرون ثانية. والشرائحُ متراصّةً عموداً ({@see CarouselRenderer::strip()})
     * تُلتقط بلقطةٍ لكلّ ثمانٍ ثمّ تُقصّ. **والتقدّمُ يُكتب بعد كلّ دفعة**،
     * فتعرضه الشاشة عدداً لا دوّاراً مبهماً.
     *
     * ومن غير GD لا قصّ، فتُلتقط الشريحةُ وحدها كما كانت: أبطأُ ولا يسقط.
     *
     * @return array<string, string>
     *
     * @throws RuntimeException
     */
    private function capture(SummaryJob $job, CarouselRenderer $renderer, ContentObject $content, BrandKit $brand, int $total): array
    {
        $images = [];
        $batch = function_exists('imagecreatefromstring') ? ImageSet::BATCH : 1;

        self::mark($job, 'rendering', null, ['progress' => ['done' => 0, 'total' => $total]]);

        for ($offset = 0; $offset < $total; $offset += $batch) {
            $length = min($batch, $total - $offset);

            $png = $batch === 1
                ? $this->capturer->capture($renderer->slides($content, $brand)[$offset], ImageSet::WIDTH, ImageSet::HEIGHT)
                : $this->capturer->capture($renderer->strip($content, $brand, $offset, $length), ImageSet::WIDTH, ImageSet::HEIGHT * $length);

            $slices = $png === null || $png === '' ? null : ($batch === 1 ? [$png] : self::slice($png, $length));

            if ($slices === null) {
                throw new RuntimeException(trans('jobs.images.capture_failed', ['slide' => $offset + 1]));
            }

            foreach ($slices as $index => $slice) {
                $images[ImageSet::slideName($offset + $index + 1)] = $slice;
            }

            self::mark($job, 'rendering', null, ['progress' => ['done' => $offset + $length, 'total' => $total]]);
        }

        return $images;
    }

    /**
     * لقطةُ الشريط صوراً بقسمة ارتفاعها — كلُّ شريحةٍ بمقاسها الثابت.
     *
     * **وارتفاعٌ لا ينقسم يُسقط الدفعة**: قصٌّ على غير حدود الشرائح يخرج صوراً
     * تبدأ من منتصف شريحة، فلا تُحزم.
     *
     * @return list<string>|null
     */
    private static function slice(string $png, int $count): ?array
    {
        $image = @imagecreatefromstring($png);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($height % $count !== 0) {
            return null;
        }

        $step = intdiv($height, $count);
        $slices = [];

        for ($index = 0; $index < $count; $index++) {
            $part = imagecrop($image, ['x' => 0, 'y' => $index * $step, 'width' => $width, 'height' => $step]);

            if ($part === false) {
                return null;
            }

            ob_start();
            imagepng($part);
            $slices[] = (string) ob_get_clean();
        }

        return $slices;
    }

    /**
     * الحزمة: الصورُ بأسمائها المرقّمة، و`caption.txt` نصُّ المنشور.
     *
     * والنصُّ بلا «۝» — `SlideDeck::toPlainText()` (T-172): يُلصق في إنستغرام.
     *
     * @param  array<string, string>  $images
     */
    private function zip(array $images, string $caption): string
    {
        $path = storage_path('app/tmp/images-'.Str::random(16).'.zip');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }

        try {
            $zip = new ZipArchive;

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException(trans('jobs.images.failed'));
            }

            foreach ($images as $name => $png) {
                $zip->addFromString($name, $png);
            }

            $zip->addFromString('caption.txt', $caption);
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }
}
