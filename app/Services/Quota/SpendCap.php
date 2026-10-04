<?php

declare(strict_types=1);

namespace App\Services\Quota;

use App\Models\ModelCall;
use App\Models\Scopes\TenantScope;
use App\Models\UsageRecord;
use App\Services\Model\ModelCallRecorder;
use Illuminate\Support\Facades\Cache;

/**
 * The platform-wide spend cap — المواصفة §11.
 *
 * «مهمّة مجدولة كلّ ساعة تجمع `total_cost_usd` لليوم والشهر. عند تجاوز عتبة
 * الإعداد **يُوقَف الطابور كلّه** وتُرسل تنبيهاً.»
 *
 * ولماذا قبل كلّ شيء: «**خطأ في حلقة برمجية قادر على إحراق ألف دولار في
 * ليلة، وهذا السقف هو ما يمنعه**» (T-13). فهو ليس تحسيناً بل شرط تشغيل.
 *
 * والوقف عامٌّ لا لجهة: تجاوزُ السقف عطلٌ عندنا لا إسرافٌ من جهة، فلا
 * يُعاقَب به مستأجرٌ بعينه.
 */
class SpendCap
{
    private const HALT_KEY = 'khulasah:spend-cap:halted';

    /**
     * المجموع لليوم الجاري بالدولار.
     *
     * **من `usage_ledger` لا من `summary_jobs`**، لسببين:
     *   ١. `summary_jobs` بلا عمود `created_at` أصلاً (§4)، فلا زمنَ يُنسب
     *      إليه الإنفاق إلا `started_at` — وهو زمنُ البدء لا زمنُ الصرف.
     *   ٢. المهمّة تبدأ ليلاً وتصرف صباحاً، فنسبةُ إنفاقها كلِّه إلى يوم
     *      بدئها تُخفي تجاوزَ اليوم الذي وقع فيه فعلاً.
     *
     * والمجموع نفسه: كلُّ ما يُقيَّد في `total_cost_usd` يُقيَّد صفّاً في
     * السجلّ بالكلفة نفسها — {@see ModelCallRecorder}.
     * وهذا الجدول «مصدر الحقيقة للحصص والفوترة» (§4).
     */
    public function spentToday(): float
    {
        return $this->spentSince(now()->startOfDay());
    }

    /** المجموع للشهر الجاري بالدولار. */
    public function spentThisMonth(): float
    {
        return $this->spentSince(now()->startOfMonth());
    }

    private function spentSince(\DateTimeInterface $since): float
    {
        $tenants = (float) UsageRecord::query()
            ->where('occurred_at', '>=', $since)
            ->sum('cost_usd');

        /*
         * ★ **ونداءاتُ أداة التحقّق معه** — T-181. لا جهة لها فلا صفّ لها في
         * الدفتر، وصفوفُها في `model_calls` بلا جهة. ولو غابت عن هذا المجموع
         * لصرفت أداةٌ عامّة بلا سقف.
         */
        $tool = (float) ModelCall::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNull('tenant_id')
            ->where('occurred_at', '>=', $since)
            ->sum('cost_usd');

        return round($tenants + $tool, 4);
    }

    public function dailyCapUsd(): float
    {
        return (float) config('khulasah.spend.daily_usd');
    }

    public function monthlyCapUsd(): float
    {
        return (float) config('khulasah.spend.monthly_usd');
    }

    /** السببُ إن وجب الوقف، أو `null`. */
    public function breachedReason(): ?string
    {
        $daily = $this->dailyCapUsd();
        $monthly = $this->monthlyCapUsd();

        if ($daily > 0 && $this->spentToday() >= $daily) {
            return sprintf('تجاوز الإنفاق اليومي: %s$ من %s$.', $this->spentToday(), $daily);
        }

        if ($monthly > 0 && $this->spentThisMonth() >= $monthly) {
            return sprintf('تجاوز الإنفاق الشهري: %s$ من %s$.', $this->spentThisMonth(), $monthly);
        }

        return null;
    }

    /**
     * يوقف الطابور.
     *
     * **بلا مهلة انتهاء**: الوقف يُرفع بيد إنسانٍ نظر في السبب، لا بمرور
     * الوقت. ووقفٌ ينتهي من نفسه بعد ساعة يُعيد الإحراق بعد ساعة.
     */
    public function halt(string $reason): void
    {
        Cache::forever(self::HALT_KEY, [
            'reason' => $reason,
            'halted_at' => now()->toIso8601String(),
        ]);
    }

    public function isHalted(): bool
    {
        return Cache::has(self::HALT_KEY);
    }

    /** @return array{reason: string, halted_at: string}|null */
    public function haltDetails(): ?array
    {
        return Cache::get(self::HALT_KEY);
    }

    /** يُرفع بيد إنسان بعد النظر في السبب. */
    public function release(): void
    {
        Cache::forget(self::HALT_KEY);
    }
}
