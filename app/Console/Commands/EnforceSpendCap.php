<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Quota\SpendCap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The hourly spend-cap sweep — المواصفة §11.
 *
 * «مهمّة مجدولة كلّ ساعة تجمع `total_cost_usd` لليوم والشهر. عند تجاوز
 * عتبة الإعداد يُوقَف الطابور كلّه وتُرسل تنبيهاً.»
 *
 * **ولا يرفع الوقف من نفسه.** لو رُفع آلياً حين ينزل المجموع — وهو لا
 * ينزل داخل اليوم — لعاد الإحراق. الرفع بيد إنسانٍ نظر في السبب.
 */
class EnforceSpendCap extends Command
{
    protected $signature = 'khulasah:enforce-spend-cap {--release : يرفع الوقف بعد النظر في السبب}';

    protected $description = 'يجمع الإنفاق ويوقف الطابور عند تجاوز السقف — المواصفة §11';

    public function handle(SpendCap $cap): int
    {
        if ($this->option('release')) {
            $cap->release();
            $this->components->info('رُفع وقف الطابور.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            'اليوم %s$ من %s$ · الشهر %s$ من %s$',
            $cap->spentToday(), $cap->dailyCapUsd(),
            $cap->spentThisMonth(), $cap->monthlyCapUsd(),
        ));

        if ($cap->isHalted()) {
            $this->components->warn('الطابور موقوف سلفاً: '.($cap->haltDetails()['reason'] ?? ''));

            return self::SUCCESS;
        }

        $reason = $cap->breachedReason();

        if ($reason === null) {
            return self::SUCCESS;
        }

        $cap->halt($reason);

        // التنبيه بمستوى `error` فتلتقطه المراقبة — ولوحة الكلفة في T-22.
        Log::error('[khulasah:spend-cap] أُوقف الطابور. '.$reason);

        $this->components->error('أُوقف الطابور: '.$reason);

        return self::FAILURE;
    }
}
