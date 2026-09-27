<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Contracts\PublishStore;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Support\Publish\Paths;

/**
 * فهرس ملخّصات الجهة العامّ — يقرؤه `embed.js` (§9).
 *
 * **بلا مصادقة وبلا بيانات خاصّة.** فما يخرج هنا هو ما يراه زائرُ الصفحات
 * أصلاً: عنوانٌ وملقٍ وتاريخٌ ومسار. **ولا حالة مهمّة، ولا كلفة، ولا شاهدٌ
 * محذوف** — وهذه تُطلب صراحةً في الاستعلام لا تُنزع بعده، فالنزعُ يُنسى.
 */
class PublishTenantIndex
{
    public function __construct(private readonly PublishStore $store) {}

    public function handle(Tenant $tenant): string
    {
        $summaries = SummaryJob::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('published_at')
            ->whereNull('unpublished_at')
            ->whereNotNull('slug')
            ->with('lecture:id,speaker_name,gregorian_date,hijri_date')
            ->orderByDesc('published_at')
            ->get(['id', 'lecture_id', 'slug', 'structure_json', 'published_at'])
            ->map(static fn (SummaryJob $job): array => array_filter([
                'slug' => $job->slug,
                'title' => $job->structure_json['title_ar'] ?? $job->lecture?->title_ar,
                'speaker' => $job->lecture?->speaker_name,
                'date' => $job->lecture?->hijri_date
                    ?? $job->lecture?->gregorian_date?->format('Y/m/d'),
                'published_at' => $job->published_at?->toDateString(),
            ], static fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();

        $json = (string) json_encode([
            'tenant' => $tenant->slug,
            'name' => $tenant->name_ar,
            'summaries' => $summaries,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return $this->store->put(Paths::index($tenant->slug), $json, 'application/json; charset=UTF-8');
    }
}
