<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Brand\GenerateCarouselDesigns;
use App\Exceptions\ModelCallFailed;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * قوالبُ كاروسيل الجهة في الطابور — T-173.
 *
 * نداءُ Opus على صورةٍ وكتالوج يبلغ دقيقة، فلا يُمسَك به طلب. ويُطلق من
 * «هوية الجهة» أو من لوحة المشرف، والحالُ في `brand_kit` تراها الشاشتان.
 *
 * **ومحاولةٌ واحدة**: البوّابة تعيد على فشل المخطّط وتسقط إلى البديل
 * (`on_exhausted: fallback`)، وإعادةُ الطابور فوقها نداءٌ مدفوعٌ ثالث.
 */
final class GenerateTenantCarouselDesigns implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 420;

    public int $uniqueFor = 420;

    public function __construct(public Tenant $tenant) {}

    public function uniqueId(): string
    {
        return (string) $this->tenant->id;
    }

    public function handle(GenerateCarouselDesigns $generate): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant === null) {
            return;
        }

        try {
            $generate->handle($tenant);
        } catch (RuntimeException $refused) {
            GenerateCarouselDesigns::mark($tenant, 'failed', $refused instanceof ModelCallFailed
                ? trans('common.carousel_designs.failed')
                : $refused->getMessage());
        } catch (Throwable $failure) {
            Log::error('carousel_designs_failed', ['tenant_id' => $tenant->id, 'error' => $failure->getMessage()]);

            GenerateCarouselDesigns::mark($tenant, 'failed', trans('common.carousel_designs.failed'));
        }
    }
}
