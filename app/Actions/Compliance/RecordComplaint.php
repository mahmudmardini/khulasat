<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Enums\ComplaintKind;
use App\Models\Complaint;
use App\Models\SummaryJob;
use App\Models\Tenant;

/**
 * يقيّد اعتراضاً واصلاً — دراسة المشروع، المادة 15.
 *
 * **ويصل المشرفَ والجهةَ معاً.** فالجهة أقدرُ على التصحيح، والمشرف هو
 * الضامن: شكوى تصل الجهةَ وحدها قد تُهمَل، وشكوى تصل المشرفَ وحده تجعله
 * وسيطاً في كلّ خطأ إملائي.
 */
class RecordComplaint
{
    /**
     * @param  array{kind: string, url: string, contact: string, detail?: string|null}  $input
     */
    public function handle(array $input): Complaint
    {
        $kind = ComplaintKind::tryFrom($input['kind']) ?? ComplaintKind::Other;

        [$tenant, $job] = $this->resolveTarget($input['url']);

        return Complaint::query()->create([
            'tenant_id' => $tenant?->id,
            'summary_job_id' => $job?->id,
            'kind' => $kind->value,
            'url' => $input['url'],
            'contact' => $input['contact'],
            'detail' => $input['detail'] ?? null,
            'status' => 'open',

            // **زمن الوصول لا زمن الإنشاء.** والعمودان يفترقان إن أُعيد
            // إنشاء الصفّ يوماً، والمهلة تُحسب من الأوّل.
            'received_at' => now(),
        ]);
    }

    /**
     * الجهة والملخّص من الرابط المعترَض عليه.
     *
     * والرابط `{tenant}.khulasat.io/{slug}`. **ويُقرأ ولا يُوثَق به**:
     * قد يكون خطأً أو لصفحةٍ حُذفت، والشكوى تُقيَّد على كلّ حال — فمن رفض
     * شكوى لأنّ رابطها لم يُطابق أضاع اعتراضاً صحيحاً على رابطٍ منسوخ خطأً.
     *
     * @return array{0: ?Tenant, 1: ?SummaryJob}
     */
    private function resolveTarget(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if (! is_string($host)) {
            return [null, null];
        }

        $tenantSlug = explode('.', $host)[0];

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if ($tenant === null) {
            return [null, null];
        }

        $job = SummaryJob::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', explode('/', $path)[0] ?: null)
            ->first();

        return [$tenant, $job];
    }
}
