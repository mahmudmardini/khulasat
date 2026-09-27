<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\SummaryTemplate;
use App\Models\Lecture;
use App\Models\Tenant;

/**
 * هوية الجهة كما يقرؤها العارض — المواصفة §4 و§8-أ.
 *
 * و`venue_mode` **ليست هنا**: هي على المحاضرة لا على الجهة (`lectures`)،
 * فجهةٌ واحدة قد تنشر محاضرةً باسمها وأخرى منقولةً عن ناشرٍ آخر.
 *
 * **والشعار يُضمَّن Base64** لأنّ المخرَج «ملفّ واحد قائم بذاته» (§8): صفحةٌ
 * تطلب شعارها من خادمنا تنكسر يوم يسقط الخادم أو يُنقل الملفّ، وهي منشورةٌ
 * على CDN لتبقى.
 */
final readonly class BrandKit
{
    public function __construct(
        public string $venueFull,
        public ?string $venueShort,
        public ?string $venueLatin,
        public Palette $palette,
        public ?string $logoDataUri = null,
        public ?string $youtubeUrl = null,
        public ?string $socialUrl = null,
        public ?string $disclaimer = null,
        /** قالب المخرَج — T-45. ومعه هنا لأنّه شكلُ الجهة كاللوحة والشعار. */
        public SummaryTemplate $template = SummaryTemplate::Classic,
        /**
         * T-125 — شعارٌ فاتحٌ أصلاً لا يحتاج اللوح الذي وضعته T-90 ليبقى
         * مرئياً على الترويسة الداكنة؛ صاحبُه يعطّله فيُرسم شفّافاً.
         */
        public bool $logoTransparent = false,
    ) {}

    /**
     * @param  Lecture|null  $lecture  محاضرةٌ تتجاوز افتراضَ الجهة في القالب.
     *
     * **وطلبُ مالك المنتج، ٩ أيلول ٢٠٢٦**: جهةٌ واحدة تنشر أنواعَ محتوًى
     * مختلفة، فالقالبُ اختيارُ الملخّص لا اختيارُ الجهة وحدها. والجهةُ
     * تضع الافتراض، والمحاضرةُ تتجاوزه إن شاءت.
     */
    public static function forTenant(Tenant $tenant, ?Lecture $lecture = null): self
    {
        $kit = (array) ($tenant->brand_kit ?? []);

        return new self(
            venueFull: $tenant->name_ar_full ?? $tenant->name_ar,
            venueShort: $tenant->name_ar,
            venueLatin: $tenant->name_latin,
            palette: $lecture?->palette() ?? Palette::find($kit['palette'] ?? null),
            // ومحاضراتُ ما قبل T-99 بلا شعار ولو رُفع بعدها (`Lecture::showsLogo`).
            // ومعاينةُ الهوية بلا محاضرة، فتُريه لتُعين على اختياره.
            logoDataUri: $lecture === null || $lecture->showsLogo() ? ($kit['logo_data_uri'] ?? null) : null,
            youtubeUrl: $kit['youtube_url'] ?? null,
            socialUrl: $kit['social_url'] ?? null,
            disclaimer: $tenant->disclaimer_text,
            template: $lecture?->template() ?? SummaryTemplate::parse($kit['template'] ?? null),
            logoTransparent: (bool) ($kit['logo_transparent'] ?? false),
        );
    }

    /** الرابط بلا مخطّطه — كما يعرضه القالب في `data-url`. */
    public static function shorten(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return rtrim(preg_replace('#^https?://(www\.)?#', '', $url) ?? $url, '/');
    }
}
