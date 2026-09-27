<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Enums\UsageEvent;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Quota\QuotaGuard;
use App\Support\Billing\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * الاشتراك والاستهلاك — SCREENS.md الشاشة 9، والمهمّة T-23.
 *
 * ★ **بالملخّصات لا بالتوكنز.** «ولا تُذكر التوكنز ولا النماذج في أيّ شاشة
 * يراها مدير المحتوى» — المبدأ الحاكم في صدر SCREENS.md ودراسة المشروع.
 * فالوحدة المحاسبية المعروضة هي الملخّص، ودقيقةُ التفريغ، وإعادةُ التوليد.
 *
 * **ولا فواتير ولا شراء ملخّص إضافي هنا** — لا بوّابة دفع في هذه المرحلة
 * (قرار مالك المنتج، 7 أيلول 2026). والشاشة تقول ذلك صراحةً وتدلّ على
 * المراجعة، ولا تعرض زرّاً لا يعمل.
 */
class BillingController extends Controller
{
    public function __construct(private readonly QuotaGuard $quota) {}

    public function show(Request $request): Response
    {
        $tenant = $this->tenant($request);

        $monthly = $this->quota->monthlyQuota($tenant);
        $daily = $this->quota->dailyCap($tenant);
        $transcription = $this->quota->transcriptionMinutes($tenant);

        $plan = Plan::find($tenant->plan);

        return Inertia::render('Settings/Billing', [
            'plan' => [
                'key' => $tenant->plan,
                'name' => Plan::label($tenant->plan),
                'rich_outputs' => $tenant->allowsRichOutputs(),
                /*
                 * **يُقال للجهة إن كانت حدودها ليست حدود شريحتها.**
                 * فمن رُفعت حصّتُه استثناءً يرى الرقم الحقيقي لا رقم
                 * الشريحة، ولا يُفاجأ عند التجديد.
                 */
                'customised' => $plan !== null && ! $plan->matches($tenant),
            ],

            // الحدود الخمسة كما هي في أعمدة الجهة — لا كما في قالب الشريحة.
            'limits' => [
                'monthly_quota' => (int) $tenant->monthly_quota,
                'daily_cap' => (int) $tenant->daily_cap,
                'max_lecture_minutes' => (int) $tenant->max_lecture_minutes,
                'transcription_minutes_quota' => (int) $tenant->transcription_minutes_quota,
                'regenerations_per_summary' => (int) $tenant->regenerations_per_summary,
            ],

            'usage' => [
                'summaries' => ['used' => $monthly->used, 'limit' => $monthly->allowance],
                'today' => ['used' => $daily->used, 'limit' => $daily->allowance],
                'transcription' => ['used' => $transcription->used, 'limit' => $transcription->allowance],
            ],

            'suspended' => $tenant->isSuspended(),

            'ledger' => $this->ledger(),
        ]);
    }

    /**
     * سجلّ الشهر — صفوف `usage_ledger` وحدها.
     *
     * **ولا يُعدّ من `summary_jobs`**: المهمّة الفاشلة صفٌّ لا يُحتسب،
     * وإعادة التوليد استهلاكٌ لا صفَّ له — المواصفة §4.
     *
     * @return list<array<string, mixed>>
     */
    private function ledger(): array
    {
        return UsageRecord::query()
            ->with('summaryJob.lecture')
            /*
             * **وصفوفُ الوحدةِ صفرٍ لا تُعرض.** هي قيدُ كلفةِ استدعاء نموذج
             * ({@see \App\Services\Model\ModelCallRecorder}): ستّةٌ منها
             * لكلّ ملخّص، لا تخصم من الحصّة ولا معنى لها عند مدير المحتوى.
             * وعرضُها يملأ السجلّ بستّة أسطر «٠ ملخّص» عن ملخّصٍ واحد،
             * **وهو بابُ التوكنز نفسه من حيث لا يُسمّى** — والشاشة
             * «بالملخّصات لا بالتوكنز».
             */
            ->where('units', '>', 0)
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (UsageRecord $record): array => [
                'id' => $record->id,
                'event' => $record->event->value,
                'units' => $record->units,
                // **والوحدة تختلف بالحدث**: ملخّص، أو دقيقة تفريغ.
                'unit' => $record->event === UsageEvent::Transcribe ? 'minutes' : 'summaries',
                'title' => $record->summaryJob?->lecture?->title_ar,
                'occurred_at' => $record->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        if ($tenant === null) {
            throw new RuntimeException('لا جهة لهذا المستخدم.');
        }

        return $tenant;
    }
}
