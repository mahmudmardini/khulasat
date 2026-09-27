<?php

declare(strict_types=1);

use App\Actions\Summary\RequestRegeneration;
use App\Actions\Summary\TransitionJob;
use App\Actions\Usage\RecordUsage;
use App\Domain\Summary\JobState;
use App\Domain\Summary\RegenerationRefused;
use App\Enums\UsageEvent;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Support\TenantContext;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['regenerations_per_summary' => 2]);
    $this->owner = User::factory()->for_($this->tenant)->owner()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);
});

// ── الدفتر مصدر الحقيقة — المواصفة §4 و§11 ───────────────────────

it('does not count usage from summary_jobs rows', function (): void {
    // ثلاث مهامّ ولا سطر في الدفتر: عدُّ الصفوف يعطي ٣ والحقيقة ٠.
    SummaryJob::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect(UsageRecord::query()->sum('units'))->toBe(0);
});

it('does not count a failed job against the quota', function (): void {
    app(TransitionJob::class)->handle($this->job, JobState::Transcribing);
    app(TransitionJob::class)->handle($this->job, JobState::Failed, errorCode: 'transcript_failed');

    // المهمّة صفٌّ في summary_jobs، ولا شيء في الدفتر. ومن عدّ الصفوف حاسب الجهة على فشلنا.
    expect(UsageRecord::query()->count())->toBe(0);
});

it('writes one ledger row per recorded event', function (): void {
    app(RecordUsage::class)->handle($this->tenant, UsageEvent::Generate, job: $this->job);
    app(RecordUsage::class)->handle($this->tenant, UsageEvent::Transcribe, units: 45, costUsd: 0.27);

    $rows = UsageRecord::query()->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->event)->toBe(UsageEvent::Generate)
        ->and($rows[0]->summary_job_id)->toBe($this->job->id)
        ->and($rows[1]->units)->toBe(45)
        ->and((float) $rows[1]->cost_usd)->toBe(0.27)
        ->and($rows[1]->occurred_at)->not->toBeNull();
});

it('separates transcription minutes from the summary quota', function (): void {
    expect(UsageEvent::Generate->countsAgainstMonthlyQuota())->toBeTrue()
        ->and(UsageEvent::Regenerate->countsAgainstMonthlyQuota())->toBeTrue()
        ->and(UsageEvent::Transcribe->countsAgainstMonthlyQuota())->toBeFalse();
});

it('confines the ledger to its tenant', function (): void {
    $other = Tenant::factory()->create();
    app(RecordUsage::class)->handle($other, UsageEvent::Generate);
    app(RecordUsage::class)->handle($this->tenant, UsageEvent::Generate);

    // الحاجز يقرأ TenantContext لا المصادقة — يملؤه ResolveTenant في الطلب.
    app(TenantContext::class)->set((int) $this->tenant->id);

    expect(UsageRecord::query()->count())->toBe(1)
        ->and(UsageRecord::acrossTenants()->count())->toBe(2);
});

// ── إعادة التوليد قرار بشري — المواصفة §5 و§11 ───────────────────

it('charges a regeneration to the ledger and the counter', function (): void {
    app(RequestRegeneration::class)->handle($this->job, $this->owner);

    expect($this->job->fresh()->regeneration_count)->toBe(1)
        ->and(UsageRecord::query()->where('event', UsageEvent::Regenerate->value)->count())->toBe(1);
});

it('stops regenerating past the tenant limit', function (): void {
    $action = app(RequestRegeneration::class);
    $action->handle($this->job, $this->owner);
    $action->handle($this->job, $this->owner);

    expect(fn (): SummaryJob => $action->handle($this->job, $this->owner))
        ->toThrow(RegenerationRefused::class);

    expect($this->job->fresh()->regeneration_count)->toBe(2);
});

it('refuses a regeneration asked for by a viewer', function (): void {
    $viewer = User::factory()->for_($this->tenant)->viewer()->create();

    expect(fn (): SummaryJob => app(RequestRegeneration::class)->handle($this->job, $viewer))
        ->toThrow(RegenerationRefused::class);
});

it('refuses a regeneration asked for from another tenant', function (): void {
    $stranger = User::factory()->for_(Tenant::factory()->create())->owner()->create();

    expect(fn (): SummaryJob => app(RequestRegeneration::class)->handle($this->job, $stranger))
        ->toThrow(RegenerationRefused::class);
});
