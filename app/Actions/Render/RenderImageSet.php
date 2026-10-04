<?php

declare(strict_types=1);

namespace App\Actions\Render;

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
            'meta' => [...(array) ($output->meta ?? []), ...$extra, 'state' => $state, 'error' => $error],
            'renderer_version' => ImageSet::VERSION,
        ])->save();

        return $output;
    }

    /** @throws RuntimeException سببُه يُعرض على المستخدم كما هو. */
    public function handle(SummaryJob $job): Output
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

        $design = CarouselDesign::forTenant($tenant);
        $pageUrl = $job->outputs()->where('type', OutputType::Page->value)->first()?->public_url;

        $documents = (new CarouselRenderer($this->views, $deck, $pageUrl, $design))
            ->slides(ContentObject::fromJob($job), BrandKit::forTenant($tenant, $job->lecture));

        $images = [];

        foreach ($documents as $index => $html) {
            $png = $this->capturer->capture($html, ImageSet::WIDTH, ImageSet::HEIGHT);

            if ($png === null || $png === '') {
                throw new RuntimeException(trans('jobs.images.capture_failed', ['slide' => $index + 1]));
            }

            $images[ImageSet::slideName($index + 1)] = $png;
        }

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
            'design' => $design->id,
            'bytes' => strlen($zip),
        ]);

        $output->forceFill(['rendered_at' => now()])->save();

        return $output;
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
