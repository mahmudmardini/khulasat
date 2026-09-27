<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use App\Contracts\ModelGateway;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\MatchStatus;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Enums\UsageEvent;
use App\Jobs\TranslateAddedLocale;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Quota\SpendCap;
use App\Support\Model\ModelResponse;
use App\Support\Publish\LocaleAdditions;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * إضافةُ لغةٍ إلى ملخّصٍ قائم — T-166، طلبُ مالك المنتج.
 *
 * ★★ **والمقياسُ الحاكم هنا ما لا يُنادى.** فالبديلُ الذي يُغني عنه هذا
 * ملخّصٌ جديدٌ بمراحله الستّ، يُدفع ثمنُه ثانيةً لعملٍ في الجدول. فإن نادت
 * الإضافةُ مرحلةً غيرَ الترجمة، أو نادتها لغيرِ اللغة المضافة، فقد أعادت
 * الضررَ الذي بُنيت لتزيله.
 */

beforeEach(function (): void {
    // الترجمةُ مرحلةٌ تُنادى فتحتاج صفَّها — والبوّابةُ الوهميّة هي الافتراض
    // (CLAUDE.md §2 القاعدة السابعة)، فلا نداءَ حقيقيّ.
    (new ModelConfigSeeder)->run();

    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', '');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'monthly_quota' => 5]);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    // النداءاتُ تُعدّ على البوّابة: البوّابةُ الوهميّة كلفتُها صفر فلا تُقيَّد في `model_calls`.
    $this->calls = new class(app(ModelGateway::class)) implements ModelGateway
    {
        /** @var list<string> */
        public array $stages = [];

        public function __construct(private readonly ModelGateway $inner) {}

        public function call(Stage $stage, array $messages, ?array $schema = null, ?SummaryJob $job = null): ModelResponse
        {
            $this->stages[] = $stage->value;

            return $this->inner->call($stage, $messages, $schema, $job);
        }
    };

    app()->instance(ModelGateway::class, $this->calls);
});

/** ملخّصٌ عربيّ منتهٍ — في `rendering`، أو منشورٌ إن طُلب. */
function arabicSummary(Tenant $tenant, bool $published = false): SummaryJob
{
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar'],
    ]);

    $job = SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'state' => JobState::Rendering->value,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنُ الفقرة.']]]]],
        'body_html' => '<p class="lead">فقرة عربية.</p>',
    ])->fresh();

    if ($published) {
        app(RenderAndPublish::class)->handle($job);
        $job->refresh();
    }

    // ما صُرف على الإنشاء نفسه لا يُحسب على الإضافة.
    test()->calls->stages = [];

    return $job;
}

function addLocale_(SummaryJob $job, string $locale): TestResponse
{
    return test()->actingAs(test()->user)->post("/panel/jobs/{$job->id}/locales", ['locale' => $locale]);
}

it('يضيف اللغة إلى المحاضرة ويضع ترجمتها وحدها في الطابور', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    addLocale_($job, 'en')->assertRedirect()->assertSessionHasNoErrors();

    expect($job->lecture->fresh()->locales)->toBe(['ar', 'en']);

    $row = SummaryTranslation::query()->where('summary_job_id', $job->id)->sole();

    expect($row->locale)->toBe(Locale::En)
        ->and($row->requested_at)->not->toBeNull()
        ->and(LocaleAdditions::stateOf($job->fresh(), Locale::En))->toBe(LocaleAdditions::TRANSLATING);

    Queue::assertPushed(TranslateAddedLocale::class, fn (TranslateAddedLocale $queued): bool => $queued->summaryJobId === $job->id && $queued->locale === 'en');

    // ولا نداءَ قبل الطابور: الطلبُ يُسجَّل والعاملُ يترجم.
    expect($this->calls->stages)->toBe([]);
});

it('يترجم بنداءٍ واحد لمرحلة الترجمة، ولا يمسّ مرحلةً غيرها', function (): void {
    $job = arabicSummary($this->tenant);

    // الطابورُ متزامنٌ في الاختبار، فيجري العاملُ في الطلب نفسه.
    addLocale_($job, 'en')->assertSessionHasNoErrors();

    /*
     * ★★ **هذا هو المقياسُ كلُّه.** ملخّصٌ جديدٌ كان ينادي المراحلَ كلَّها
     * ومعها الترجمة؛ والإضافةُ نداءٌ واحد.
     */
    expect($this->calls->stages)->toBe([Stage::Translating->value]);

    $row = SummaryTranslation::query()->where('summary_job_id', $job->id)->sole();

    expect($row->isReady())->toBeTrue()
        ->and($row->requested_at)->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and(LocaleAdditions::stateOf($job->fresh(), Locale::En))->toBe(LocaleAdditions::READY);

    // ولم يبلغ ملخّصٌ غيرُ منشورٍ النشرَ بسببها.
    expect($job->fresh()->state)->toBe(JobState::Rendering)
        ->and(Output::acrossTenants()->where('summary_job_id', $job->id)->whereNotNull('storage_path')->exists())->toBeFalse();
});

it('لا يُخصم من حصّة الملخّصات، ويمرّ والحصّةُ نافدة', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    UsageRecord::query()->create([
        'tenant_id' => $this->tenant->id,
        'event' => UsageEvent::Generate->value,
        'units' => 5,
        'occurred_at' => now(),
    ]);

    $before = UsageRecord::query()->count();

    addLocale_($job, 'tr')->assertSessionHasNoErrors();

    Queue::assertPushed(TranslateAddedLocale::class);
    expect(UsageRecord::query()->count())->toBe($before);
});

it('يقف عند سقف الإنفاق وعند تعليق الاشتراك قبل الطابور', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    app(SpendCap::class)->halt('اختبار');
    addLocale_($job, 'en')->assertSessionHasErrors('quota');
    app(SpendCap::class)->release();

    $this->tenant->forceFill(['status' => 'suspended'])->save();
    addLocale_($job, 'en')->assertSessionHasErrors('quota');

    Queue::assertNothingPushed();
    expect($job->lecture->fresh()->locales)->toBe(['ar']);
});

it('ينشر اللغة الجديدة لملخّصٍ منشور، ويربطها بأختها', function (): void {
    $job = arabicSummary($this->tenant, published: true);

    addLocale_($job, 'en')->assertSessionHasNoErrors();

    // والنشرُ وإعادةُ الربط رسمٌ بلا نداء — §8-أ.
    expect($this->calls->stages)->toBe([Stage::Translating->value]);

    $pages = Output::acrossTenants()
        ->where('summary_job_id', $job->id)
        ->where('type', OutputType::Page->value)
        ->get();

    $arabic = $pages->firstWhere(fn (Output $o): bool => $o->locale === Locale::Ar);
    $english = $pages->firstWhere(fn (Output $o): bool => $o->locale === Locale::En);

    expect($english?->storage_path)->not->toBeNull()
        // ★ والجذرُ بقي عربياً: الرابطُ الذي شوركَ لم ينقلب.
        ->and($arabic?->public_url)->not->toContain('/en');

    // والعربيةُ المنشورة قبلها تعرفها الآن في شريط اللغات — T-134.
    expect(Storage::disk('public')->get((string) $arabic->storage_path))
        ->toContain((string) $english->public_url);
});

it('لا ينشر لغةً لملخّصٍ أُزيل نشرُه', function (): void {
    $job = arabicSummary($this->tenant, published: true);
    $job->forceFill(['unpublished_at' => now()])->save();

    addLocale_($job, 'en')->assertSessionHasNoErrors();

    expect(Output::acrossTenants()
        ->where('summary_job_id', $job->id)
        ->where('locale', Locale::En->value)
        ->whereNotNull('storage_path')
        ->exists())->toBeFalse();
});

it('يرفض ملخّصاً فيه شاهدٌ معلّق', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    EvidenceItem::factory()->for_($job)->create([
        'match_status' => MatchStatus::None,
        'review_status' => ReviewStatus::Pending,
    ]);

    addLocale_($job, 'en')->assertSessionHasErrors('locale');

    Queue::assertNothingPushed();
});

it('يرفض لغةً مضافةً أو قيدَ الترجمة، ولا يضاعف النداء', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    addLocale_($job, 'en')->assertSessionHasNoErrors();
    addLocale_($job, 'en')->assertSessionHasErrors('locale');

    // والعربيةُ مختارةٌ أصلاً.
    addLocale_($job, 'ar')->assertSessionHasErrors('locale');

    Queue::assertPushed(TranslateAddedLocale::class, 1);
});

it('لا يعرض العربية لملخّصٍ نُشر بغيرها، فلا ينقلب جذرُه', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);
    $job->lecture->forceFill(['locales' => ['en']])->save();

    $keys = array_column(LocaleAdditions::for($job->fresh()), 'key');

    expect($keys)->not->toContain('ar');

    addLocale_($job, 'ar')->assertSessionHasErrors('locale');
    Queue::assertNothingPushed();
});

it('يُظهر الترجمةَ الساقطة ويقبل إعادتها', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    addLocale_($job, 'en')->assertSessionHasNoErrors();

    $queued = new TranslateAddedLocale($job->id, 'en');
    $queued->failed(new RuntimeException('انقطع'));

    // والإطارُ يحرّر قفلَ التفرّد عند الإخفاق؛ وهنا نُودي `failed()` بيدنا فيُحرَّر بيدنا.
    (new UniqueLock(app('cache')->driver()))->release($queued);

    $row = SummaryTranslation::query()->where('summary_job_id', $job->id)->sole();

    expect($row->failed_at)->not->toBeNull()
        ->and($row->requested_at)->toBeNull()
        ->and(LocaleAdditions::stateOf($job->fresh(), Locale::En))->toBe(LocaleAdditions::FAILED);

    addLocale_($job, 'en')->assertSessionHasNoErrors();

    Queue::assertPushed(TranslateAddedLocale::class, 2);
});

it('يعدّ طلباً عالقاً منذ ربع ساعة ساقطاً، فلا تنتظره الشاشة أبداً', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);

    addLocale_($job, 'en')->assertSessionHasNoErrors();

    SummaryTranslation::query()->where('summary_job_id', $job->id)
        ->update(['requested_at' => now()->subMinutes(16)]);

    expect(LocaleAdditions::stateOf($job->fresh(), Locale::En))->toBe(LocaleAdditions::FAILED);
});

it('لا يُتاح لمن لا ينشر', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant);
    $viewer = User::factory()->viewer()->for_($this->tenant)->create();

    $this->actingAs($viewer)->post("/panel/jobs/{$job->id}/locales", ['locale' => 'en'])->assertForbidden();

    Queue::assertNothingPushed();
});

it('يعرض في المعاينة وشاشة الملخّص ما يُضاف', function (): void {
    Queue::fake();

    $job = arabicSummary($this->tenant, published: true);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('locale_additions', 3)
            ->where('locale_additions.0.key', 'en')
            ->where('locale_additions.0.state', LocaleAdditions::AVAILABLE));

    addLocale_($job, 'en');

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('locale_additions.0.key', 'en')
            ->where('locale_additions.0.state', LocaleAdditions::TRANSLATING));

    // والمبدّلُ يقول «جارٍ» لا «لم تُترجَم».
    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('locales.1.key', 'en')
            ->where('locales.1.translated', false)
            ->where('locales.1.translating', true));
});
