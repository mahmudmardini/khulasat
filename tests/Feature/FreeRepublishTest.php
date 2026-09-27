<?php

declare(strict_types=1);

use App\Actions\Stages\RenderAndPublish;
use App\Contracts\ModelGateway;
use App\Domain\Summary\JobState;
use App\Enums\Stage;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Model\ModelResponse;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * «حدّث المنشور» بلا نداءٍ مدفوع — T-167، طلبُ مالك المنتج.
 *
 * ★★ **والضررُ كان في كلّ ضغطة.** فالزرُّ يمرّ على {@see RenderAndPublish}،
 * وهذه كانت تنادي المرحلة السادسة (بيانات الصفحة) كلَّ مرّة — على بنيةٍ لا
 * تُكتب بعد الاستخراج. وواجهتُه تقول إنّ إعادة الرسم مجّانية (§8-أ).
 *
 * فالمقيسُ هنا النداءُ نفسُه: يُعدّ على البوّابة، وهو ما يُدفع ثمنُه.
 */

beforeEach(function (): void {
    (new ModelConfigSeeder)->run();

    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', '');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

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

/** ملخّصٌ بلغتين منشورٌ بالخطّ — وما صُرف في نشره الأوّل لا يُعدّ. */
function publishedTwice_(Tenant $tenant): SummaryJob
{
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar', 'en'],
    ]);

    $job = SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'state' => JobState::Rendering->value,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنُ الفقرة.']]]]],
        'body_html' => '<p class="lead">فقرة عربية.</p>',
    ])->fresh();

    app(RenderAndPublish::class)->handle($job);

    return $job->refresh();
}

it('يتبنّى بياناتٍ بلا بصمة ويختمها بلا نداء', function (): void {
    $job = publishedTwice_($this->tenant);

    // بياناتُ ما قبل T-167: بلا بصمة.
    $job->forceFill(['output_meta_json' => ['meta_title' => 'عنوانٌ قديم', 'meta_description' => 'وصفٌ قديم']])->save();

    $this->calls->stages = [];

    app(RenderAndPublish::class)->handle($job->fresh());

    $meta = $job->fresh()->output_meta_json;

    expect($this->calls->stages)->not->toContain(Stage::OutputMetadata->value)
        ->and($meta['meta_title'])->toBe('عنوانٌ قديم')
        ->and($meta)->toHaveKey('source_hash');
});

it('يبني البيانات من جديد لبنيةٍ تبدّلت', function (): void {
    $job = publishedTwice_($this->tenant);

    $job->forceFill(['structure_json' => ['title_ar' => 'عنوانٌ آخر']])->save();

    $this->calls->stages = [];

    app(RenderAndPublish::class)->handle($job->fresh());

    expect($this->calls->stages)->toContain(Stage::OutputMetadata->value);
});
