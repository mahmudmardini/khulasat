<?php

declare(strict_types=1);

use App\Actions\Render\RenderImageSet;
use App\Contracts\OverflowProbe;
use App\Contracts\ShareCardCapturer;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\SlideKind;
use App\Jobs\GenerateImageSet;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Render\CarouselRenderer;
use App\Services\ShareCard\NullShareCardCapturer;
use App\Support\Quran\AyahText;
use App\Support\Render\BrandKit;
use App\Support\Render\CarouselDesign;
use App\Support\Render\ContentObject;
use App\Support\Render\ImageSet;
use App\Support\Render\Palette;
use App\Support\Render\Slide;
use App\Support\Render\SlideDeck;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * حزمة صور الكاروسيل — T-173، وتستوعب T-20.
 *
 * والمتصفّحُ هنا ملتقِطٌ وهميّ يحفظ ما يصله ويردّ صورةً صغيرة: ما يُختبر هو
 * ما يُرسل إليه وما يُحفظ منه، لا Chrome نفسه.
 */

/**
 * صورةُ PNG صحيحة، **بنسبة المقاس المطلوب لا بمقاسه**: ١/٢٧٠ منه، فشريحةٌ
 * ٤×٥ ولقطةُ ثماني شرائح ٤×٤٠. فتُقصّ كما تُقصّ لقطةُ المتصفّح (T-197)، بلا
 * صورٍ بالميغابايتات في الاختبار.
 */
function tinyPng(int $width = 1080, int $height = 1350): string
{
    $image = imagecreatetruecolor(max(1, intdiv($width, 270)), max(1, intdiv($height, 270)));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/** كاروسيلٌ من عشر شرائح — دفعتان: ثمانٍ ثمّ اثنتان. */
function tenSlides(SummaryJob $job): void
{
    $slides = array_map(
        static fn (int $i): Slide => new Slide($i, SlideKind::Concept, "شريحة {$i}", 'نصٌّ حرّ.'),
        range(1, 10),
    );

    Output::query()->where('summary_job_id', $job->id)->where('type', OutputType::Carousel->value)
        ->update(['meta' => json_encode(['slides' => (new SlideDeck($slides))->toArray()], JSON_UNESCAPED_UNICODE)]);
}

/** لفظُ الآية مزيَّناً كما يحفظه المحقّق — وفيه «۝» يُسقطها نصّ المنشور. */
function decoratedAyah(): string
{
    return AyahText::decorate('مَنْ عَمِلَ صَالِحًا مِنْ ذَكَرٍ أَوْ أُنْثَىٰ وَهُوَ مُؤْمِنٌ', ['ayah_number' => 97]);
}

function imageDeck(): SlideDeck
{
    return new SlideDeck([
        new Slide(1, SlideKind::Cover, 'عنوان الدرس', 'سطرٌ تحت العنوان.'),
        new Slide(2, SlideKind::Ayah, 'الآية', decoratedAyah(), 'النحل · ٩٧', true),
        new Slide(3, SlideKind::Concept, 'مفهوم', 'نصٌّ حرّ.'),
        new Slide(4, SlideKind::Evidence, 'شاهد', 'إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ', 'البخاري · ١', true),
        new Slide(5, SlideKind::Closing, 'خاتمة', 'سطر الخاتمة.'),
    ]);
}

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('khulasah.images.disk', 'local');

    // إنشاءُ الصور يُعيد رسم كاروسيل الويب وينشره إن كان الملخّص منشوراً (T-204).
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', 'https://cdn.khulasah.test');

    $this->capturer = new class implements ShareCardCapturer
    {
        /** @var list<array{html: string, width: int, height: int}> */
        public array $calls = [];

        public ?int $failOn = null;

        public function capture(string $html, int $width, int $height): ?string
        {
            $this->calls[] = ['html' => $html, 'width' => $width, 'height' => $height];

            return $this->failOn === count($this->calls) ? null : tinyPng($width, $height);
        }
    };

    app()->instance(ShareCardCapturer::class, $this->capturer);

    $this->tenant = Tenant::factory()->create(['plan' => 'business', 'name_ar' => 'جهة الاختبار']);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    $lecture = Lecture::factory()->create(['tenant_id' => $this->tenant->id, 'title_ar' => 'عنوان الدرس']);

    $this->job = SummaryJob::factory()->for_($lecture)->inState(JobState::Published)->create([
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);

    $deck = imageDeck();

    Output::query()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'type' => OutputType::Carousel->value,
        'locale' => Locale::Ar->value,
        'format' => OutputType::Carousel->format()->value,
        'rendered_at' => now(),
        'renderer_version' => '1.2.0',
        'meta' => ['slides' => $deck->toArray(), 'plain_text' => $deck->toPlainText()],
    ]);
});

function imageSetRow(SummaryJob $job): ?Output
{
    return Output::query()
        ->where('summary_job_id', $job->id)
        ->where('type', OutputType::ImageSet->value)
        ->first();
}

it('ينشئ الحزمة صوراً مرقّمةً بمقاس إنستغرام ومعها نصّ المنشور', function (): void {
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images")->assertRedirect()->assertSessionHasNoErrors();

    $row = imageSetRow($this->job);
    $disk = Storage::disk('local');

    expect($row->meta['state'])->toBe('ready')
        ->and($row->meta['count'])->toBe(5)
        // ★ **لا `storage_path`**: ذاك يمسحه إلغاء النشر من مخزن النشر، والحزمة ليست هناك.
        ->and($row->storage_path)->toBeNull()
        ->and($row->public_url)->toBeNull();

    foreach (range(1, 5) as $position) {
        $disk->assertExists(ImageSet::slidePath($this->job, $position));
    }

    // **لقطةٌ واحدة للشرائح الخمس** (T-197): متراصّةً عموداً، ثمّ تُقصّ.
    expect($this->capturer->calls)->toHaveCount(1)
        ->and($this->capturer->calls[0]['width'])->toBe(1080)
        ->and($this->capturer->calls[0]['height'])->toBe(1350 * 5);

    $zipPath = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($zipPath, $disk->get(ImageSet::zipPath($this->job)));
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $names = array_map(fn (int $i): string => (string) $zip->getNameIndex($i), range(0, $zip->numFiles - 1));
    $caption = (string) $zip->getFromName('caption.txt');
    $zip->close();
    unlink($zipPath);

    // ورقمُ الآية في نصّ المنشور بلا «۝» — T-172.
    expect($names)->toBe(['01.png', '02.png', '03.png', '04.png', '05.png', 'caption.txt'])
        ->and($caption)->toContain('مُؤْمِنٌ ٩٧﴾')->not->toContain('۝');
});

// ★ المتصفّحُ الملتقِط يطلب شاهدة العدّ كما يطلبها القارئ، فتُعدّ كلُّ صورةٍ زيارة.
it('يلتقط الشرائحَ عموداً في وثيقةٍ واحدة، ولا يحمّلها شاهدةَ العدّ', function (): void {
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://views.test');

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $published = (new CarouselRenderer(app('view'), imageDeck()))
        ->render(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant))->contents;

    expect($published)->toContain('https://views.test/v/'.$this->job->id)
        ->and($this->capturer->calls)->toHaveCount(1);

    $html = $this->capturer->calls[0]['html'];

    expect($html)->not->toContain('views.test')
        ->and(substr_count($html, '<figure class="slide '))->toBe(5)
        ->and($html)->toContain(' capture strip">');
});

// معيار قبول: لفظُ كلّ شاهدٍ في الصورة يساوي `matched_text` حرفاً، أيّاً كانت المواصفة.
it('يُبقي لفظ الشاهد حرفاً في كلّ تخطيطٍ وكلّ سطح', function (string $ayahLayout, string $evidenceLayout, string $surface): void {
    $design = CarouselDesign::from([
        ...CarouselDesign::default()->toArray(),
        'id' => 'probe',
        'surface' => $surface,
        'layouts' => [...CarouselDesign::default()->toArray()['layouts'], 'ayah' => $ayahLayout, 'evidence' => $evidenceLayout],
    ]);

    $documents = (new CarouselRenderer(app('view'), imageDeck(), null, $design))
        ->slides(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant));

    expect($documents[1])->toContain(e(decoratedAyah()))->toContain('l-'.$ayahLayout)
        ->and($documents[3])->toContain(e('إِنَّمَا الْأَعْمَالُ بِالنِّيَّاتِ'))->toContain('l-'.$evidenceLayout);
})->with(fn (): array => collect(CarouselDesign::LAYOUTS['ayah'])
    ->crossJoin(CarouselDesign::LAYOUTS['evidence'], CarouselDesign::CATALOG['surface'])
    ->all());

// ★ **كلُّها أو لا شيء**: حزمةٌ ناقصةٌ تُنشر فيضيع ترتيب الكاروسيل.
it('يُسقط الحزمة كلَّها إن تعذّر التقاط دفعة، ويقول من أيّ شريحة', function (): void {
    tenSlides($this->job);
    $this->capturer->failOn = 2;

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $row = imageSetRow($this->job);

    expect($row->meta['state'])->toBe('failed')
        ->and($row->meta['error'])->toBe(trans('jobs.images.capture_failed', ['slide' => 9]));

    Storage::disk('local')->assertMissing(ImageSet::zipPath($this->job));
});

// ★ الشريحةُ تقصّ ما فاض صامتة، فيُقاس قبل الالتقاط — ولا التقاطَ إن فاض.
it('لا يلتقط شيئاً إن فاض نصُّ شريحة، ويقول أيّها، ولا يعدّ القياسَ زيارة', function (): void {
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://views.test');

    app()->instance(OverflowProbe::class, $probe = new class implements OverflowProbe
    {
        public string $html = '';

        public function overflowing(string $html): ?array
        {
            $this->html = $html;

            return [4];
        }
    });

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $row = imageSetRow($this->job);

    expect($row->meta['state'])->toBe('failed')
        ->and($row->meta['error'])->toBe(trans('jobs.images.overflow', ['slides' => '4']))
        ->and($this->capturer->calls)->toBe([])
        ->and(substr_count($probe->html, '<figure class="slide '))->toBe(5)
        ->and($probe->html)->not->toContain('views.test');

    Storage::disk('local')->assertMissing(ImageSet::zipPath($this->job));
});

it('لا يُنشئ صوراً وفي الملخّص شاهدٌ لم يُحسم', function (): void {
    EvidenceItem::factory()->for_($this->job)->create(['review_status' => ReviewStatus::Pending]);

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images")
        ->assertSessionHasErrors(['images' => trans('jobs.images.pending', ['count' => 1])]);

    expect(imageSetRow($this->job))->toBeNull()
        ->and($this->capturer->calls)->toBe([]);
});

it('لا يُنشئ صوراً بلا شرائح', function (): void {
    Output::query()->where('summary_job_id', $this->job->id)->delete();

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images")
        ->assertSessionHasErrors(['images' => trans('jobs.images.no_carousel')]);

    expect(imageSetRow($this->job))->toBeNull();
});

it('لا يُنشئ صوراً لجهةٍ على شريحةٍ بلا مخرجات غنية', function (): void {
    $this->tenant->forceFill(['plan' => 'free'])->save();

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images")->assertSessionHasErrors('images');

    expect(imageSetRow($this->job))->toBeNull();
});

it('يقول إنّ الالتقاط مطفأ ولا يَعِد بصور', function (): void {
    app()->instance(ShareCardCapturer::class, new NullShareCardCapturer);

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images")
        ->assertSessionHasErrors(['images' => trans('jobs.images.disabled')]);

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page->where('outputs.images.enabled', false));
});

it('يعرض الصور في المعاينة وينزّل الحزمة', function (): void {
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.images.produced', true)
            ->where('outputs.images.state', 'ready')
            ->has('outputs.images.urls', 5)
            ->where('outputs.images.urls.0', fn (string $url): bool => str_starts_with($url, "/panel/jobs/{$this->job->id}/images/1.png?v="))
        );

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/images/2.png")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    $download = $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/download/image_set")->assertOk();

    expect($download->headers->get('Content-Type'))->toBe('application/zip')
        ->and($download->headers->get('Content-Disposition'))->toContain('-images.zip');
});

it('لا تُرى صورُ جهةٍ ولا تُنشأ من جهةٍ أخرى', function (): void {
    // قبل أيّ طلب: الطلبُ يضبط سياق جهته، والكتابةُ لجهةٍ أخرى بعده تُرفض.
    $other = User::factory()->owner()->for_(Tenant::factory()->create(['plan' => 'business']))->create();

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $this->actingAs($other)->get("/panel/jobs/{$this->job->id}/images/1.png")->assertNotFound();
    $this->actingAs($other)->get("/panel/jobs/{$this->job->id}/download/image_set")->assertNotFound();
    $this->actingAs($other)->post("/panel/jobs/{$this->job->id}/images")->assertNotFound();
});

// ★ شعارٌ داكنٌ على الأولى والأخيرة الداكنتين يختفي، فيُرسم على لوح — إلّا لمن
// أعلن أنّ شعاره فاتحٌ أصلاً (T-125)، كما في رأس صفحة الملخّص.
it('يرسم الشعار على لوحٍ في الخلفية الداكنة، ويرفعه لشعارٍ فاتحٍ أصلاً', function (bool $transparent, string $expected): void {
    $brand = new BrandKit('اسم الجهة', 'اسم الجهة', 'Venue', Palette::find(null), 'data:image/png;base64,iVBORw0KGgo=', logoTransparent: $transparent);

    $html = (new CarouselRenderer(app('view'), imageDeck()))->render(ContentObject::fromJob($this->job), $brand)->contents;

    expect($html)->toContain('class="deck surface-paper bookends-deep')
        ->and(substr_count($html, $expected))->toBe(2);
})->with([
    'بلوح' => [false, '<img class="logo" '],
    'بلا لوح' => [true, '<img class="logo no-plate" '],
]);

// ★ T-196 — معاينةُ اللوحة مصغَّرةٌ لتُرى في إطارها، وبلا شاهدة عدّ: كان فتحُها
// من اللوحة يُعدّ قراءة. والالتقاطُ بمقاسه، فلا يمسّه التصغير.
it('يصغّر معاينة اللوحة ولا يعدّها قراءة، ولا يمسّ الالتقاط', function (): void {
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://views.test');

    // معاينةُ اللوحة اليوم معاينةُ القوالب (T-204 حذف صفحة الشرائح)، وكلتاهما منه.
    $preview = (new CarouselRenderer(app('view'), imageDeck()))
        ->preview(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant));

    expect($preview)->toContain(' fit">')->not->toContain('views.test');

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    foreach ($this->capturer->calls as $call) {
        expect($call['html'])->not->toContain(' fit">');
    }
});

// ── T-197: دفعاتٌ وتقدّمٌ معلوم، وجاريةٌ لا تعلق ─────────────────────────

it('يلتقط عشر شرائح بدفعتين ويقصّها بمقاسها، ويكتب التقدّم بعد كلّ دفعة', function (): void {
    tenSlides($this->job);

    $progress = [];
    $capturer = new class($progress) implements ShareCardCapturer
    {
        public array $heights = [];

        public function __construct(private array &$progress) {}

        public function capture(string $html, int $width, int $height): ?string
        {
            $this->heights[] = $height;
            $this->progress[] = Output::query()->where('type', OutputType::ImageSet->value)->first()?->meta['progress'];

            return tinyPng($width, $height);
        }
    };
    app()->instance(ShareCardCapturer::class, $capturer);

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $row = imageSetRow($this->job);
    $disk = Storage::disk('local');

    expect($capturer->heights)->toBe([1350 * 8, 1350 * 2])
        ->and($progress)->toBe([['done' => 0, 'total' => 10], ['done' => 8, 'total' => 10]])
        ->and($row->meta['count'])->toBe(10)
        ->and($row->meta['progress'])->toBe(['done' => 10, 'total' => 10]);

    foreach (range(1, 10) as $position) {
        // كلُّ صورةٍ بمقاس شريحةٍ واحدة: ١/٢٧٠ من ١٠٨٠×١٣٥٠ في الاختبار.
        expect(getimagesizefromstring($disk->get(ImageSet::slidePath($this->job, $position)))[1])->toBe(5);
    }
});

it('يُسقط لقطةً لا ينقسم ارتفاعُها على شرائحها، فلا يقصّ من منتصف شريحة', function (): void {
    app()->instance(ShareCardCapturer::class, new class implements ShareCardCapturer
    {
        public function capture(string $html, int $width, int $height): ?string
        {
            return tinyPng($width, $height + 270);
        }
    });

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    expect(imageSetRow($this->job)->meta['error'])->toBe(trans('jobs.images.capture_failed', ['slide' => 1]));
});

it('يُبقي الصور السابقة معروضةً أثناء الإنشاء، ويعرض جاريةً طال أمدُها متعثّرة', function (): void {
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    RenderImageSet::mark($this->job, 'rendering', null, ['progress' => ['done' => 0, 'total' => 5]]);

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.images.state', 'rendering')
            ->where('outputs.images.progress', ['done' => 0, 'total' => 5])
            ->where('outputs.images.produced', true)
            ->has('outputs.images.urls', 5)
        );

    $this->travel(ImageSet::STALL_MINUTES + 1)->minutes();

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.images.state', 'failed')
            ->where('outputs.images.error', trans('jobs.images.stalled'))
            ->where('outputs.images.progress', null)
            ->has('outputs.images.urls', 5)
        );
});

it('يعرض التقدّم من أوّل لحظة، قبل أن يبلغ العاملُ المهمّة', function (): void {
    Queue::fake();

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    Queue::assertPushed(GenerateImageSet::class);

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.images.state', 'rendering')
            ->where('outputs.images.progress', ['done' => 0, 'total' => 5])
        );
});

// ── T-204: قالبٌ واحد لشرائح الملخّص ─────────────────────────────────

/** يعتمد للجهة قالباً ليلياً، ويعيده. */
function approveNight(Tenant $tenant): CarouselDesign
{
    $night = CarouselDesign::from([...CarouselDesign::default()->toArray(), 'id' => 'night', 'name' => 'ليلي', 'surface' => 'night']);
    $tenant->forceFill(['brand_kit' => ['carousel_designs' => [$night->toArray()]]])->save();

    return $night;
}

it('يجعل القالب المختار قالبَ شرائح الملخّص: يُرسم به المنشور والصور معاً', function (): void {
    approveNight($this->tenant);

    // الجهةُ تختار الأصل للصور، وافتراضيُّها الليليّ: فيتبعه كلُّ شيء.
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images", ['design' => 'default']);

    $job = $this->job->fresh();
    $carousel = $job->outputs()->where('type', OutputType::Carousel->value)->first();

    expect(CarouselDesign::forJob($job)->id)->toBe(CarouselDesign::DEFAULT_ID)
        ->and($carousel->meta['design']['id'])->toBe(CarouselDesign::DEFAULT_ID)
        ->and(imageSetRow($job)->meta['design'])->toBe(CarouselDesign::DEFAULT_ID);

    // **والمنشورُ يُحدَّث بالقالب نفسه**: الملخّصُ منشور، فكاروسيلُه على الويب.
    $published = collect(Storage::disk('public')->allFiles())->first(fn (string $path): bool => str_ends_with($path, 'carousel/index.html'));

    expect(Storage::disk('public')->get($published))->toContain('class="deck surface-paper')->not->toContain('class="deck surface-night');

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images", ['design' => 'night']);

    $published = Storage::disk('public')->get($published);

    expect(CarouselDesign::forJob($this->job->fresh())->id)->toBe('night')
        ->and($published)->toContain('class="deck surface-night')
        ->and(imageSetRow($this->job)->meta['design'])->toBe('night');

    $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page->where('outputs.images.design', 'night'));
});

it('يقول إنّ الصور قديمةٌ إذا تغيّرت الشرائح بعدها، ويزول ذلك بإعادة إنشائها', function (): void {
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $stale = fn (): bool => $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/preview")
        ->viewData('page')['props']['outputs']['images']['stale'];

    expect($stale())->toBeFalse();

    // إعادةُ رسم الشرائح بعد الصور (صياغةٌ أو قالبٌ أو هويةٌ تغيّرت).
    $this->travel(2)->seconds();
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/carousel/build", ['recondense' => false]);

    expect($stale())->toBeTrue();

    $this->travel(2)->seconds();
    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    expect($stale())->toBeFalse();
});
