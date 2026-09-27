<?php

declare(strict_types=1);

namespace App\Enums;

/** مخرجات العارضات — المواصفة §4 و§8-أ. */
enum OutputType: string
{
    /** الصفحة الكاملة. المخرَج الافتراضي لكل الشرائح. */
    case Page = 'page';

    /** شرائح إنستغرام — T-19. */
    case Carousel = 'carousel';

    /** حزمة صور PNG — T-20. **تُنزَّل ولا تُنشر** (§9). */
    case ImageSet = 'image_set';

    public function format(): OutputFormat
    {
        return match ($this) {
            self::Page => OutputFormat::Html,
            self::Carousel => OutputFormat::Html,
            self::ImageSet => OutputFormat::PngZip,
        };
    }

    /** المسار تحت مجلّد الجهة — §9. والصفحة على الجذر، وما عداها فرع. */
    public function pathSuffix(): string
    {
        return match ($this) {
            self::Page => '',
            self::Carousel => '/carousel',
            self::ImageSet => '/images',
        };
    }

    /** **حزمة الصور تُنزَّل ولا تُنشر** — §9. */
    public function isPublished(): bool
    {
        return $this !== self::ImageSet;
    }
}
