<?php

declare(strict_types=1);

use App\Contracts\ModelGateway;
use App\Contracts\OverflowProbe;
use App\Contracts\ShareCardCapturer;
use App\Domain\Summary\JobState;
use App\Enums\AuditAction;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Enums\SlideKind;
use App\Enums\Stage;
use App\Models\AuditEvent;
use App\Models\Lecture;
use App\Models\ModelCall;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\UsageRecord;
use App\Models\User;
use App\Services\Quota\SpendCap;
use App\Support\Model\ModelResponse;
use App\Support\Model\StagePrompt;
use App\Support\Render\CarouselDesign;
use App\Support\Render\Slide;
use App\Support\Render\SlideDeck;
use App\Support\Render\TenantCarouselDesigns;
use App\Support\Verification\DomainPolicy;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * قوالبُ كاروسيل الجهة — T-173، المرحلتان الثانية والثالثة.
 *
 * والنموذجُ هنا وهميٌّ يردّ ما يُعطى ويحفظ ما يصله: ما يُختبر هو ما يُرسل
 * إليه (التعليمات، والشعار، والكتالوج) وما يُقبل من ردّه وما يُرمى.
 */

/** قالبٌ كما يردّه النموذج: بلا معرّف، ومعه سببُه. */
function proposedDesign(array $overrides = []): array
{
    return [
        'name' => 'ليليٌّ هندسي',
        'rationale' => 'شعارٌ هندسيّ، فالنقش الشبكيّ والكوفيّ يمتدّان منه.',
        'surface' => 'night',
        'bookends' => 'deep',
        'background' => 'lattice',
        'frame' => 'double',
        'ornament' => 'star',
        'heading_font' => 'reem-kufi',
        'accent' => 'gold',
        'number' => 'bar',
        'layouts' => [
            'cover' => 'poster', 'ayah' => 'medallion', 'concept' => 'band', 'diagnosis' => 'side',
            'axis' => 'band', 'comparison' => 'side', 'evidence' => 'medallion', 'closing' => 'poster',
        ],
        ...$overrides,
    ];
}

/** ثلاثةُ قوالب صالحة متمايزة. */
function threeDesigns(): array
{
    return [
        proposedDesign(),
        proposedDesign(['name' => 'ورقيٌّ هادئ', 'surface' => 'paper-2', 'bookends' => 'paper', 'background' => 'dots', 'frame' => 'corners']),
        proposedDesign(['name' => 'حديثٌ مبسَّط', 'surface' => 'paper', 'background' => 'gradient', 'frame' => 'none', 'heading_font' => 'plex']),
    ];
}

beforeEach(function (): void {
    $this->gateway = new class implements ModelGateway
    {
        /** @var list<array{stage: Stage, messages: array}> */
        public array $calls = [];

        public array $designs = [];

        public function call(Stage $stage, array $messages, ?array $schema = null, ?SummaryJob $job = null): ModelResponse
        {
            $this->calls[] = ['stage' => $stage, 'messages' => $messages];
            $decoded = ['designs' => $this->designs];

            return new ModelResponse(
                stage: $stage,
                provider: 'anthropic',
                modelId: 'claude-opus-5-5',
                content: (string) json_encode($decoded, JSON_UNESCAPED_UNICODE),
                inputTokens: 3_000,
                outputTokens: 1_200,
                costUsd: 0.036,
                decoded: $decoded,
            );
        }
    };

    $this->gateway->designs = threeDesigns();
    app()->instance(ModelGateway::class, $this->gateway);

    // شعارٌ PNG صغير، يُرسل كما هو.
    $logo = imagecreatetruecolor(8, 8);
    ob_start();
    imagepng($logo);
    $png = (string) ob_get_clean();

    $this->tenant = Tenant::factory()->create([
        'plan' => 'business',
        'name_ar' => 'جهة الاختبار',
        'brand_kit' => ['palette' => 'indigo', 'logo_data_uri' => 'data:image/png;base64,'.base64_encode($png)],
    ]);

    $this->owner = User::factory()->owner()->for_($this->tenant)->create();
    $this->admin = User::factory()->superAdmin()->create();
});

/**
 * طلبُ التوليد من «هوية الجهة».
 *
 * والمستخدمُ يُقرأ من جديد في كلّ طلب، كما في التطبيق: نسخةٌ واحدةٌ عبر الطلبات
 * تحفظ جهتَها من أوّلها، فيقرأ الطلبُ الثاني قوالبَ قبل التوليد.
 */
function generateFor(object $test): void
{
    $test->actingAs($test->owner->fresh())->post('/panel/settings/brand/carousel-designs')->assertRedirect()->assertSessionHasNoErrors();
}

it('يولّد ثلاثة قوالب مرشّحة من الكتالوج، ويقيّد كلفتها للجهة', function (): void {
    generateFor($this);

    $tenant = $this->tenant->fresh();
    $candidates = TenantCarouselDesigns::candidates($tenant);

    expect($candidates)->toHaveCount(3)
        ->and(TenantCarouselDesigns::status($tenant)['state'])->toBe('ready')
        // ★ **المرشّحُ لا يُستعمل حتى يُعتمد**: الكاروسيلُ على الأصل بعدُ.
        ->and(TenantCarouselDesigns::approved($tenant))->toBe([])
        ->and(CarouselDesign::forTenant($tenant)->isDefault())->toBeTrue()
        ->and($candidates[0]['design']->id)->toStartWith('c-')
        ->and($candidates[0]['rationale'])->toContain('هندسيّ');

    // نداءٌ للجهة لا لملخّص: يُقيَّد بلا `summary_job_id`، ويحسبه السقف.
    $call = ModelCall::acrossTenants()->where('tenant_id', $this->tenant->id)->sole();

    expect($call->summary_job_id)->toBeNull()
        ->and($call->stage)->toBe(Stage::CarouselDesign)
        ->and((float) UsageRecord::acrossTenants()->where('tenant_id', $this->tenant->id)->sum('cost_usd'))->toBe(0.036)
        ->and(app(SpendCap::class)->spentToday())->toBe(0.036);
});

it('يرسل التعليمات الافتراضية، والشعارَ صورةً، والكتالوجَ والّلوحة', function (): void {
    generateFor($this);

    [$system, $user] = $this->gateway->calls[0]['messages'];
    $brief = json_decode($user['content'], true);

    expect($system['content'])->toBe(StagePrompt::for(Stage::CarouselDesign, DomainPolicy::DEFAULT))
        ->and($user['images'][0]['mime'])->toBe('image/png')
        ->and($brief['logo'])->toBe('attached')
        ->and($brief['palette']['key'])->toBe('indigo')
        ->and($brief['catalog'])->toBe(CarouselDesign::CATALOG)
        ->and($brief['layouts'])->toBe(CarouselDesign::LAYOUTS);
});

// ★ **صارمةٌ لا متسامحة**: قالبٌ بقيمةٍ واحدةٍ خارج الكتالوج يُرمى كلُّه.
it('يرمي القالب الذي فيه قيمةٌ خارج الكتالوج، ويُبقي الصالح والفريد', function (): void {
    $this->gateway->designs = [
        proposedDesign(),
        proposedDesign(['surface' => 'neon']),
        proposedDesign(['layouts' => [...proposedDesign()['layouts'], 'ayah' => 'band; content: "x"']]),
        // مكرَّرٌ باسمٍ آخر: قالبٌ واحد.
        proposedDesign(['name' => 'اسمٌ آخر']),
    ];

    generateFor($this);

    expect(TenantCarouselDesigns::candidates($this->tenant->fresh()))->toHaveCount(1);
});

// ★ قالبٌ يفيض نصُّه على كاروسيل الإجهاد يُقصّ في الصورة صامتاً، فلا يُعرض.
it('يرمي القالب الذي يفيض نصُّه على كاروسيل الإجهاد، ولا يرمي ما تعذّر قياسُه', function (): void {
    app()->instance(OverflowProbe::class, new class implements OverflowProbe
    {
        /** @var list<string> */
        public array $seen = [];

        public function overflowing(string $html): ?array
        {
            $this->seen[] = $html;

            // القالبُ الليليّ يفيض في شريحته الثالثة، والباقيان لا يُقاسان.
            // وصنفُ `.deck` لا نصُّ الأنماط: قواعدُ القوالب كلِّها في كلّ وثيقة.
            return str_contains($html, 'class="deck surface-night') ? [3] : null;
        }
    });

    generateFor($this);

    $names = array_map(fn (array $c): ?string => $c['design']->name, TenantCarouselDesigns::candidates($this->tenant->fresh()));
    $probe = app(OverflowProbe::class);

    expect($names)->toBe(['ورقيٌّ هادئ', 'حديثٌ مبسَّط'])
        ->and($probe->seen)->toHaveCount(3)
        // يُقاس على كاروسيل الإجهاد: الآيةُ تامّةٌ بعلامتها، والحديثُ الطويل.
        ->and($probe->seen[0])->toContain('۝٩٧')->toContain(e('احفظ الله يحفظك'));
});

it('يقول إنّ شيئاً لم يصلح، ولا يمسّ المرشّحَ السابق', function (): void {
    generateFor($this);

    $this->gateway->designs = [proposedDesign(['frame' => 'neon']), ['name' => 'ناقص']];

    generateFor($this);

    $tenant = $this->tenant->fresh();

    expect(TenantCarouselDesigns::status($tenant))->toMatchArray([
        'state' => 'failed',
        'error' => trans('common.carousel_designs.none_valid'),
    ])->and(TenantCarouselDesigns::candidates($tenant))->toHaveCount(3);
});

it('لا يولّد والسقفُ موقوف، ولا يولّد لجهةٍ بلا مخرجاتٍ غنية', function (): void {
    app(SpendCap::class)->halt('اختبار');

    $this->actingAs($this->owner->fresh())->post('/panel/settings/brand/carousel-designs')->assertSessionHasErrors('quota');

    app(SpendCap::class)->release();
    $this->tenant->forceFill(['plan' => 'free'])->save();

    $this->actingAs($this->owner->fresh())->post('/panel/settings/brand/carousel-designs')->assertSessionHasErrors('carousel_designs');

    expect($this->gateway->calls)->toBe([]);
});

it('يعتمد مرشّحاً فيصير افتراضيَّ الجهة، ويجعل غيرَه الافتراضيّ، ويحذف', function (): void {
    generateFor($this);

    [$first, $second] = array_column(TenantCarouselDesigns::candidates($this->tenant->fresh()), 'design');

    $this->actingAs($this->owner->fresh())->post("/panel/settings/brand/carousel-designs/{$first->id}/approve")->assertSessionHasNoErrors();
    $this->actingAs($this->owner->fresh())->post("/panel/settings/brand/carousel-designs/{$second->id}/approve")->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect(array_map(fn (CarouselDesign $d): string => $d->id, TenantCarouselDesigns::approved($tenant)))->toBe([$first->id, $second->id])
        ->and(TenantCarouselDesigns::candidates($tenant))->toHaveCount(1)
        ->and(CarouselDesign::forTenant($tenant)->id)->toBe($first->id);

    $this->actingAs($this->owner->fresh())->post("/panel/settings/brand/carousel-designs/{$second->id}/default");
    expect(CarouselDesign::forTenant($this->tenant->fresh())->id)->toBe($second->id);

    $this->actingAs($this->owner->fresh())->delete("/panel/settings/brand/carousel-designs/{$second->id}");
    expect(CarouselDesign::forTenant($this->tenant->fresh())->id)->toBe($first->id);
});

it('يعاين القالب على كاروسيل العيّنة، ولا يُري جهةً قوالبَ غيرها', function (): void {
    // قبل أيّ طلب: الطلبُ يضبط سياق جهته، والكتابةُ لجهةٍ أخرى بعده تُرفض.
    $other = User::factory()->owner()->for_(Tenant::factory()->create(['plan' => 'business']))->create();

    generateFor($this);

    $design = TenantCarouselDesigns::candidates($this->tenant->fresh())[0]['design'];

    $html = $this->actingAs($this->owner->fresh())
        ->get("/panel/settings/brand/carousel-designs/{$design->id}/preview")
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->getContent();

    expect($html)->toContain('surface-night')->toContain('l-medallion')->toContain(e('إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ'));

    $this->actingAs($other)->get("/panel/settings/brand/carousel-designs/{$design->id}/preview")->assertNotFound();
});

it('يعرض القوالب في هوية الجهة', function (): void {
    generateFor($this);

    $this->actingAs($this->owner->fresh())->get('/panel/settings/brand')
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('carousel_designs.candidates', 3)
            ->where('carousel_designs.status.state', 'ready')
            ->where('rich_outputs', true)
        );
});

// ── لوحة المشرف ─────────────────────────────────────────────────

it('يكتب المشرف لجهةٍ تعليماتٍ خاصّة، ويُقيَّد ذلك، وفارغُها يعيد الافتراضية', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/carousel-designs/prompt", ['prompt' => '  صمّم بطابعٍ ريفيّ دافئ.  '])
        ->assertRedirect();

    expect($this->tenant->fresh()->carousel_design_prompt)->toBe('صمّم بطابعٍ ريفيّ دافئ.')
        ->and(AuditEvent::query()->where('action', AuditAction::TenantCarouselPrompt->value)->count())->toBe(1);

    $this->actingAs($this->admin, 'admin')
        ->put("/admin/tenants/{$this->tenant->id}/carousel-designs/prompt", ['prompt' => ''])
        ->assertRedirect();

    expect($this->tenant->fresh()->carousel_design_prompt)->toBeNull();
});

it('يعيد المشرف التوليدَ بتعليمات الجهة، ويبقى الاعتمادُ لها', function (): void {
    $this->tenant->forceFill(['carousel_design_prompt' => 'تعليماتٌ خاصّة بهذه الجهة.'])->save();

    $this->actingAs($this->admin, 'admin')->post("/admin/tenants/{$this->tenant->id}/carousel-designs")->assertRedirect();

    $tenant = $this->tenant->fresh();

    expect($this->gateway->calls[0]['messages'][0]['content'])->toBe('تعليماتٌ خاصّة بهذه الجهة.')
        ->and(TenantCarouselDesigns::candidates($tenant))->toHaveCount(3)
        ->and(TenantCarouselDesigns::approved($tenant))->toBe([])
        ->and(AuditEvent::query()->where('action', AuditAction::TenantCarouselDesigns->value)->count())->toBe(1);

    $this->actingAs($this->admin, 'admin')->get("/admin/tenants/{$this->tenant->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('carousel_prompt.custom', 'تعليماتٌ خاصّة بهذه الجهة.')
            ->where('carousel_prompt.default', StagePrompt::for(Stage::CarouselDesign, DomainPolicy::DEFAULT))
            ->has('carousel_designs.candidates', 3)
        );
});

// ── المرحلة الثالثة: القالبُ عند إنشاء الصور ──────────────────────────

it('ينشئ الصور بالقالب المختار، وبافتراضيّ الجهة إن لم يُختر', function (): void {
    Storage::fake('local');
    config()->set('khulasah.images.disk', 'local');

    app()->instance(ShareCardCapturer::class, new class implements ShareCardCapturer
    {
        public function capture(string $html, int $width, int $height): ?string
        {
            // صورةٌ صحيحةٌ بنسبة المقاس، فتُقصّ لقطةُ الدفعة (T-197).
            $image = imagecreatetruecolor(4, max(1, intdiv($height, 270)));
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        }
    });

    generateFor($this);
    [$first, $second] = array_column(TenantCarouselDesigns::candidates($this->tenant->fresh()), 'design');
    TenantCarouselDesigns::approve($this->tenant->fresh(), $first->id);
    TenantCarouselDesigns::approve($this->tenant->fresh(), $second->id);

    $lecture = Lecture::factory()->create(['tenant_id' => $this->tenant->id]);
    $job = SummaryJob::factory()->for_($lecture)->inState(JobState::Published)->create(['structure_json' => ['title_ar' => 'عنوان الدرس']]);
    $deck = new SlideDeck([new Slide(1, SlideKind::Cover, 'عنوان الدرس', 'سطر.'), new Slide(2, SlideKind::Closing, 'خاتمة', 'سطر.')]);

    Output::query()->create([
        'summary_job_id' => $job->id, 'tenant_id' => $this->tenant->id, 'type' => OutputType::Carousel->value,
        'locale' => Locale::Ar->value, 'format' => OutputType::Carousel->format()->value, 'rendered_at' => now(),
        'renderer_version' => '1.2.0', 'meta' => ['slides' => $deck->toArray()],
    ]);

    $design = fn (): ?string => Output::query()->where('summary_job_id', $job->id)
        ->where('type', OutputType::ImageSet->value)->first()?->meta['design'];

    $this->actingAs($this->owner->fresh())->post("/panel/jobs/{$job->id}/images");
    expect($design())->toBe($first->id);

    $this->actingAs($this->owner->fresh())->post("/panel/jobs/{$job->id}/images", ['design' => $second->id]);
    expect($design())->toBe($second->id);

    $this->actingAs($this->owner->fresh())->post("/panel/jobs/{$job->id}/images", ['design' => 'default']);
    expect($design())->toBe(CarouselDesign::DEFAULT_ID);

    $this->actingAs($this->owner->fresh())->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('outputs.images.designs', 2)
            ->where('outputs.images.designs.0.id', $first->id)
        );
});
