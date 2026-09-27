<?php

declare(strict_types=1);

namespace App\Support\Landing;

use App\Actions\Landing\FindShowcaseSummaryUrl;
use App\Actions\Landing\LoadShowcaseSummary;
use App\Enums\Locale;

/**
 * The landing view's data, shared by the root route and its locale prefixes — T-143.
 *
 * مسارانِ يرسمان القالبَ نفسه (T-131)، **فبياناتُه تُجمع في موضعٍ واحد**:
 * مثالٌ يُضاف إلى أحدهما وحده يُظهر الخلاصةَ الحقيقيةَ بالعربية ويُخفيها
 * بالإنجليزية، ولا ينكسر اختبار.
 */
final class LandingView
{
    /** @return array{showcaseUrl: ?string, showcase: ?Showcase} */
    public static function data(FindShowcaseSummaryUrl $showcaseUrl, LoadShowcaseSummary $showcase): array
    {
        $loaded = $showcase->handle(Locale::parse(app()->getLocale()));

        return [
            'showcase' => $loaded,
            'showcaseUrl' => $loaded?->url ?? $showcaseUrl->handle(),
        ];
    }
}
