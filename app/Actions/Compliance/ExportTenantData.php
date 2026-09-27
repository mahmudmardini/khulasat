<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Models\SummaryJob;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * تصدير كامل لبيانات الجهة — T-24، ودراسة المشروع.
 *
 * **«المراكز في أوروبا سوق مستهدف وستسأل عنه».** والتصدير الذي يُعطي
 * جدولاً من المعرّفات ليس تصديراً: الجهة تريد **ملخّصاتها كما تُقرأ**،
 * فتُوضع صفحاتها HTML كاملةً إلى جانب بياناتها الوصفية.
 */
class ExportTenantData
{
    public function handle(Tenant $tenant): string
    {
        $disk = Storage::disk((string) config('khulasah.publish.disk'));

        $relative = "exports/{$tenant->slug}-".now()->format('Ymd-His').'.zip';

        // ZipArchive يكتب على نظام ملفّات حقيقي، فيُبنى مؤقّتاً ثمّ يُرفع.
        $temp = tempnam(sys_get_temp_dir(), 'khulasah-export').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('تعذّر إنشاء أرشيف التصدير.');
        }

        $zip->addFromString('tenant.json', $this->tenantJson($tenant));
        $zip->addFromString('summaries.json', $this->summariesJson($tenant));
        $zip->addFromString('README.txt', $this->readme($tenant));

        foreach ($this->publishedFiles($tenant) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        $disk->put($relative, (string) file_get_contents($temp));

        unlink($temp);

        return $relative;
    }

    private function tenantJson(Tenant $tenant): string
    {
        $kit = (array) ($tenant->brand_kit ?? []);

        // **الشعار يخرج ملفّاً لا سطراً في JSON**، فأرشيفٌ فيه data URI
        // بطول مئة ألف حرف لا يُفتح في محرّر.
        unset($kit['logo_data_uri']);

        return $this->json([
            'name_ar' => $tenant->name_ar,
            'name_ar_full' => $tenant->name_ar_full,
            'name_latin' => $tenant->name_latin,
            'slug' => $tenant->slug,
            'domain' => $tenant->domain,
            'brand_kit' => $kit,
            'disclaimer_text' => $tenant->disclaimer_text,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
        ]);
    }

    private function summariesJson(Tenant $tenant): string
    {
        $summaries = SummaryJob::query()
            ->where('tenant_id', $tenant->id)
            ->with(['lecture', 'evidenceItems', 'outputs'])
            ->get()
            ->map(fn (SummaryJob $job): array => [
                'slug' => $job->slug,
                'state' => $job->state->value,
                'title' => $job->structure_json['title_ar'] ?? $job->lecture?->title_ar,
                'speaker' => $job->lecture?->speaker_name,
                'source_url' => $job->lecture?->source_url,
                'hijri_date' => $job->lecture?->hijri_date,
                'gregorian_date' => $job->lecture?->gregorian_date?->toDateString(),
                'published_at' => $job->published_at?->toIso8601String(),
                'unpublished_at' => $job->unpublished_at?->toIso8601String(),

                // **ونصّ التفريغ يخرج معها.** هو مادّتها الأصلية، ومن صدّر
                // الملخّص بلا أصله أعطى الجهة نصفَ ما تملك.
                'transcript_text' => $job->transcript_text,
                'structure_json' => $job->structure_json,
                'body_html' => $job->body_html,

                'evidence' => $job->evidenceItems->map(static fn ($item): array => [
                    'kind' => $item->kind,
                    'quoted_text' => $item->raw_text,
                    'source_text' => $item->matched_text,
                    'source_ref' => $item->source_ref,
                    'match_status' => $item->match_status?->value,
                    'review_status' => $item->review_status?->value,
                    'source_meta' => $item->source_meta,
                ])->all(),

                'outputs' => $job->outputs->map(static fn ($output): array => [
                    'type' => $output->type->value,
                    'public_url' => $output->public_url,
                    'rendered_at' => $output->rendered_at?->toIso8601String(),
                ])->all(),
            ])
            ->values()
            ->all();

        return $this->json(['count' => count($summaries), 'summaries' => $summaries]);
    }

    /**
     * صفحات الملخّصات HTML كما تُقرأ.
     *
     * @return array<string, string>
     */
    private function publishedFiles(Tenant $tenant): array
    {
        $disk = Storage::disk((string) config('khulasah.publish.disk'));
        $files = [];

        foreach (SummaryJob::query()->where('tenant_id', $tenant->id)->with('outputs')->get() as $job) {
            $kit = (array) ($tenant->brand_kit ?? []);

            foreach ($job->outputs as $output) {
                if ($output->storage_path === null || ! $disk->exists($output->storage_path)) {
                    continue;
                }

                $name = "summaries/{$job->slug}".($output->type->pathSuffix() !== '' ? '-'.trim($output->type->pathSuffix(), '/') : '').'.html';

                $files[$name] = (string) $disk->get($output->storage_path);
            }

            unset($kit);
        }

        // الشعار ملفّاً مستقلّاً، فيُفتح ويُعاد استعماله.
        $logo = (array) ($tenant->brand_kit ?? []);

        if (isset($logo['logo_data_uri']) && preg_match('#^data:([^;]+);base64,(.*)$#s', (string) $logo['logo_data_uri'], $m) === 1) {
            $extension = $m[1] === 'image/svg+xml' ? 'svg' : 'png';
            $files["logo.{$extension}"] = (string) base64_decode($m[2], true);
        }

        return $files;
    }

    private function readme(Tenant $tenant): string
    {
        return implode("\n", [
            'تصدير بيانات '.$tenant->name_ar,
            'بتاريخ '.now()->toDateTimeString(),
            '',
            'tenant.json      — بيانات الجهة وهويتها',
            'summaries.json   — كل الملخّصات ببنيتها وشواهدها ونصوص تفريغها',
            'summaries/*.html — الصفحات المنشورة كما تُقرأ، قائمةً بذاتها',
            'logo.*           — شعار الجهة كما رُفع بعد التنقية',
            '',
            'وصفحات HTML قائمة بذاتها: تُفتح في أيّ متصفّح بلا خادم.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
