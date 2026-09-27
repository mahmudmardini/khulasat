<?php

declare(strict_types=1);

use App\Enums\QuotaLimit;
use App\Enums\Stage;
use App\Enums\UsageEvent;
use App\Models\Lecture;
use App\Models\ModelConfig;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Services\Model\FakeModelGateway;
use App\Services\Quota\QuotaGuard;
use App\Services\Quota\SpendCap;
use Illuminate\Support\Facades\Log;

// المواصفة §11. والحدود الخمسة تُفحص **قبل** وضع المهمّة في الطابور:
// «الفحص بعد الوضع يعني أنّ التوكنز صُرفت».

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'monthly_quota' => 20,
        'daily_cap' => 3,
        'max_lecture_minutes' => 90,
        'transcription_minutes_quota' => 120,
        'regenerations_per_summary' => 2,
    ]);

    $this->guard = app(QuotaGuard::class);
    $this->cap = app(SpendCap::class);
    $this->cap->release();
});

/** يقيّد كلفةً كما يفعل ModelCallRecorder: صفٌّ في السجلّ ووحدتُه صفر. */
function bookCost(Tenant $tenant, float $usd, ?string $at = null): void
{
    UsageRecord::query()->create([
        'tenant_id' => $tenant->id,
        'event' => UsageEvent::Generate,
        'units' => 0,
        'cost_usd' => $usd,
        'occurred_at' => $at ? now()->parse($at) : now(),
    ]);
}

function spend(Tenant $tenant, UsageEvent $event, int $units, ?string $at = null): void
{
    UsageRecord::query()->create([
        'tenant_id' => $tenant->id,
        'event' => $event,
        'units' => $units,
        'cost_usd' => 0,
        'occurred_at' => $at ? now()->parse($at) : now(),
    ]);
}

// ── الحصّة الشهرية ───────────────────────────────────────────────

it('allows a summary while the monthly quota holds', function (): void {
    spend($this->tenant, UsageEvent::Generate, 5);

    $decision = $this->guard->monthlyQuota($this->tenant);

    expect($decision->permitted())->toBeTrue()
        ->and($decision->remaining())->toBe(15);
});

it('refuses once the monthly quota is spent', function (): void {
    spend($this->tenant, UsageEvent::Generate, 20);

    $decision = $this->guard->monthlyQuota($this->tenant);

    expect($decision->denied)->toBeTrue()
        ->and($decision->limit)->toBe(QuotaLimit::MonthlyQuota)
        // ورسالة تشرح الإجراء لا تكتفي بالرفض.
        ->and($decision->message())->toContain('شراء ملخّص إضافي');
});

// §11: إعادة التوليد «تُحتسب من الحصّة الشهرية».
it('counts regenerations against the monthly quota', function (): void {
    spend($this->tenant, UsageEvent::Generate, 18);
    spend($this->tenant, UsageEvent::Regenerate, 2);

    expect($this->guard->monthlyQuota($this->tenant)->denied)->toBeTrue();
});

// ودقائق التفريغ حصّةٌ مستقلّة، فلا تُخصم من حصّة الملخّصات.
it('keeps transcription minutes out of the summary quota', function (): void {
    spend($this->tenant, UsageEvent::Transcribe, 500);

    expect($this->guard->monthlyQuota($this->tenant)->permitted())->toBeTrue();
});

it('ignores what was spent last month', function (): void {
    spend($this->tenant, UsageEvent::Generate, 20, at: now()->subMonth()->toDateTimeString());

    expect($this->guard->monthlyQuota($this->tenant)->permitted())->toBeTrue();
});

// ── السقف اليومي ─────────────────────────────────────────────────

it('refuses past the daily cap', function (): void {
    spend($this->tenant, UsageEvent::Generate, 3);

    expect($this->guard->dailyCap($this->tenant)->denied)->toBeTrue()
        ->and($this->guard->dailyCap($this->tenant)->message())->toContain('غداً');
});

it('starts the daily cap fresh each day', function (): void {
    spend($this->tenant, UsageEvent::Generate, 3, at: now()->subDay()->toDateTimeString());

    expect($this->guard->dailyCap($this->tenant)->permitted())->toBeTrue();
});

// ── طول المحاضرة — يُرفض قبل صرف أيّ توكن ───────────────────────

it('refuses a lecture longer than the plan allows', function (): void {
    $lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'duration_seconds' => 7_200,
    ]);

    $decision = $this->guard->lectureDuration($lecture);

    expect($decision->denied)->toBeTrue()
        ->and($decision->limit)->toBe(QuotaLimit::LectureDuration)
        ->and($decision->message())->toContain('اقسموا الدرس');
});

it('accepts a lecture inside the limit', function (): void {
    $lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'duration_seconds' => 5_400,
    ]);

    expect($this->guard->lectureDuration($lecture)->permitted())->toBeTrue();
});

// مدّة مجهولة لا تُرفض: الرفض يحتاج دليلاً.
it('does not refuse a lecture whose duration is unknown', function (): void {
    $lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'duration_seconds' => null,
    ]);

    expect($this->guard->lectureDuration($lecture)->permitted())->toBeTrue();
});

// ── إعادة التوليد ────────────────────────────────────────────────

// T-13: «مرّتان ثم تُحتسب» — والثالثة تُرفض على هذا الملخّص.
it('allows two regenerations and refuses the third', function (): void {
    $job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id, 'regeneration_count' => 0]);

    expect($this->guard->regeneration($job)->permitted())->toBeTrue();

    $job->forceFill(['regeneration_count' => 1])->save();
    expect($this->guard->regeneration($job)->permitted())->toBeTrue();

    $job->forceFill(['regeneration_count' => 2])->save();

    $decision = $this->guard->regeneration($job);

    expect($decision->denied)->toBeTrue()
        ->and($decision->limit)->toBe(QuotaLimit::Regeneration);
});

// ── دقائق التفريغ — واليدوي يبقى مقبولاً ────────────────────────

it('refuses transcription once the minutes are gone', function (): void {
    spend($this->tenant, UsageEvent::Transcribe, 120);

    $decision = $this->guard->transcriptionMinutes($this->tenant);

    expect($decision->denied)->toBeTrue()
        ->and($decision->limit)->toBe(QuotaLimit::TranscriptionMinutes);
});

// **§11 صريحة:** «رفض التفريغ، **وقبول تفريغ يدوي**». فالجهة لا تُمنع من
// العمل، وإنّما من المسار الذي يكلّف — والرسالة تدلّها عليه.
it('still points the tenant at the manual path', function (): void {
    expect(QuotaLimit::TranscriptionMinutes->allowsManualPath())->toBeTrue()
        ->and(QuotaLimit::TranscriptionMinutes->message())->toContain('لصق نصّ المحاضرة')
        // وبقيّة الحدود لا تُنقَذ باليدوي.
        ->and(QuotaLimit::MonthlyQuota->allowsManualPath())->toBeFalse()
        ->and(QuotaLimit::DailyCap->allowsManualPath())->toBeFalse();
});

it('refuses a request that would overshoot the remaining minutes', function (): void {
    spend($this->tenant, UsageEvent::Transcribe, 100);

    // بقيت ٢٠ دقيقة، والدرس ٤٥ — فيُرفض قبل أن يبدأ لا بعد أن يستهلك.
    expect($this->guard->transcriptionMinutes($this->tenant, requestedMinutes: 45)->denied)->toBeTrue()
        ->and($this->guard->transcriptionMinutes($this->tenant, requestedMinutes: 15)->permitted())->toBeTrue();
});

// ── سقف الإنفاق — يوقف الطابور كلّه ─────────────────────────────

it('reports what has been spent today and this month', function (): void {
    bookCost($this->tenant, 12.5);
    bookCost($this->tenant, 7.5);

    expect($this->cap->spentToday())->toBe(20.0)
        ->and($this->cap->spentThisMonth())->toBe(20.0);
});

// «خطأ في حلقة برمجية قادر على إحراق ألف دولار في ليلة، وهذا السقف هو ما
// يمنعه» — T-13.
it('halts the whole queue when the daily cap is breached', function (): void {
    Log::spy();

    config()->set('khulasah.spend.daily_usd', 10);
    bookCost($this->tenant, 11);

    $this->artisan('khulasah:enforce-spend-cap')->assertFailed();

    expect($this->cap->isHalted())->toBeTrue();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message): bool => str_contains($message, 'أُوقف الطابور'));
});

it('halts on the monthly cap too', function (): void {
    config()->set('khulasah.spend.daily_usd', 10_000);
    config()->set('khulasah.spend.monthly_usd', 10);

    bookCost($this->tenant, 11);

    $this->artisan('khulasah:enforce-spend-cap')->assertFailed();

    expect($this->cap->isHalted())->toBeTrue();
});

it('stays quiet while spending is within the caps', function (): void {
    config()->set('khulasah.spend.daily_usd', 100);
    bookCost($this->tenant, 5);

    $this->artisan('khulasah:enforce-spend-cap')->assertSuccessful();

    expect($this->cap->isHalted())->toBeFalse();
});

// **الوقف لا يرفع نفسه.** ولو رُفع آلياً حين ينزل المجموع — وهو لا ينزل
// داخل اليوم — لعاد الإحراق. الرفع بيد إنسان نظر في السبب.
it('does not lift the halt on its own', function (): void {
    config()->set('khulasah.spend.daily_usd', 10);
    bookCost($this->tenant, 11);

    $this->artisan('khulasah:enforce-spend-cap')->assertFailed();

    config()->set('khulasah.spend.daily_usd', 10_000);
    $this->artisan('khulasah:enforce-spend-cap')->assertSuccessful();

    expect($this->cap->isHalted())->toBeTrue();

    $this->artisan('khulasah:enforce-spend-cap', ['--release' => true])->assertSuccessful();

    expect($this->cap->isHalted())->toBeFalse();
});

// والوقف عامٌّ لا لجهة: تجاوزُ السقف عطلٌ عندنا لا إسرافٌ من جهة.
it('stops every tenant, not the one that spent', function (): void {
    $other = Tenant::factory()->create(['monthly_quota' => 100, 'daily_cap' => 100]);

    $this->cap->halt('اختبار');

    expect($this->guard->forNewSummary($this->tenant)->limit)->toBe(QuotaLimit::SpendCap)
        ->and($this->guard->forNewSummary($other)->limit)->toBe(QuotaLimit::SpendCap)
        // ورسالةٌ تعتذر ولا تُلقي اللوم على الجهة.
        ->and(QuotaLimit::SpendCap->message())->toContain('مراجعة تشغيلية عندنا');
});

// ── الحاجز يمنع قبل الصرف لا بعده ───────────────────────────────

// **معيار قبول T-13:** «تجاوز الحصة يُرفض بلا صرف توكن».
it('refuses over quota without spending a single token', function (): void {
    spend($this->tenant, UsageEvent::Generate, 20);

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

    $gateway = app(FakeModelGateway::class);

    $decision = $this->guard->forNewSummary($this->tenant);

    // القرار رفضٌ، ولم يُنادَ نموذجٌ أصلاً — فلا توكن صُرف ولا كلفة قُيّدت.
    expect($decision->denied)->toBeTrue()
        ->and($gateway->calls)->toBeEmpty()
        ->and((float) SummaryJob::query()->sum('total_cost_usd'))->toBe(0.0);
});

// الترتيب: الأعمّ أوّلاً. سقفُ الإنفاق يوقف الجميع، فيُقرَأ قبل حدود الجهة.
it('reports the spend cap before any tenant limit', function (): void {
    spend($this->tenant, UsageEvent::Generate, 20);
    $this->cap->halt('اختبار');

    expect($this->guard->forNewSummary($this->tenant)->limit)->toBe(QuotaLimit::SpendCap);
});

// ولا رمز حدٍّ يظهر في رسالته — كما في أكواد التفريغ.
it('shows no limit code inside any message', function (QuotaLimit $limit): void {
    expect($limit->message())
        ->not->toContain($limit->value)
        ->and(preg_match('/\p{Arabic}/u', $limit->message()))->toBe(1);
})->with(QuotaLimit::cases());
