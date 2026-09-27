<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Enums\UsageEvent;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Notifications\CostAlertTriggered;
use App\Services\Quota\SpendCap;
use Illuminate\Support\Facades\Notification;

/*
 * تنبيهات الكلفة — T-22. عند ٨٠٪ من السقف لا ١٠٠٪، لأنّ بلوغ السقف نفسه
 * يعني توقّف الخدمة بالفعل — {@see \App\Console\Commands\EnforceSpendCap}.
 */

beforeEach(function (): void {
    config()->set('khulasah.alerts.mail_to', 'ops@khulasah.app');
    $this->tenant = Tenant::factory()->create();
});

it('warns by mail once the daily spend crosses 80%, before the cap halts anything', function (): void {
    Notification::fake();
    config()->set('khulasah.spend.daily_usd', 10);
    config()->set('khulasah.spend.monthly_usd', 0);

    app(SpendCap::class); // يضمن الحلّ قبل التلاعب بالإعداد أعلاه.

    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Generate,
        'units' => 0,
        'cost_usd' => 8.5, // ٨٥٪ من ١٠
        'occurred_at' => now(),
    ]);

    $this->artisan('khulasah:check-cost-alerts')->assertSuccessful();

    Notification::assertSentOnDemand(CostAlertTriggered::class);
    expect(app(SpendCap::class)->isHalted())->toBeFalse();
});

it('does not warn twice in the same hour for the same threshold', function (): void {
    Notification::fake();
    config()->set('khulasah.spend.daily_usd', 10);
    config()->set('khulasah.spend.monthly_usd', 0);

    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Generate,
        'units' => 0,
        'cost_usd' => 9,
        'occurred_at' => now(),
    ]);

    $this->artisan('khulasah:check-cost-alerts');
    $this->artisan('khulasah:check-cost-alerts');

    Notification::assertSentOnDemandTimes(CostAlertTriggered::class, 1);
});

it('warns when the failure rate in the last hour crosses the threshold', function (): void {
    Notification::fake();

    for ($i = 0; $i < 8; $i++) {
        SummaryJob::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'state' => $i < 2 ? JobState::Failed->value : JobState::Published->value,
            'started_at' => now(),
        ]);
    }

    $this->artisan('khulasah:check-cost-alerts');

    Notification::assertSentOnDemand(CostAlertTriggered::class);
});

it('sends nothing when there is no address configured', function (): void {
    Notification::fake();
    config()->set('khulasah.alerts.mail_to', null);
    config()->set('khulasah.spend.daily_usd', 10);

    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Generate,
        'units' => 0,
        'cost_usd' => 9,
        'occurred_at' => now(),
    ]);

    $this->artisan('khulasah:check-cost-alerts')->assertSuccessful();

    Notification::assertNothingSent();
});
