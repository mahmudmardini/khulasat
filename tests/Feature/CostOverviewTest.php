<?php

declare(strict_types=1);

use App\Actions\Admin\BuildCostOverview;
use App\Actions\Usage\RecordUsage as RecordUsageAction;
use App\Domain\Summary\JobState;
use App\Enums\Stage;
use App\Enums\UsageEvent;
use App\Models\Lecture;
use App\Models\ModelCall;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;

/*
 * شاشة الكلفة — T-22. رُفع تأجيلها بقرار مالك المنتج (11 أيلول 2026) بعد
 * أن صار `MODEL_GATEWAY=real` مُشغَّلاً فعلاً.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->superAdmin()->create();
    $this->tenant = Tenant::factory()->create(['name_ar' => 'جهة الاختبار']);

    ModelConfig::query()->create([
        'stage' => Stage::Cleaning->value,
        'provider' => 'anthropic',
        'model_id' => 'test-model',
        'max_tokens' => 1_000,
        'timeout_seconds' => 30,
        'on_exhausted' => 'degrade',
        'input_price_per_m' => 100,
        'output_price_per_m' => 200,
        'is_active' => true,
    ]);
});

function costJob(Tenant $tenant): SummaryJob
{
    return SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $tenant->id])->id,
        'state' => JobState::Published->value,
        'started_at' => now(),
    ]);
}

// ★ كانت كلفة التفريغ صفراً دائماً — T-22. الآن تدخل الإجمالي وتظهر بندَ مرحلةٍ مستقلّاً.
it('folds transcription cost into the totals and its own stage bucket', function (): void {
    app(RecordUsageAction::class)->handle(
        tenant: $this->tenant,
        event: UsageEvent::Transcribe,
        units: 30,
        costUsd: 0.18,
    );

    $overview = app(BuildCostOverview::class)->handle();

    $hasTranscribingStage = collect($overview['by_stage'])
        ->contains(fn (array $row): bool => $row['stage'] === 'transcribing' && $row['cost_usd'] === 0.18);

    expect($overview['today_cost_usd'])->toBe(0.18)
        ->and($hasTranscribingStage)->toBeTrue();
});

// 404 لا 403 — كما تفعل بقية شاشات لوحة المشرف: AdminGuardTest.
it('hides the cost screen from a tenant user', function (): void {
    $owner = User::factory()->for_($this->tenant)->owner()->create();

    $this->actingAs($owner)->get('/admin/costs')->assertNotFound();
});

/*
 * ─── T-103: تُقرأ الكلفة من دفترها لا من أحدثِ جدولٍ يحملها ──────────
 *
 * ثلاثُ بطاقاتٍ كانت تقول «لا كلفة» والكلفةُ مصروفةٌ مقيَّدة: قرأت
 * `model_calls` — وهو جدولٌ وُلد مع T-22 — بدل `usage_ledger`
 * و`cost_breakdown` اللذين يحملان تاريخ المنصّة كلَّه.
 */

it('reads the per-stage breakdown from cost_breakdown, not from the newer model_calls table', function (): void {
    SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'state' => JobState::Published->value,
        'started_at' => now(),
        'total_cost_usd' => 0.27,
        'cost_breakdown' => ['writing' => 0.15, 'cleaning' => 0.12],
    ]);

    $overview = app(BuildCostOverview::class)->handle();

    $byStage = collect($overview['by_stage'])->pluck('cost_usd', 'stage');

    // ولا صفَّ واحداً في الجدول الأحدث — وهي عينُ حال المهامّ السابقة له.
    expect(ModelCall::query()->count())->toBe(0)
        ->and($byStage['writing'])->toBe(0.15)
        ->and($byStage['cleaning'])->toBe(0.12);
});

it('reads tenant cost from the usage ledger, so what predates model_calls still shows', function (): void {
    app(RecordUsageAction::class)->handle(
        tenant: $this->tenant,
        event: UsageEvent::Generate,
        units: 0,
        costUsd: 6.081,
    );

    $overview = app(BuildCostOverview::class)->handle();

    $row = collect($overview['by_tenant'])->firstWhere('tenant_id', $this->tenant->id);

    expect(ModelCall::query()->count())->toBe(0)
        ->and($row['cost_usd'])->toBe(6.081);
});

/** وكلفةُ كلّ مرحلة تُرسَم في شاشة المهمّة — وكانت تُمرَّر خاصّيةً ولا تُقرأ. */
it('hands the admin job screen the cost of every stage', function (): void {
    $job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'state' => JobState::Published->value,
        'started_at' => now(),
        'total_cost_usd' => 0.16,
        'cost_breakdown' => ['writing' => 0.15, 'cleaning' => 0.01],
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get("/admin/jobs/{$job->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Jobs/Show')
            // الأغلى أوّلاً — فالمشرف يفتح الشاشة ليعرف أين ذهب المال.
            ->where('stage_costs.0.stage', 'writing')
            ->where('stage_costs.0.cost_usd', 0.15)
            ->where('stage_costs.1.stage', 'cleaning'));
});

/** وكلفةُ شهر الجهة في شاشتها — يراها من يرفع حدودها وهو يرفعها. */
it('shows a tenant its own month cost on the admin tenant screen', function (): void {
    app(RecordUsageAction::class)->handle(
        tenant: $this->tenant,
        event: UsageEvent::Generate,
        units: 0,
        costUsd: 1.25,
    );

    $this->actingAs($this->admin, 'admin')
        ->get("/admin/tenants/{$this->tenant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('usage.cost_usd', 1.25));
});
