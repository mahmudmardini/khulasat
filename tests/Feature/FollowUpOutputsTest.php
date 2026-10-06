<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use App\Contracts\ModelGateway;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\Stage;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Model\ModelResponse;
use App\Support\Publish\LocaleAdditions;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * ما بعد اللغة الأولى «في الطريق» لا «سقط» — T-228، بلاغ @HasanSiwi.
 *
 * ★ المهمّةُ تبلغ `published` بنشر اللغة الأولى، ثمّ يترجم العاملُ نفسُه
 * اللغاتِ التالية ويبني الشرائح. وكانت المعاينةُ في تلك الدقيقة تقول
 * «تعذّرت ترجمة الإنجليزية» و«لم تُنشأ» الشرائح، ولا تتحدّث من نفسها.
 */

beforeEach(function (): void {
    (new ModelConfigSeeder)->run();

    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', '');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'plan' => 'business']);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    // يُنادى عند كلّ ترجمة — فيرى الاختبارُ الحالَ في وسط الخطّ.
    $this->gateway = new class(app(ModelGateway::class)) implements ModelGateway
    {
        public ?Closure $onTranslate = null;

        public bool $failTranslation = false;

        public function __construct(private readonly ModelGateway $inner) {}

        public function call(Stage $stage, array $messages, ?array $schema = null, ?SummaryJob $job = null): ModelResponse
        {
            if ($stage === Stage::Translating) {
                if ($this->onTranslate !== null) {
                    ($this->onTranslate)($job);
                }

                if ($this->failTranslation) {
                    throw new RuntimeException('انقطع');
                }
            }

            return $this->inner->call($stage, $messages, $schema, $job);
        }
    };

    app()->instance(ModelGateway::class, $this->gateway);
});

/** ملخّصٌ عربيّ منتهٍ في `rendering`، لغاتُه العربيةُ والإنجليزية. */
function bilingualSummary(Tenant $tenant, bool $carousel = false): SummaryJob
{
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar', 'en'],
        'want_carousel' => $carousel,
    ]);

    return SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'state' => JobState::Rendering->value,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنُ الفقرة.']]]]],
        'body_html' => '<p class="lead">فقرة عربية.</p>',
    ])->fresh();
}

it('يُري اللغةَ التالية «تُترجَم» لا «سقطت» والمهمّةُ منشورةٌ والعاملُ يترجمها', function (): void {
    $job = bilingualSummary($this->tenant);
    $seen = [];

    $this->gateway->onTranslate = function () use ($job, &$seen): void {
        $fresh = $job->fresh();
        $seen = [
            'state' => $fresh->state,
            'finishing' => $fresh->isFinishing(),
            'en' => LocaleAdditions::stateOf($fresh, Locale::En),
        ];
    };

    app(RenderAndPublish::class)->handle($job);

    expect($seen['state'])->toBe(JobState::Published)
        ->and($seen['finishing'])->toBeTrue()
        ->and($seen['en'])->toBe(LocaleAdditions::TRANSLATING);

    // وانتهى: العلامةُ تُمحى، والإنجليزيةُ جاهزة.
    $job->refresh();

    expect($job->finishing_at)->toBeNull()
        ->and(LocaleAdditions::stateOf($job, Locale::En))->toBe(LocaleAdditions::READY);
});

it('يمحو العلامةَ إن سقطت الترجمة، فتُعرض ساقطةً تُعاد لا منتظَرة', function (): void {
    $job = bilingualSummary($this->tenant);
    $this->gateway->failTranslation = true;

    app(RenderAndPublish::class)->handle($job);

    $job->refresh();

    expect($job->state)->toBe(JobState::Published)
        ->and($job->finishing_at)->toBeNull()
        ->and(LocaleAdditions::stateOf($job, Locale::En))->toBe(LocaleAdditions::FAILED);
});

it('يعدّ علامةً عالقةً منذ ربع ساعة منتهية، فلا تنتظر الشاشةُ عاملاً مات', function (): void {
    $job = bilingualSummary($this->tenant);
    $this->gateway->failTranslation = true;

    app(RenderAndPublish::class)->handle($job);

    $job->forceFill(['finishing_at' => now()->subMinutes(SummaryJob::FINISHING_STALE_AFTER_MINUTES + 1)])->save();

    expect($job->fresh()->isFinishing())->toBeFalse()
        ->and(LocaleAdditions::stateOf($job->fresh(), Locale::En))->toBe(LocaleAdditions::FAILED);
});

it('تقول المعاينةُ «تُبنى» للّغة والشرائح ما دام الخطُّ يبنيهما، ثمّ «سقطت» بعده', function (): void {
    $job = bilingualSummary($this->tenant, carousel: true);
    $this->gateway->failTranslation = true;

    app(RenderAndPublish::class)->handle($job);

    // نُعيد الحالَ إلى وسط الخطّ: منشورةٌ، والإنجليزيةُ والشرائحُ لم تُبنيا.
    $job->outputs()->where('type', 'carousel')->delete();
    $job->forceFill(['finishing_at' => now()])->save();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.published', true)
            ->where('job.finishing', true)
            ->where('outputs.carousel.produced', false)
            ->where('outputs.carousel.pending', true)
            ->where('locales.1.key', 'en')
            ->where('locales.1.translated', false)
            ->where('locales.1.translating', true)
            ->where('locale_additions.0.key', 'en')
            ->where('locale_additions.0.state', LocaleAdditions::TRANSLATING));

    $job->forceFill(['finishing_at' => null])->save();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.finishing', false)
            ->where('outputs.carousel.pending', false)
            ->where('locales.1.translating', false)
            ->where('locale_additions.0.state', LocaleAdditions::FAILED));
});

it('لا يقول «تُبنى» لشرائح لم تُطلب', function (): void {
    $job = bilingualSummary($this->tenant);

    app(RenderAndPublish::class)->handle($job);

    $job->forceFill(['finishing_at' => now()])->save();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.carousel.pending', false)
            // والإنجليزيةُ جاهزة، فلا «جارٍ» لها وإن كانت العلامةُ قائمة.
            ->where('locales.1.translated', true)
            ->where('locales.1.translating', false));
});
