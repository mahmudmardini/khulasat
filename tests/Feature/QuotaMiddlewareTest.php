<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Enums\UsageEvent;
use App\Http\Middleware\EnforceQuota;
use App\Jobs\RunSummaryPipeline;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Quota\SpendCap;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

/*
 * وصلُ `EnforceQuota` بالمسارات — T-29، والمواصفة §11.
 *
 * **وكان الحاجز مبنيّاً مختبَراً غيرَ مسجَّل**: لا في `bootstrap/app.php` ولا
 * على مسار. فهو يُرى في الشيفرة ولا يحرس شيئاً — **وحاجزٌ موجودٌ لا يعمل
 * أخطر من غيابه**، لأنّ من رآه ظنّ الطريق محروساً.
 */

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = Tenant::factory()->create([
        'plan' => 'starter',
        'monthly_quota' => 6,
        'daily_cap' => 3,
        'regenerations_per_summary' => 2,
    ]);

    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

function newLecturePayload(): array
{
    return [
        'source_kind' => 'text',
        'transcript_text' => str_repeat('كلمة ', 600),
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
        'venue_mode' => 'institution',
    ];
}

function jobFor_(Tenant $tenant, JobState $state = JobState::Failed): SummaryJob
{
    return SummaryJob::factory()->inState($state)->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $tenant->id])->id,
    ]);
}

/*
 * ─── الحارس الثابت: لا مسارَ يضع في الطابور بلا حاجز ─────────────────
 */

/**
 * ★ **الاختبار الحاكم في هذا الملفّ** — وهو معيار القبول الثالث.
 *
 * ولا يعدّ المسارات عدّاً مكتوباً بيدنا، بل **يقرأ الشيفرة نفسها**: كلّ
 * فعلِ متحكّمٍ يستدعي `RunSummaryPipeline::dispatch` يجب أن يكون مساره
 * محروساً. فمن أضاف مساراً جديداً يضع في الطابور ونسي الحاجز **أسقط هذا
 * الاختبار**، ولا يُنتظر أن ينتبه مراجع.
 *
 * وهذا هو الفرق بين قاعدةٍ مكتوبة وقاعدةٍ مفروضة.
 */
it('يحرس كل مسارٍ يضع مهمّةً في الطابور', function (): void {
    $unguarded = [];
    $queueing = 0;

    foreach (Route::getRoutes() as $route) {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            continue;
        }

        [$class, $method] = explode('@', $action, 2);

        if (! str_starts_with($class, 'App\\Http\\Controllers\\') || ! method_exists($class, $method)) {
            continue;
        }

        if (! dispatchesThePipeline($class, $method)) {
            continue;
        }

        $queueing++;

        $guarded = collect($route->gatherMiddleware())
            ->contains(fn (mixed $entry): bool => is_string($entry)
                && (str_starts_with($entry, 'quota') || str_starts_with($entry, EnforceQuota::class)));

        if (! $guarded) {
            $unguarded[] = $route->methods()[0].' /'.$route->uri();
        }
    }

    // ولو لم يُعثر على شيء لمرّ الاختبار بلا أن يقيس — فيُقاس أنّه قاس.
    expect($queueing)->toBeGreaterThanOrEqual(4)
        ->and($unguarded)->toBe([], 'مسارٌ يضع في الطابور بلا حاجز حصّة: '.implode(' · ', $unguarded));
});

/** أيستدعي هذا الفعلُ الطابورَ؟ يُقرأ من مصدر الدالّة نفسها. */
function dispatchesThePipeline(string $class, string $method): bool
{
    $reflection = new ReflectionMethod($class, $method);
    $file = $reflection->getFileName();

    if ($file === false) {
        return false;
    }

    $lines = array_slice(
        file($file) ?: [],
        $reflection->getStartLine() - 1,
        $reflection->getEndLine() - $reflection->getStartLine() + 1,
    );

    return str_contains(implode('', $lines), 'RunSummaryPipeline::dispatch');
}

/*
 * ─── إنشاء ملخّص: الحدود كلّها ───────────────────────────────────────
 */

it('يمنع إنشاء ملخّص وقد نفدت الحصّة الشهرية، قبل الطابور', function (): void {
    fillQuota($this->tenant, 6);

    $this->actingAs($this->user)->post('/panel/lectures', newLecturePayload())
        ->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();

    expect(SummaryJob::query()->count())->toBe(0);
});

/*
 * ★ T-221: النصُّ الملصوق دون ٥٠٠ كلمة كان يُقبل ويُحتسب من الحصّة، ثمّ يقف
 * الخطّ برسالةٍ تقول إنّ الحصّة لم تُمسّ. فيُردّ في النموذج، بعدده وحدّه.
 */
it('يردّ النصّ الملصوق القصير في النموذج قبل أن يُحتسب من الحصّة', function (): void {
    $this->actingAs($this->user)->post('/panel/lectures', [
        ...newLecturePayload(),
        'transcript_text' => str_repeat('كلمة ', 258),
    ])->assertSessionHasErrors([
        'transcript_text' => 'النصّ ٢٥٨ كلمة، وأقلّ ما يُلخَّص ٥٠٠ كلمة. الصقوا نصّ الدرس كاملاً.',
    ]);

    Queue::assertNothingPushed();

    expect(SummaryJob::query()->count())->toBe(0)
        ->and(UsageRecord::query()->count())->toBe(0);
});

it('يقبل النصّ الملصوق الذي يبلغ الحدّ', function (): void {
    $this->actingAs($this->user)->post('/panel/lectures', [
        ...newLecturePayload(),
        'transcript_text' => str_repeat('كلمة ', 500),
    ])->assertSessionHasNoErrors();

    expect(SummaryJob::query()->count())->toBe(1);
});

/**
 * **ولا يُردّ النموذج فارغاً.**
 *
 * فالحاجز يقف قبل المتحكّم، ونموذجُ «ملخّص جديد» فيه عشرة حقول ملأها
 * المستخدم — وردُّه فارغاً عقوبةٌ على حدٍّ ليس ذنبَه.
 */
it('يحفظ ما أدخله المستخدم حين يردّه الحدّ', function (): void {
    fillQuota($this->tenant, 6);

    $this->actingAs($this->user)->post('/panel/lectures', newLecturePayload());

    expect(session()->getOldInput('title_ar'))->toBe('عنوان الدرس')
        ->and(session()->getOldInput('speaker_name'))->toBe('اسم الملقي');
});

it('يمنع الإنشاء على جهةٍ معلَّقة', function (): void {
    $this->tenant->forceFill(['status' => Tenant::SUSPENDED])->save();

    $this->actingAs($this->user)->post('/panel/lectures', newLecturePayload())
        ->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();
});

/*
 * ─── «أعد المحاولة»: الثغرة التي أغلقها T-29 ─────────────────────────
 */

/**
 * ★ **وهذه ثغرةٌ لا تنظيمٌ للشيفرة.**
 *
 * فـ`RequestRegeneration` يفحص عدّاد إعادة التوليد وحده، **ولا يفحص تعليق
 * الاشتراك ولا الحصّة الشهرية ولا سقف الإنفاق**. فكانت جهةٌ معلَّقة تضع
 * مهمّةً في الطابور بضغطة «أعد المحاولة»، وتصرف توكنز.
 */
it('يمنع إعادة المحاولة على جهةٍ معلَّقة', function (): void {
    $job = jobFor_($this->tenant);

    $this->tenant->forceFill(['status' => Tenant::SUSPENDED])->save();

    $this->actingAs($this->user)->post(route('jobs.retry', $job))
        ->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();

    // ولا تُنشأ مهمّةٌ خَلَف، ولا يُزاد عدّاد إعادة التوليد.
    expect(SummaryJob::query()->count())->toBe(1)
        ->and((int) $job->refresh()->regeneration_count)->toBe(0);
});

it('يمنع إعادة المحاولة وقد نفدت الحصّة الشهرية', function (): void {
    $job = jobFor_($this->tenant);

    fillQuota($this->tenant, 6);

    $this->actingAs($this->user)->post(route('jobs.retry', $job))
        ->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();
});

/** وتمرّ حين لا حدَّ يمنع — فالحاجز يحرس ولا يُعطّل. */
it('يمرّر إعادة المحاولة حين لا حدَّ يمنع', function (): void {
    $job = jobFor_($this->tenant);

    $this->actingAs($this->user)->post(route('jobs.retry', $job))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(RunSummaryPipeline::class);
});

/*
 * ─── سقف الإنفاق: يوقف الطابور كلّه ──────────────────────────────────
 */

/**
 * «عند تجاوز عتبة الإعداد **يُوقَف الطابور كلّه**» — §11.
 *
 * فلا يستثني عملاً قائماً ولا مشرفاً: بلوغُه يعني أنّ الصرف وقف عندنا.
 */
it('يوقف كل ما يضع في الطابور حين يُبلغ سقف الإنفاق', function (): void {
    app(SpendCap::class)->halt('اختبار');

    $job = jobFor_($this->tenant);
    $review = jobFor_($this->tenant, JobState::NeedsReview);

    $this->actingAs($this->user)->post('/panel/lectures', newLecturePayload())
        ->assertSessionHasErrors('quota');

    $this->actingAs($this->user)->post(route('jobs.retry', $job))
        ->assertSessionHasErrors('quota');

    // والاستئناف بعد المراجعة كذلك — وهو `quota:cap`.
    $this->actingAs($this->user)->post(route('jobs.resume', $review))
        ->assertSessionHasErrors('quota');

    // وإعادةُ المشرف كذلك: العطل عندنا، والصرف موقوف.
    $this->actingAs(User::factory()->superAdmin()->create(), 'admin')
        ->post(route('admin.jobs.retry', $job))
        ->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();
});

/*
 * ─── الاستئناف: عملٌ دُفع ثمنُه، فلا يُخصم مرّتين ────────────────────
 */

/**
 * ★ معيار القبول الثاني: **ولا يُخصم شيءٌ مرّتين.**
 *
 * فالاستئناف بعد المراجعة عملٌ دُفع ثمنُه عند الإنشاء، وخصمُ حصّةٍ ثانيةٍ
 * عنه خصمٌ مرّتين عن ملخّصٍ واحد.
 */
it('يستأنف بعد المراجعة ولو نفدت الحصّة الشهرية', function (): void {
    $job = jobFor_($this->tenant, JobState::NeedsReview);

    fillQuota($this->tenant, 6);

    $this->actingAs($this->user)->post(route('jobs.resume', $job))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(RunSummaryPipeline::class);
});

/** وإعادةُ المشرف لا تُحاسَب بحدود الجهة: العطل عندنا لا عندها. */
it('يعيد المشرف تشغيل مهمّةٍ ولو نفدت حصّة جهتها', function (): void {
    $job = jobFor_($this->tenant);

    fillQuota($this->tenant, 6);

    $this->actingAs(User::factory()->superAdmin()->create(), 'admin')
        ->post(route('admin.jobs.retry', $job))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(RunSummaryPipeline::class);
});

/** يستهلك حصّة الشهر بالكامل من دفتر الاستهلاك — مصدر الحقيقة (§4). */
function fillQuota(Tenant $tenant, int $units): void
{
    UsageRecord::query()->create([
        'tenant_id' => $tenant->id,
        'event' => UsageEvent::Generate->value,
        'units' => $units,
        'occurred_at' => now(),
    ]);
}
