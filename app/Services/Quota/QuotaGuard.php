<?php

declare(strict_types=1);

namespace App\Services\Quota;

use App\Enums\QuotaLimit;
use App\Enums\UsageEvent;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Support\Quota\QuotaDecision;

/**
 * The five limits of §11, computed before anything is spent — المواصفة §11.
 *
 * **الفحص قبل وضع المهمّة في الطابور لا بعده.** «الفحص بعد الوضع يعني أنّ
 * التوكنز صُرفت — وهذا الخطأ يكلّف مالاً حقيقياً» (T-13).
 *
 * والحساب من `usage_ledger` وحده: «هذا الجدول هو مصدر الحقيقة للحصص
 * والفوترة. **لا تُحسب الحصص من عدّ الصفوف في `summary_jobs`**» (§4). ومن
 * عدّها منه أخطأ في الاتجاهين: المهمّة الفاشلة صفٌّ لا يُحتسب، وإعادة
 * التوليد استهلاكٌ لا صفَّ له.
 *
 * @see khulasah-build-spec.md §11
 */
class QuotaGuard
{
    public function __construct(private readonly SpendCap $spendCap) {}

    /**
     * القرار الجامع قبل إنشاء ملخّص جديد.
     *
     * ويُفحص **بالترتيب**: الأعمّ أوّلاً (سقف الإنفاق يوقف الجميع)، ثم
     * حدود الجهة، ثم حدّ هذه المحاضرة بعينها.
     */
    public function forNewSummary(Tenant $tenant, ?Lecture $lecture = null): QuotaDecision
    {
        if ($this->spendCap->isHalted()) {
            return QuotaDecision::denied(QuotaLimit::SpendCap);
        }

        /*
         * **الاشتراك الموقوف يمنع الإنتاج ولا يمسّ المنشور** — T-23.
         *
         * وموضعُه هنا لا في `EnforceQuota` وحده: الفعل يُنادى من الطابور
         * ومن الأوامر أيضاً، **والحارس في الواجهة يُلتفّ عليه** — وهي
         * العلّة نفسها التي وُضع لها حارسُ النشر في `PublishSummary`.
         *
         * ولا يمسّ `PublishSummary` ولا `UnpublishSummary`: صفحاتُ الجهة
         * الموقوفة ملفّاتٌ على التخزين تُخدَم من CDN بلا مرورٍ بنا أصلاً،
         * وإسقاطُها لخلافٍ ماليّ يضرّ بمن لا ذنب له.
         */
        if ($tenant->isSuspended()) {
            return QuotaDecision::denied(QuotaLimit::Suspended);
        }

        $monthly = $this->monthlyQuota($tenant);

        if ($monthly->denied) {
            return $monthly;
        }

        $daily = $this->dailyCap($tenant);

        if ($daily->denied) {
            return $daily;
        }

        return $lecture === null
            ? QuotaDecision::allowed()
            : $this->lectureDuration($lecture);
    }

    /** الحصّة الشهرية — `generate` و`regenerate` معاً، للشهر الجاري. */
    public function monthlyQuota(Tenant $tenant): QuotaDecision
    {
        $limit = (int) $tenant->monthly_quota;

        if ($limit <= 0) {
            return QuotaDecision::allowed();
        }

        $used = (int) UsageRecord::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('event', [UsageEvent::Generate->value, UsageEvent::Regenerate->value])
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->sum('units');

        return $used >= $limit
            ? QuotaDecision::denied(QuotaLimit::MonthlyQuota, $used, $limit)
            : QuotaDecision::allowed($used, $limit);
    }

    /** السقف اليومي — ثلاث مهامّ افتراضاً (§11). */
    public function dailyCap(Tenant $tenant): QuotaDecision
    {
        $limit = (int) $tenant->daily_cap;

        if ($limit <= 0) {
            return QuotaDecision::allowed();
        }

        $used = (int) UsageRecord::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('event', [UsageEvent::Generate->value, UsageEvent::Regenerate->value])
            ->where('occurred_at', '>=', now()->startOfDay())
            ->sum('units');

        return $used >= $limit
            ? QuotaDecision::denied(QuotaLimit::DailyCap, $used, $limit)
            : QuotaDecision::allowed($used, $limit);
    }

    /**
     * طول المحاضرة — «يُرفض قبل صرف أيّ توكن».
     *
     * والمدّة تأتي من الفحص المسبق في T-08، **قبل تنزيل بايت واحد**.
     */
    public function lectureDuration(Lecture $lecture): QuotaDecision
    {
        $limitMinutes = (int) $lecture->tenant->max_lecture_minutes;

        if ($limitMinutes <= 0 || $lecture->duration_seconds === null) {
            return QuotaDecision::allowed();
        }

        $minutes = (int) ceil($lecture->duration_seconds / 60);

        return $minutes > $limitMinutes
            ? QuotaDecision::denied(QuotaLimit::LectureDuration, $minutes, $limitMinutes)
            : QuotaDecision::allowed($minutes, $limitMinutes);
    }

    /** إعادة التوليد لكل ملخّص — والحدّ من الجهة لا من الكود. */
    public function regeneration(SummaryJob $job): QuotaDecision
    {
        $limit = (int) $job->tenant->regenerations_per_summary;
        $used = (int) $job->regeneration_count;

        return $used >= $limit
            ? QuotaDecision::denied(QuotaLimit::Regeneration, $used, $limit)
            : QuotaDecision::allowed($used, $limit);
    }

    /**
     * دقائق التفريغ — ويبقى المسار اليدوي مقبولاً عند نفادها (§11).
     *
     * @param  int  $requestedMinutes  دقائق هذا الدرس، إن عُرفت.
     */
    public function transcriptionMinutes(Tenant $tenant, int $requestedMinutes = 0): QuotaDecision
    {
        $limit = (int) $tenant->transcription_minutes_quota;

        if ($limit <= 0) {
            return QuotaDecision::allowed();
        }

        $used = (int) UsageRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('event', UsageEvent::Transcribe->value)
            ->where('occurred_at', '>=', now()->startOfMonth())
            ->sum('units');

        // شرطان لا واحد:
        //   ١. لم يبقَ شيء أصلاً (`>=`) — كبقيّة الحدود.
        //   ٢. أو هذا الدرس بعينه يتجاوز ما بقي.
        //
        // والاكتفاء بالثاني يُمرّر طلباً مجهولَ المدّة على حصّةٍ نفدت،
        // فيُصرف ما لا رصيد له.
        $exhausted = $used >= $limit;
        $overshoots = $used + $requestedMinutes > $limit;

        return $exhausted || $overshoots
            ? QuotaDecision::denied(QuotaLimit::TranscriptionMinutes, $used, $limit)
            : QuotaDecision::allowed($used, $limit);
    }
}
