<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VerifyCheck;
use Illuminate\Console\Command;

/**
 * Forget what people pasted into the verify tool once its time is up — T-181.
 *
 * **النصُّ لا يبقى أكثر من سبعة أيام، ومعه التقرير** لأنّ فيه ألفاظه. ويبقى
 * الصفّ بكلفته وعدد شواهده وحاله، فيُقرأ منه الاستعمال والإنفاق ولا يُقرأ
 * منه شيءٌ ممّا كتبه أحد. ورابطُ التقرير بعدها يقول «انقضت مدّته».
 */
class PruneVerifyChecks extends Command
{
    protected $signature = 'khulasah:prune-verify-checks';

    protected $description = 'يحذف نصوص أداة التحقّق وتقاريرها بعد مدّتها — T-181';

    public function handle(): int
    {
        $days = (int) config('khulasah.verify.retention_days', 7);

        $count = VerifyCheck::query()
            ->whereNull('purged_at')
            ->where('created_at', '<', now()->subDays($days))
            ->update(['text' => null, 'report' => null, 'purged_at' => now()]);

        $this->info("حُذف نصُّ {$count} طلباً وتقريرُه.");

        return self::SUCCESS;
    }
}
