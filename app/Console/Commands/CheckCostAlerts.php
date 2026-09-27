<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Summary\JobState;
use App\Models\SummaryJob;
use App\Notifications\CostAlertTriggered;
use App\Services\Quota\SpendCap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * تنبيهات الكلفة والأداء — T-22.
 *
 * **مكمِّلةٌ لـ{@see EnforceSpendCap} لا بديلة عنها.** تلك توقف الطابور
 * عند ١٠٠٪ من السقف، وهذه تُنذر عند ٨٠٪ — قبل أن تتوقّف الخدمة، لا بعده.
 *
 * وكلّ عتبةٍ تُنذر مرّةً في الساعة لا في كل تشغيلة: **`Cache::add`** يمنع
 * إغراق البريد بتنبيهٍ نفسِه كلّ دقائق ما دامت العتبة متجاوَزة.
 */
class CheckCostAlerts extends Command
{
    protected $signature = 'khulasah:check-cost-alerts';

    protected $description = 'ينذر عند دنوّ سقف الإنفاق، وارتفاع الإخفاق، وتجاوز متوسّط الكلفة — T-22';

    public function handle(SpendCap $cap): int
    {
        $mailTo = config('khulasah.alerts.mail_to');

        if (! is_string($mailTo) || $mailTo === '') {
            Log::warning('[khulasah:check-cost-alerts] لا ALERTS_MAIL_TO مضبوطاً، فلا تنبيه يُرسل.');
            $this->components->warn('لا عنوان بريد مضبوط (ALERTS_MAIL_TO) — تُحسب العتبات ولا يُرسل شيء.');
        }

        $this->checkSpendCap($cap, $mailTo);
        $this->checkFailureRate($mailTo);
        $this->checkAvgCost($mailTo);

        return self::SUCCESS;
    }

    private function checkSpendCap(SpendCap $cap, ?string $mailTo): void
    {
        if ($cap->isHalted()) {
            // ١٠٠٪ بلغها بالفعل — تلك رسالة EnforceSpendCap لا هذه.
            return;
        }

        $ratio = (float) config('khulasah.alerts.spend_cap_warning_ratio');

        $daily = $cap->dailyCapUsd();
        if ($daily > 0 && $cap->spentToday() >= $daily * $ratio) {
            $this->fire($mailTo, 'daily_spend_warning', sprintf(
                'بلغ إنفاق اليوم %.2f$ من سقف %.2f$ (%.0f٪).',
                $cap->spentToday(), $daily, ($cap->spentToday() / $daily) * 100,
            ));
        }

        $monthly = $cap->monthlyCapUsd();
        if ($monthly > 0 && $cap->spentThisMonth() >= $monthly * $ratio) {
            $this->fire($mailTo, 'monthly_spend_warning', sprintf(
                'بلغ إنفاق هذا الشهر %.2f$ من سقف %.2f$ (%.0f٪).',
                $cap->spentThisMonth(), $monthly, ($cap->spentThisMonth() / $monthly) * 100,
            ));
        }
    }

    private function checkFailureRate(?string $mailTo): void
    {
        $since = now()->subHour();
        $total = SummaryJob::query()->where('started_at', '>=', $since)->count();

        if ($total < 5) {
            // عيّنةٌ صغيرة تكذب: مهمّتان أخفقت إحداهما ليستا ٥٠٪ إخفاقٍ حقيقياً.
            return;
        }

        $failed = SummaryJob::query()
            ->where('started_at', '>=', $since)
            ->where('state', JobState::Failed->value)
            ->count();

        $threshold = (float) config('khulasah.alerts.failure_rate_threshold');

        if (($failed / $total) >= $threshold) {
            $this->fire($mailTo, 'failure_rate', sprintf(
                'أخفقت %d من %d مهمّة في الساعة الأخيرة (%.0f٪).',
                $failed, $total, ($failed / $total) * 100,
            ));
        }
    }

    private function checkAvgCost(?string $mailTo): void
    {
        $since = now()->subDay();

        $avg = (float) (SummaryJob::query()
            ->where('total_cost_usd', '>', 0)
            ->where('started_at', '>=', $since)
            ->avg('total_cost_usd') ?? 0);

        $threshold = (float) config('khulasah.alerts.avg_cost_per_summary_usd');

        if ($avg > $threshold) {
            $this->fire($mailTo, 'avg_cost', sprintf(
                'متوسّط كلفة الملخّص في آخر ٢٤ ساعة %.4f$، وهو أعلى من العتبة %.2f$.',
                $avg, $threshold,
            ));
        }
    }

    private function fire(?string $mailTo, string $kind, string $message): void
    {
        $this->components->warn($message);

        // مرّةً في الساعة لكلّ نوع تنبيه — لا يُغرق البريد ما دامت العتبة قائمة.
        $fresh = Cache::add("khulasah:cost-alert:{$kind}", true, now()->addHour());

        if (! $fresh) {
            return;
        }

        Log::warning("[khulasah:check-cost-alerts] {$kind}: {$message}");

        if ($mailTo === null || $mailTo === '') {
            return;
        }

        Notification::route('mail', $mailTo)
            ->notify(new CostAlertTriggered("تنبيه: {$kind}", $message));
    }
}
