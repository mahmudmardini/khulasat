<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Contracts\ShareCardCapturer;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Models\SummaryJob;
use App\Support\I18n\PageStrings;
use App\Support\Publish\ShareCard;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Storage;

/**
 * Draws and stores the share card of one published summary in one language — T-144.
 *
 * **لا نداءَ نموذج ولا كلفة** (معيار القبول): البطاقةُ من بياناتٍ محسومةٍ
 * قبلها — العنوانُ والمُلقي والجهةُ وشعارُها — تُرسم ولا تُصاغ.
 *
 * **ولا تُولَّد لخلاصةٍ غيرِ منشورة**: البطاقةُ وجهُ صفحةٍ تُشارَك، وخلاصةٌ
 * أُلغي نشرُها لا وجهَ لها.
 */
final class GenerateShareCard
{
    public function __construct(
        private readonly ShareCardCapturer $capturer,
        private readonly ViewFactory $views,
    ) {}

    public function handle(SummaryJob $job, Locale $locale): bool
    {
        if ($job->state !== JobState::Published || $job->unpublished_at !== null || $job->tenant === null) {
            return false;
        }

        $content = ContentObject::fromJob($job, $locale);

        // لغةٌ بلا ترجمة يسقط فيها المحتوى إلى الأصل — فلا بطاقةَ عربيةً باسمها.
        if ($content->locale !== $locale) {
            return false;
        }

        $html = $this->views->make('summary.share-card', self::data($content, BrandKit::forTenant($job->tenant, $job->lecture)))->render();

        $png = $this->capturer->capture($html, ShareCard::WIDTH, ShareCard::HEIGHT);

        if ($png === null || $png === '') {
            return false;
        }

        Storage::disk((string) config('khulasah.share_card.disk'))
            ->put(ShareCard::path((int) $job->id, $locale), $png);

        return true;
    }

    /**
     * The card's view data — shared with the fingerprint in {@see ShareCard::url()}.
     *
     * @return array<string, mixed>
     */
    public static function data(ContentObject $content, BrandKit $brand): array
    {
        $title = trim((string) ($content->majlis['title'] ?? ''));

        return [
            'locale' => $content->locale,
            'palette' => $brand->palette->vars,
            'logoDataUri' => $brand->logoDataUri,
            'logoTransparent' => $brand->logoTransparent,
            'venue' => $content->locale->isSource() ? $brand->venueShort ?? $brand->venueFull : $brand->venueLatin ?? $brand->venueShort,
            'title' => $title,
            // عنوانٌ طويلٌ يُصغَّر ليبقى في سطرين، ولا يُقصّ من الوسط.
            'titleSize' => mb_strlen($title) > 60 ? 54 : (mb_strlen($title) > 34 ? 64 : 76),
            'subtitle' => $content->majlis['subtitle'] ?? null,
            'speaker' => $content->majlis['sheikh_full'] ?? $content->majlis['sheikh'] ?? null,
            'platformName' => PageStrings::of('platform_name', $content->locale),
            'note' => PageStrings::of('share_card_note', $content->locale),
            'domain' => (string) (parse_url((string) config('khulasah.platform_url'), PHP_URL_HOST) ?: 'khulasat.io'),
        ];
    }

    /**
     * What the card shows — its URL changes when any of it does.
     *
     * @return list<string|null>
     */
    public static function fingerprint(ContentObject $content, BrandKit $brand): array
    {
        $data = self::data($content, $brand);

        return [
            $data['title'], $data['subtitle'], $data['speaker'], $data['venue'],
            $brand->palette->key, $brand->logoDataUri === null ? null : md5($brand->logoDataUri),
        ];
    }
}
