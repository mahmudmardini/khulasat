<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Domain\Summary\JobState;
use App\Enums\UsageEvent;
use App\Models\ModelCall;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Quota\SpendCap;
use App\Support\Billing\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * يجمع أرقام شاشة الكلفة — T-22.
 *
 * **ثلاثة مصادر، ولكلٍّ ما لا يملكه غيره** — وضُبطت نسبتُها في T-103:
 *
 *   ١. **`usage_ledger`** — «مصدر الحقيقة للحصص والفوترة» (§4). منه مجاميعُ
 *      اليوم والشهر، والاتجاهُ الشهري، **والكلفةُ على كلّ مؤسّسة**. وفيه
 *      التوليد والتفريغ معاً، ومنذ أوّل ملخّص.
 *   ٢. **`summary_jobs.cost_breakdown`** — الكلفة موزَّعةً على مرحلتها،
 *      وهي كذلك منذ أوّل ملخّص. منه التوزيعُ على المراحل.
 *   ٣. **`model_calls`** — المزوّد والنموذج والتوكنز، وهو **وحده** من يحملها.
 *      لكنّه وُلد مع T-22، فلا شيء فيه عمّا قبلها — والشاشة تقول ذلك صراحةً
 *      بدل أن تعرض فراغاً يُقرأ «لا كلفة».
 *
 * **وقاعدةُ الاختيار:** ما وُجد في الدفتر يُقرأ منه، ولا يُقرأ من جدولٍ
 * أحدثَ منه فيضيع تاريخُ ما قبله.
 */
class BuildCostOverview
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        $monthStart = now()->startOfMonth();
        $dayStart = now()->startOfDay();
        $cap = app(SpendCap::class);

        return [
            'today_cost_usd' => $cap->spentToday(),
            'month_cost_usd' => $cap->spentThisMonth(),
            'avg_cost_per_summary_usd' => $this->avgCostPerSummary($monthStart),
            'spend_cap' => [
                'daily_usd' => $cap->dailyCapUsd(),
                'monthly_usd' => $cap->monthlyCapUsd(),
                'halted' => $cap->isHalted(),
                'halt_reason' => $cap->haltDetails()['reason'] ?? null,
            ],
            'by_stage' => $this->byStage($monthStart),
            'by_model' => $this->byModel($monthStart),
            'by_tenant' => $this->byTenant($monthStart),
            'by_video_length' => $this->byVideoLength($monthStart),
            'monthly_trend' => $this->monthlyTrend(),
            'failure_rate_last_hour' => $this->failureRateLastHour(),
        ];
    }

    private function avgCostPerSummary(Carbon $since): float
    {
        $jobs = SummaryJob::query()
            ->where('total_cost_usd', '>', 0)
            ->where('started_at', '>=', $since)
            ->avg('total_cost_usd');

        return round((float) ($jobs ?? 0), 4);
    }

    /**
     * التوزيع على المراحل — من `summary_jobs.cost_breakdown`.
     *
     * ★ **وكان يقرأ `model_calls`** (T-22)، وذلك الجدول وُلد في تلك المهمّة
     * نفسِها — فكانت الشاشة تُسقط تاريخ المنصّة كلَّه وتعرض ما جرى بعدها
     * وحده. و`cost_breakdown` يحمل الكلفة موزَّعةً على مرحلتها منذ أوّل
     * ملخّص، فهو الأصل هنا — T-103.
     *
     * @return list<array{stage: string, cost_usd: float}>
     */
    private function byStage(Carbon $since): array
    {
        $totals = [];

        SummaryJob::query()
            ->whereNotNull('cost_breakdown')
            ->where('started_at', '>=', $since)
            ->get(['cost_breakdown'])
            ->each(function (SummaryJob $job) use (&$totals): void {
                foreach ((array) $job->cost_breakdown as $stage => $cost) {
                    $totals[(string) $stage] = ($totals[(string) $stage] ?? 0.0) + (float) $cost;
                }
            });

        // والتفريغ ليس مرحلةَ نموذج، فلا صفَّ له هناك — ويُضاف كي لا يغيب.
        $transcribing = $this->transcriptionCost($since);
        if ($transcribing > 0) {
            $totals['transcribing'] = ($totals['transcribing'] ?? 0.0) + $transcribing;
        }

        $out = [];
        foreach ($totals as $stage => $total) {
            $out[] = ['stage' => $stage, 'cost_usd' => round($total, 4)];
        }

        usort($out, static fn (array $a, array $b): int => $b['cost_usd'] <=> $a['cost_usd']);

        return $out;
    }

    /** @return list<array{provider: string, model_id: string, cost_usd: float}> */
    private function byModel(Carbon $since): array
    {
        $rows = ModelCall::query()
            ->where('occurred_at', '>=', $since)
            ->select('provider', 'model_id', DB::raw('SUM(cost_usd) as total'))
            ->groupBy('provider', 'model_id')
            ->orderByDesc('total')
            ->get();

        $out = $rows->map(static fn ($row): array => [
            'provider' => (string) $row->provider,
            'model_id' => (string) $row->model_id,
            'cost_usd' => round((float) $row->total, 4),
        ])->all();

        $transcribing = $this->transcriptionCost($since);
        if ($transcribing > 0) {
            $out[] = [
                'provider' => (string) config('khulasah.transcript.whisper.provider') ?: 'whisper',
                'model_id' => (string) config('khulasah.transcript.whisper.model'),
                'cost_usd' => $transcribing,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['cost_usd'] <=> $a['cost_usd']);

        return $out;
    }

    /**
     * الكلفة على المؤسّسات — من `usage_ledger` وحده، وهو مصدر الحقيقة (§4).
     *
     * ★ **وكان يجمع `model_calls` والتفريغَ معاً** (T-22) فيقول «‏$0.0000»
     * والدفترُ فيه ستّة دولارات: الجدول وُلد مع تلك المهمّة، فكلفةُ ما قبلها
     * كلُّها خارجه. والدفترُ يحمل التوليد والتفريغ جميعاً، فمصدرٌ واحدٌ
     * يكفي — T-103.
     *
     * @return list<array{tenant_id: int, name: string, plan: string, cost_usd: float, price_usd: float|null, margin_usd: float|null}>
     */
    private function byTenant(Carbon $since): array
    {
        $costs = UsageRecord::query()
            ->where('occurred_at', '>=', $since)
            ->select('tenant_id', DB::raw('SUM(cost_usd) as total'))
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        if ($costs->isEmpty()) {
            return [];
        }

        $tenants = Tenant::query()->whereIn('id', $costs->keys())->get(['id', 'name_ar', 'plan']);

        return $tenants->map(function (Tenant $tenant) use ($costs): array {
            $cost = round((float) ($costs[$tenant->id] ?? 0), 4);

            $price = Plan::find($tenant->plan)?->priceUsd;

            return [
                'tenant_id' => $tenant->id,
                'name' => $tenant->name_ar,
                'plan' => Plan::label($tenant->plan),
                'cost_usd' => $cost,
                'price_usd' => $price,
                'margin_usd' => $price === null ? null : round($price - $cost, 4),
            ];
        })->sortByDesc('cost_usd')->values()->all();
    }

    /**
     * التوزيع بطول الفيديو — حِزَمٌ من المواصفة §11 (٩٠ و١٨٠ الحدّان الشائعان).
     *
     * **كلفة النماذج وحدها هنا**، لا التفريغ: `summary_jobs.total_cost_usd`
     * لا يحمل كلفة الدقائق — انظر توثيق T-22 في `TASKS.md`.
     *
     * @return list<array{bucket: string, cost_usd: float, count: int}>
     */
    private function byVideoLength(Carbon $since): array
    {
        $rows = SummaryJob::query()
            ->join('lectures', 'lectures.id', '=', 'summary_jobs.lecture_id')
            ->where('summary_jobs.total_cost_usd', '>', 0)
            ->where('summary_jobs.started_at', '>=', $since)
            ->select(
                'summary_jobs.total_cost_usd',
                'lectures.duration_seconds',
            )
            ->get();

        $buckets = [
            '٠–٣٠ د' => ['max' => 30 * 60, 'cost' => 0.0, 'count' => 0],
            '٣٠–٦٠ د' => ['max' => 60 * 60, 'cost' => 0.0, 'count' => 0],
            '٦٠–٩٠ د' => ['max' => 90 * 60, 'cost' => 0.0, 'count' => 0],
            '٩٠–١٨٠ د' => ['max' => 180 * 60, 'cost' => 0.0, 'count' => 0],
            'أكثر من ١٨٠ د' => ['max' => PHP_INT_MAX, 'cost' => 0.0, 'count' => 0],
        ];

        foreach ($rows as $row) {
            $seconds = (int) ($row->duration_seconds ?? 0);

            foreach ($buckets as $label => &$bucket) {
                if ($seconds <= $bucket['max']) {
                    $bucket['cost'] += (float) $row->total_cost_usd;
                    $bucket['count']++;

                    break;
                }
            }
            unset($bucket);
        }

        $out = [];
        foreach ($buckets as $label => $bucket) {
            if ($bucket['count'] > 0) {
                $out[] = ['bucket' => $label, 'cost_usd' => round($bucket['cost'], 4), 'count' => $bucket['count']];
            }
        }

        return $out;
    }

    /** @return list<array{month: string, cost_usd: float}> */
    private function monthlyTrend(): array
    {
        $since = now()->subMonths(5)->startOfMonth();

        $rows = UsageRecord::query()
            ->where('occurred_at', '>=', $since)
            ->select(
                DB::raw("to_char(occurred_at, 'YYYY-MM') as ym"),
                DB::raw('SUM(cost_usd) as total'),
            )
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $key = $month->format('Y-m');

            $out[] = [
                'month' => $key,
                'cost_usd' => round((float) ($rows[$key] ?? 0), 4),
            ];
        }

        return $out;
    }

    private function transcriptionCost(Carbon $since): float
    {
        return round((float) UsageRecord::query()
            ->where('event', UsageEvent::Transcribe)
            ->where('occurred_at', '>=', $since)
            ->sum('cost_usd'), 4);
    }

    private function failureRateLastHour(): float
    {
        $since = now()->subHour();

        $total = SummaryJob::query()->where('started_at', '>=', $since)->count();

        if ($total === 0) {
            return 0.0;
        }

        $failed = SummaryJob::query()
            ->where('started_at', '>=', $since)
            ->where('state', JobState::Failed->value)
            ->count();

        return round($failed / $total, 4);
    }
}
