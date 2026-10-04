<?php

declare(strict_types=1);

use App\Contracts\OverflowProbe;
use App\Contracts\ShareCardCapturer;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\SlideKind;
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
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * حزمة صور الكاروسيل — T-173، وتستوعب T-20.
 *
 * والمتصفّحُ هنا ملتقِطٌ وهميّ يحفظ ما يصله ويردّ صورةً صغيرة: ما يُختبر هو
 * ما يُرسل إليه وما يُحفظ منه، لا Chrome نفسه.
 */

/** صورةُ PNG صحيحةٌ صغيرة — يكفي أن تُحفظ كما هي. */
function tinyPng(): string
{
    $image = imagecreatetruecolor(4, 5);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
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

    $this->capturer = new class implements ShareCardCapturer
    {
        /** @var list<array{html: string, width: int, height: int}> */
        public array $calls = [];

        public ?int $failOn = null;

        public function capture(string $html, int $width, int $height): ?string
        {
            $this->calls[] = ['html' => $html, 'width' => $width, 'height' => $height];

            return $this->failOn === count($this->calls) ? null : tinyPng();
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

    expect(collect($this->capturer->calls)->every(
        fn (array $call): bool => $call['width'] === 1080 && $call['height'] === 1350,
    ))->toBeTrue();

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
it('يلتقط كلَّ شريحةٍ في وثيقتها، ولا يحمّلها شاهدةَ العدّ', function (): void {
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://views.test');

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $published = (new CarouselRenderer(app('view'), imageDeck()))
        ->render(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant))->contents;

    expect($published)->toContain('https://views.test/v/'.$this->job->id)
        ->and($this->capturer->calls)->toHaveCount(5);

    foreach ($this->capturer->calls as $call) {
        expect($call['html'])->not->toContain('views.test')
            ->and(substr_count($call['html'], '<figure class="slide '))->toBe(1)
            ->and($call['html'])->toContain(' capture">');
    }
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
it('يُسقط الحزمة كلَّها إن تعذّر التقاط شريحة، ويقول أيّها', function (): void {
    $this->capturer->failOn = 3;

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    $row = imageSetRow($this->job);

    expect($row->meta['state'])->toBe('failed')
        ->and($row->meta['error'])->toBe(trans('jobs.images.capture_failed', ['slide' => 3]));

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
it('يصغّر معاينة صفحة الشرائح ولا يعدّها قراءة، ولا يمسّ الالتقاط', function (): void {
    config()->set('khulasah.analytics.enabled', true);
    config()->set('khulasah.analytics.beacon_base', 'https://views.test');

    $preview = $this->actingAs($this->user)->get("/panel/jobs/{$this->job->id}/carousel/preview")->assertOk()->getContent();

    expect($preview)->toContain(' fit">')->not->toContain('views.test');

    $this->actingAs($this->user)->post("/panel/jobs/{$this->job->id}/images");

    foreach ($this->capturer->calls as $call) {
        expect($call['html'])->not->toContain(' fit">');
    }
});
