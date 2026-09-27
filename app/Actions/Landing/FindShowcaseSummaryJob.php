<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Domain\Summary\JobState;
use App\Models\SummaryJob;

/**
 * The published summary configured as the landing page's live example — T-119.
 *
 * استُخرج من {@see FindShowcaseSummaryUrl} في T-143: صار للمثال قارئان —
 * رابطُه، ومحتواه الذي يُعرض في البطل — **وشرطُ الظهور واحدٌ لهما**. فلو
 * بقي البحثُ في موضعين لأمكن أن تُلغى خلاصةٌ فيختفي رابطُها ويبقى محتواها.
 */
final class FindShowcaseSummaryJob
{
    public function handle(): ?SummaryJob
    {
        $tenantSlug = (string) config('khulasah.landing.showcase.tenant_slug');
        $summarySlug = (string) config('khulasah.landing.showcase.summary_slug');

        if ($tenantSlug === '' || $summarySlug === '') {
            return null;
        }

        return SummaryJob::query()
            ->whereHas('tenant', fn ($query) => $query->where('slug', $tenantSlug))
            ->where('slug', $summarySlug)
            ->where('state', JobState::Published)
            ->whereNull('unpublished_at')
            ->first();
    }
}
