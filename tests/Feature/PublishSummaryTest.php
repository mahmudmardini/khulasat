<?php

declare(strict_types=1);

use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\PublishTenantIndex;
use App\Actions\Publish\UnpublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Publish\Paths;
use App\Support\Publish\Slug;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Storage;

/*
 * النشر إلى التخزين والـ CDN — T-15، والمواصفة §9.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', 'https://cdn.khulasah.test');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'name_ar' => 'جهة الاختبار']);
    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
    ]);

    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => $this->lecture->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);

    app(RenderOutput::class)->handle($this->job, app(PageRenderer::class));
});

function publishJob(SummaryJob $job): array
{
    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->refresh()), BrandKit::forTenant($job->tenant))
        ->contents;

    return app(PublishSummary::class)->handle($job->refresh(), ['page' => $html]);
}

/*
 * ─── الـ slug ────────────────────────────────────────────────────────
 */

it('يشتقّ slug لاتينياً بشرطات من عنوانٍ عربي', function (string $title, string $expected): void {
    expect(Slug::make($title))->toBe($expected);
})->with([
    // **المشكول يُخرج مثال المواصفة §9 حرفاً بحرف.**
    ['الدُّرُوسُ', 'al-durus'],
    ['في الدرس', 'fi-al-drs'],
    ['Hello World', 'hello-world'],
]);

/** عنوانٌ لا حرف فيه قابلاً للنقحرة لا يُخرج رابطاً فارغاً. */
it('لا يُخرج slug فارغاً', function (): void {
    expect(Slug::make('١٢٣'))->toBe('summary')
        ->and(Slug::make('؟؟؟'))->toBe('summary');
});

/** **التصادم بلاحقة رقمية** — §9. */
it('يفضّ التصادم بلاحقة رقمية', function (): void {
    $taken = ['anuan-al-drs', 'anuan-al-drs-2'];

    expect(Slug::unique('عنوان الدرس', fn (string $s): bool => in_array($s, $taken, true)))
        ->toBe('anuan-al-drs-3');
});

it('يفضّ التصادم داخل الجهة لا عبر الجهات', function (): void {
    $other = Tenant::factory()->create(['slug' => 'other']);
    SummaryJob::factory()->create([
        'tenant_id' => $other->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $other->id])->id,
        'slug' => 'anuan-al-drs',
    ]);

    publishJob($this->job);

    // جامعان يلقيان درساً بالعنوان نفسه — ولا يزاحم أحدهما الآخر.
    expect($this->job->refresh()->slug)->toBe('anuan-al-drs');
});

/*
 * ─── الرفع والمسارات ─────────────────────────────────────────────────
 */

it('يرفع الصفحة إلى مسار الجهة ويعيد رابط الـ CDN', function (): void {
    $urls = publishJob($this->job);

    Storage::disk('public')->assertExists('tenant-a/anuan-al-drs/index.html');

    expect($urls['page'])->toStartWith('https://cdn.khulasah.test/');
});

/**
 * **بلا CDN — رابطٌ نظيف عبر التطبيق نفسه، لا رابط قرصٍ خام** — T-127.
 * `disk()->url()` كان يخرج `/storage/tenant-a/…/index.html`: مسارَ تخزينٍ
 * لا رابطاً يُشارَك.
 */
it('يبني رابطاً نظيفاً بلا CDN', function (): void {
    config()->set('khulasah.publish.cdn_url', '');
    config()->set('app.url', 'https://khulasat.io');

    $urls = publishJob($this->job);

    expect($urls['page'])->toBe('https://khulasat.io/tenant-a/anuan-al-drs')
        ->and($urls['page'])->not->toContain('/storage/')
        ->and($urls['page'])->not->toContain('/index.html');
});

it('يعطي كل نوع مخرَج مساره', function (): void {
    expect(Paths::forOutput('tenant-a', 'slug', OutputType::Page))->toBe('tenant-a/slug/index.html')
        ->and(Paths::forOutput('tenant-a', 'slug', OutputType::Carousel))->toBe('tenant-a/slug/carousel/index.html');
});

/** **حزمة الصور تُنزَّل ولا تُنشر** — §9. */
it('لا ينشر حزمة الصور', function (): void {
    expect(OutputType::ImageSet->isPublished())->toBeFalse()
        ->and(OutputType::Page->isPublished())->toBeTrue();
});

it('يقيّد مسار المخرَج ورابطه في outputs', function (): void {
    publishJob($this->job);

    $output = $this->job->refresh()->outputs()->first();

    expect($output->storage_path)->toBe('tenant-a/anuan-al-drs/index.html')
        ->and($output->public_url)->toContain('cdn.khulasah.test');
});

/*
 * ─── إعادة النشر ─────────────────────────────────────────────────────
 */

/** **الرابط لا يتبدّل بتصحيحٍ في العنوان** — شاركه الناس. */
it('يثبّت الـ slug فلا يتبدّل عند إعادة النشر', function (): void {
    publishJob($this->job);
    $first = $this->job->refresh()->slug;

    $this->job->forceFill(['structure_json' => ['title_ar' => 'عنوان آخر تماماً']])->save();

    // و`published` حالةٌ نهائية بحقّ، فإعادة النشر لا تُعيد الانتقال إليها.
    publishJobAgain($this->job->refresh());

    expect($this->job->refresh()->slug)->toBe($first);
});

function publishJobAgain(SummaryJob $job): void
{
    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job), BrandKit::forTenant($job->tenant))
        ->contents;

    app(PublishSummary::class)->handle($job, ['page' => $html]);
}

it('يكتب فوق الملفّ نفسه ولا يُنشئ ثانياً', function (): void {
    publishJob($this->job);
    publishJobAgain($this->job->refresh());

    expect(Storage::disk('public')->files('tenant-a/anuan-al-drs'))->toHaveCount(1);
});

/*
 * ─── الحذف — 410 لا 404 ──────────────────────────────────────────────
 */

/**
 * **410 لا 404.**
 *
 * والتخزين الساكن لا يردّ رمز حالةٍ من عنده، فالشاهدة نصٌّ يقول «كان هنا
 * وأُزيل». و404 تقول «لم يكن»، وهي كذبٌ على من رأى الصفحة بعينه.
 */
it('يُبقي شاهدةً مكان المحذوف لا فراغاً', function (): void {
    publishJob($this->job);

    app(UnpublishSummary::class)->handle($this->job->refresh());

    $tombstone = Storage::disk('public')->get('tenant-a/anuan-al-drs/index.html');

    expect($tombstone)->toContain('أُزيل هذا الملخّص')
        ->and($tombstone)->toContain('كان هنا ملخّصٌ منشور')
        ->and($tombstone)->toContain('جهة الاختبار');
});

it('يمسح مسار المخرَج عند الحذف', function (): void {
    publishJob($this->job);
    app(UnpublishSummary::class)->handle($this->job->refresh());

    $output = $this->job->refresh()->outputs()->first();

    expect($output->storage_path)->toBeNull()
        ->and($this->job->refresh()->unpublished_at)->not->toBeNull();
});

/** وإعادة النشر تُلغي الشاهدة، وإلّا خُدمت 410 لصفحةٍ قائمة. */
it('يُلغي أثر الحذف عند إعادة النشر', function (): void {
    publishJob($this->job);
    app(UnpublishSummary::class)->handle($this->job->refresh());

    publishJobAgain($this->job->refresh());

    expect($this->job->refresh()->unpublished_at)->toBeNull()
        ->and($this->job->refresh()->published_at)->not->toBeNull();
});

/*
 * ─── الحدّ الرابع ────────────────────────────────────────────────────
 */

/** **لا نشر عند `needs_review`** — CLAUDE.md §2 القاعدة الرابعة. */
it('يمنع النشر وشاهدٌ لم يُحسم', function (): void {
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => 'معلّق',
        'normalized_text' => 'معلّق',
        'match_status' => 'partial',
        'review_status' => ReviewStatus::Pending->value,
    ])->save();

    expect(fn () => publishJob($this->job->refresh()))->toThrow(RuntimeException::class);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

/*
 * ─── الفهرس العامّ وembed.js ─────────────────────────────────────────
 */

it('ينشر فهرس الجهة بلا بيانات خاصّة', function (): void {
    publishJob($this->job);

    app(PublishTenantIndex::class)->handle($this->tenant);

    $index = json_decode(Storage::disk('public')->get('tenant-a/index.json'), true);

    expect($index['tenant'])->toBe('tenant-a')
        ->and($index['summaries'])->toHaveCount(1)
        ->and($index['summaries'][0]['slug'])->toBe('anuan-al-drs');

    // **ولا حالة، ولا كلفة، ولا نصّ تفريغ.**
    $flat = json_encode($index, JSON_UNESCAPED_UNICODE);

    foreach (['cost', 'transcript', 'state', 'evidence'] as $leak) {
        expect($flat)->not->toContain($leak);
    }
});

it('لا يُدرج المحذوف في الفهرس', function (): void {
    publishJob($this->job);
    app(UnpublishSummary::class)->handle($this->job->refresh());

    app(PublishTenantIndex::class)->handle($this->tenant);

    $index = json_decode(Storage::disk('public')->get('tenant-a/index.json'), true);

    expect($index['summaries'])->toBe([]);
});

/** **حجمه تحت 10KB** — معيار القبول الأخير. */
it('يبقي embed.js تحت عشرة كيلوبايت', function (): void {
    $size = filesize(public_path('embed.js'));

    expect($size)->toBeLessThan(10240)->toBeGreaterThan(500);
});

/** **ولا يحقن HTML من الشبكة**: وسمٌ في عنوان ملخّص لا يصير عنصراً. */
it('لا يستعمل innerHTML في embed.js', function (): void {
    $source = (string) file_get_contents(public_path('embed.js'));

    // الإسناد لا ذكرُ الاسم: تعليقٌ يشرح لماذا لا نستعمله ليس استعمالاً.
    expect($source)->not->toMatch('/\\.(inner|outer)HTML\\s*=/')
        ->and($source)->not->toContain('document.write')
        ->and($source)->toContain('textContent');
});
