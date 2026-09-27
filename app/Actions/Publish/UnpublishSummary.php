<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Contracts\PublishStore;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Support\Publish\Paths;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * إزالة ملخّصٍ منشور — المواصفة §9.
 *
 * **و410 لا 404.** «إزالة من التخزين + صفحة 410 محفوظة». والفرق ليس تقنياً:
 * **404 تقول «لم يكن هنا شيء»، و410 تقول «كان هنا وأُزيل»** — والثانية هي
 * الصادقة لرابطٍ شاركه الناس ونسخوه في مجموعاتهم. ومن ردّ 404 كذَب على
 * قارئٍ يعلم أنّه رأى الصفحة بعينه.
 *
 * ولذلك تُكتب شاهدةٌ مكان الصفحة بدل حذف الملفّ حذفاً: التخزين الساكن لا
 * يردّ رمز حالةٍ من عنده، فالشاهدة هي ما يبقى.
 */
class UnpublishSummary
{
    public function __construct(
        private readonly PublishStore $store,
        private readonly ViewFactory $views,
    ) {}

    public function handle(SummaryJob $job): void
    {
        $tenant = $job->tenant;

        if ($tenant === null || $job->slug === null) {
            return;
        }

        foreach ($job->outputs()->get() as $output) {
            /** @var Output $output */
            if ($output->storage_path === null) {
                continue;
            }

            $this->store->delete($output->storage_path);

            $output->forceFill(['storage_path' => null, 'public_url' => null])->save();
        }

        /*
         * الشاهدة تحلّ محلّ الصفحة نفسها لا محلّ كلّ مخرَج: الكاروسيل فرعٌ
         * منها، ومن بلغه بلغ الصفحة أوّلاً.
         */
        $this->store->put(
            Paths::tombstone($tenant->slug, $job->slug),
            $this->views->make('summary.gone', [
                'tenantName' => $tenant->name_ar,
                'tenantUrl' => Paths::publicUrl($tenant->slug, '', OutputType::Page),
            ])->render(),
            'text/html; charset=UTF-8',
        );

        $job->forceFill(['unpublished_at' => now(), 'published_at' => null])->save();
    }
}
