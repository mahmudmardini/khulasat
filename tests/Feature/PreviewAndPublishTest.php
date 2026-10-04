<?php

declare(strict_types=1);

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\MatchStatus;
use App\Enums\OutputFormat;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\UsageEvent;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Publish\Paths;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * شاشة المعاينة والنشر — SCREENS.md §6 و§7، والمهمّة T-30.
 *
 * ★ **والمقياس الحاكم هنا اثنان:**
 *
 * **١. المعاينة قراءةٌ لا تكتب.** فهي تُفتح بـ`GET` وتُعاد بكلّ تحديثِ
 * صفحة وبكلّ رجوعٍ من التاريخ. ولو كتبت لصار فتحُ الشاشة نشراً لم يطلبه
 * أحد — وهي العلّة بعينها التي وقعت في معاينة الكاروسيل (T-19).
 *
 * **٢. إلغاء النشر يترك ٤١٠ لا ٤٠٤** — المواصفة §9. و404 تقول «لم يكن هنا
 * شيء»، وهي كذبةٌ على من شارك الرابط ونسخه في مجموعته.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', 'https://cdn.khulasah.test');

    Queue::fake();

    $this->tenant = Tenant::factory()->create([
        'slug' => 'tenant-a',
        'name_ar' => 'جهة الاختبار',
        'monthly_quota' => 20,
        'regenerations_per_summary' => 2,
    ]);

    $this->user = User::factory()->owner()->for_($this->tenant)->create();

    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
    ]);
});

/** مهمّة بلغت `rendering` بالمسار الشرعي، فتصلح للنشر. */
function readyJob(array $attributes = []): SummaryJob
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    $job = SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => test()->lecture->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن الملخّص كما كُتب.</p>',
        ...$attributes,
    ]);

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job->refresh();
}

/** وتُنشر فعلاً، فتصير حالُها ما يراه صاحبها بعد اكتمال الخطّ. */
function livedJob(): SummaryJob
{
    $job = readyJob();

    test()->actingAs(test()->user)->post("/panel/summaries/{$job->id}")->assertRedirect();

    return $job->refresh();
}

// ── الحاجز والعزل ────────────────────────────────────────────────

it('يردّ الزائر إلى الدخول ولا يعرض معاينة ولا ملخّصاً منشوراً', function (string $path): void {
    $job = readyJob();

    test()->get(str_replace('{id}', (string) $job->id, $path))->assertRedirect('/panel/login');
})->with([
    '/panel/jobs/{id}/preview',
    '/panel/jobs/{id}/preview/page',
    '/panel/jobs/{id}/download/page',
    '/panel/summaries/{id}',
]);

it('لا يُري جهةً معاينةَ ملخّصِ جهةٍ أخرى', function (): void {
    $job = readyJob();

    $stranger = User::factory()->owner()->for_(Tenant::factory()->create())->create();

    $this->actingAs($stranger)->get("/panel/jobs/{$job->id}/preview")->assertNotFound();
});

// ── ١. المعاينة: قراءةٌ لا تكتب ──────────────────────────────────

it('يعرض المعاينة بتبويبات المخرجات', function (): void {
    $job = readyJob();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Jobs/Preview')
            ->where('job.id', $job->id)
            ->where('job.renderable', true)
            ->where('outputs.page.produced', true)
            ->where('outputs.carousel.produced', false)
            // حزمة الصور عارضٌ مؤجَّل (T-20)، وتُعرض معطَّلةً لا مخفيّة.
            ->where('outputs.images.produced', false)
        );
});

it('يرسم الصفحة بالقالب الحقيقي في المعاينة', function (): void {
    $job = readyJob();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        // تُعرض في إطارٍ داخل اللوحة، ولا تُفتح من موقعٍ آخر.
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertSee('عنوان الدرس', escape: false)
        ->assertSee('متن الملخّص كما كُتب', escape: false);
});

/**
 * ★ **الاختبار الحاكم في هذا الملفّ.**
 *
 * والحالة المسجَّلة هي التي وقعت في T-19: معاينةٌ بـ`GET` تنادي فعل الرسم،
 * فتقيّد صفّاً وترفع ملفّاً **وتنشر** مع كلّ تحديثِ صفحة. فمن فتح المعاينة
 * لينظر قبل أن يقرّر، نشرَ وهو ينظر.
 */
it('لا يكتب صفّاً ولا يرفع ملفّاً حين تُفتح المعاينة', function (): void {
    $job = readyJob();

    $before = Output::query()->count();

    // ثلاث فتحات: تحديثُ صفحةٍ ورجوعٌ من التاريخ لا يُفرّق بينهما الخادم.
    foreach (range(1, 3) as $ignored) {
        $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")->assertOk();
        $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page")->assertOk();
    }

    expect(Output::query()->count())->toBe($before)
        ->and($job->refresh()->published_at)->toBeNull()
        ->and($job->state)->toBe(JobState::Rendering)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('يمنع المعاينة وفيها شاهدٌ لم يُحسم', function (): void {
    $job = readyJob();

    EvidenceItem::factory()->for_($job)->create([
        'match_status' => MatchStatus::None,
        'review_status' => ReviewStatus::Pending,
    ]);

    // **ولا يُرسَم ما لم يُحسم** — CLAUDE.md §2 القاعدة الرابعة. وملفٌّ
    // مرسوم من شواهد معلّقة جاهزٌ للنشر بضغطة، وهو أخطر من ألّا يُرسم.
    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page")->assertNotFound();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.renderable', false)
            ->where('job.pending_evidence', 1)
        );
});

it('يبيّن المتبقّي من إعادات التوليد قبل التنفيذ', function (): void {
    // «أعد التوليد وحده يُحتسب، **ويبيّن المتبقّي قبل التنفيذ**» — §6.
    $job = readyJob(['regeneration_count' => 1]);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('regenerations.used', 1)
            ->where('regenerations.limit', 2)
        );
});

// ── التنزيل ──────────────────────────────────────────────────────

it('ينزّل الصفحة ملفّاً قائماً بذاته', function (): void {
    $job = livedJob();

    $response = $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/page")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('attachment');

    // **قائمٌ بذاته** (§8): CSS داخليّ ولا اعتماد خارجي إلّا الخطوط.
    expect($response->streamedContent())->toContain('<style')->toContain('عنوان الدرس');
});

it('لا ينزّل شرائح لم تُبنَ ولا نوعاً لا يعرفه', function (): void {
    $job = readyJob();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/carousel")->assertNotFound();
    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/anything")->assertNotFound();
});

// T-172 — رقمُ الآية في النصّ المنسوخ بلا «۝»، ولو حُفظ المخرَج قبل القرار.
it('ينسخ رقم الآية بلا علامتها ويُبقيها في الشريحة', function (): void {
    $job = readyJob();
    $ayah = '﴿وَمَا خَلَقْتُ الْجِنَّ وَالْإِنْسَ إِلَّا لِيَعْبُدُونِ ۝٥٦﴾';

    // مخرَجٌ حُفظ قبل T-172: نصُّه المنسوخ يحمل العلامة.
    Output::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'type' => OutputType::Carousel->value,
        'locale' => Locale::Ar->value,
        'format' => OutputType::Carousel->format()->value,
        'storage_path' => 'tenant-a/slug/carousel.html',
        'rendered_at' => now(),
        'renderer_version' => '1.1.0',
        'meta' => [
            'slides' => [[
                'index' => 1, 'kind' => 'ayah', 'heading' => 'الآية المفتاح', 'body' => $ayah,
                'source_line' => 'الذاريات · ٥٦', 'anchored' => true,
            ]],
            'plain_text' => "١. الآية المفتاح\n{$ayah}\nالذاريات · ٥٦",
        ],
    ]);

    $copied = fn (string $text): bool => str_contains($text, 'لِيَعْبُدُونِ ٥٦﴾') && ! str_contains($text, '۝');

    $download = $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/carousel")->assertOk();
    expect($copied($download->streamedContent()))->toBeTrue();

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs.carousel.plain_text', $copied)
            // **والشريحةُ تبقى بعلامتها**: تُرسم بخطّ المصحف لا تُنسخ.
            ->where('outputs.carousel.slides.0.body', $ayah)
        );
});

// ── ٢. النشر ─────────────────────────────────────────────────────

it('ينشر من الشاشة فيرفع الملفّ ويعطي الرابط', function (): void {
    $job = readyJob();

    $this->actingAs($this->user)->post("/panel/summaries/{$job->id}")->assertRedirect();

    $job->refresh();

    expect($job->state)->toBe(JobState::Published)
        ->and($job->published_at)->not->toBeNull()
        ->and($job->slug)->not->toBeNull();

    $output = $job->outputs()->where('type', OutputType::Page->value)->first();

    expect($output->public_url)->toContain($job->slug)
        ->and(Storage::disk('public')->exists($output->storage_path))->toBeTrue();
});

it('لا يأذن بالنشر لمن لا يملك صلاحيته', function (): void {
    $job = readyJob();

    $viewer = User::factory()->viewer()->for_($this->tenant)->create();

    $this->actingAs($viewer)->post("/panel/summaries/{$job->id}")->assertForbidden();
    $this->actingAs($viewer)->delete("/panel/summaries/{$job->id}/publication")->assertForbidden();
    $this->actingAs($viewer)->delete("/panel/summaries/{$job->id}")->assertForbidden();

    expect($job->refresh()->published_at)->toBeNull();
});

it('يرفض النشر وفيه شاهدٌ لم يُحسم، برسالةٍ عربية', function (): void {
    $job = readyJob();

    EvidenceItem::factory()->for_($job)->create([
        'match_status' => MatchStatus::None,
        'review_status' => ReviewStatus::Pending,
    ]);

    // **لا نشر عند شاهدٍ معلَّق** — ولا إعداد يتجاوزه، ولا وضع تطوير.
    $this->actingAs($this->user)->post("/panel/summaries/{$job->id}")
        ->assertSessionHasErrors('publish');

    expect($job->refresh()->published_at)->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('يكتب فوق الملفّ نفسه عند التحديث ولا يبدّل الرابط', function (): void {
    $job = livedJob();

    $slug = $job->slug;
    $url = $job->outputs()->where('type', OutputType::Page->value)->value('public_url');

    $this->actingAs($this->user)->post("/panel/summaries/{$job->id}")->assertRedirect();

    // **`slug` يُشتقّ مرّةً ويثبت** (§9): الرابط شاركه الناس، وتبديلُه
    // لتصحيحٍ في العنوان يكسر كلّ إحالةٍ إليه.
    expect($job->refresh()->slug)->toBe($slug)
        ->and($job->outputs()->where('type', OutputType::Page->value)->value('public_url'))->toBe($url);
});

// ── ٣. إلغاء النشر ───────────────────────────────────────────────

it('يزيل النشر فيترك شاهدةً مكان الصفحة لا فراغاً', function (): void {
    $job = livedJob();

    $this->actingAs($this->user)->delete("/panel/summaries/{$job->id}/publication")->assertRedirect();

    $job->refresh();

    expect($job->unpublished_at)->not->toBeNull()
        ->and($job->published_at)->toBeNull()
        ->and($job->outputs()->where('type', OutputType::Page->value)->value('public_url'))->toBeNull();

    // **٤١٠ لا ٤٠٤** (§9): الشاهدة تحلّ محلّ الصفحة في موضعها نفسه.
    $tombstone = Paths::tombstone($this->tenant->slug, (string) $job->slug);

    expect(Storage::disk('public')->get($tombstone))->toContain('أُزيل هذا الملخّص');
});

it('يعيد النشر بعد الإلغاء على الرابط نفسه', function (): void {
    $job = livedJob();
    $slug = $job->slug;

    $this->actingAs($this->user)->delete("/panel/summaries/{$job->id}/publication")->assertRedirect();
    $this->actingAs($this->user)->post("/panel/summaries/{$job->id}")->assertRedirect();

    $job->refresh();

    expect($job->slug)->toBe($slug)
        ->and($job->unpublished_at)->toBeNull()
        ->and($job->published_at)->not->toBeNull();

    // والشاهدة زالت من موضعها، فلا تُخدَم ٤١٠ لصفحةٍ قائمة.
    expect(Storage::disk('public')->get(Paths::tombstone($this->tenant->slug, (string) $slug)))
        ->toContain('عنوان الدرس')
        ->not->toContain('أُزيل هذا الملخّص');
});

// ── ٤. الحذف النهائي ─────────────────────────────────────────────

it('يمحو الملخّص ومخرجاته ويُبقي الشاهدة مكان الصفحة', function (): void {
    $job = livedJob();
    $slug = (string) $job->slug;

    $this->actingAs($this->user)->delete("/panel/summaries/{$job->id}")
        ->assertRedirect('/panel');

    expect(SummaryJob::query()->whereKey($job->id)->exists())->toBeFalse()
        ->and(Output::query()->where('summary_job_id', $job->id)->exists())->toBeFalse();

    // **والشاهدة تُكتب قبل الحذف**: بعده يضيع `slug` فلا يُعرف أين تُكتب،
    // فيردّ الرابطُ الذي شاركه الناس ٤٠٤ — أي «لم يكن هنا شيء».
    expect(Storage::disk('public')->get(Paths::tombstone($this->tenant->slug, $slug)))
        ->toContain('أُزيل هذا الملخّص');
});

it('لا يستردّ الحصّة المصروفة بحذف الملخّص', function (): void {
    $job = livedJob();

    // وحدةُ حصّةٍ كالتي يكتبها إنشاءُ الملخّص — T-23.
    DB::table('usage_ledger')->insert([
        'tenant_id' => $this->tenant->id,
        'summary_job_id' => $job->id,
        'event' => UsageEvent::Generate->value,
        'units' => 1,
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)->delete("/panel/summaries/{$job->id}")->assertRedirect();

    // **مفتاحُه `nullOnDelete`، فالصفّ يبقى والمهمّة تذهب.** ولو سقط معها
    // لصار الحذف باباً إلى توليدٍ بلا حدّ.
    $row = DB::table('usage_ledger')->where('tenant_id', $this->tenant->id)->first();

    expect($row)->not->toBeNull()
        ->and((int) $row->units)->toBe(1)
        ->and($row->summary_job_id)->toBeNull();
});

it('يحذف الدرس مع آخر ملخّصاته ويُبقيه ما بقي له ملخّص', function (): void {
    $first = readyJob();
    $second = readyJob();

    $this->actingAs($this->user)->delete("/panel/summaries/{$first->id}")->assertRedirect();

    // «أعد المحاولة» تُنشئ مهمّةً ثانيةً للدرس نفسه، فحذفُ الدرس مع أولاهما
    // يمحو الثانيةَ القائمة معه.
    expect(Lecture::query()->whereKey($this->lecture->id)->exists())->toBeTrue()
        ->and(SummaryJob::query()->whereKey($second->id)->exists())->toBeTrue();

    $this->actingAs($this->user)->delete("/panel/summaries/{$second->id}")->assertRedirect();

    expect(Lecture::query()->whereKey($this->lecture->id)->exists())->toBeFalse();
});

// ── ٥. شاشة المنشور ──────────────────────────────────────────────

it('يعرض الرابط العلني ومخرجاته في شاشة المنشور', function (): void {
    $job = livedJob();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('Summaries/Show')
            ->where('job.published', true)
            ->where('outputs.0.type', 'page')
            ->where('outputs.0.produced', true)
            ->where('can_publish', true)
        );
});

it('لا يعرض في شاشة المنشور حزمة الصور، فهي تُنزَّل ولا تُنشر', function (): void {
    $job = livedJob();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs', fn (Collection $outputs): bool => $outputs
                ->pluck('type')
                ->doesntContain(OutputType::ImageSet->value))
        );
});

/** وعدّاد الفتحات يبدأ صفراً صادقاً لا مقدَّراً — T-31. */
it('يرسل عدّاد فتحاتٍ حقيقياً يبدأ من الصفر', function (): void {
    $job = livedJob();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('views.total', 0)
            ->where('views.recent', 0)
        );
});

it('يدلّ من لم ينشر بعدُ على المعاينة بدل صفحةٍ فارغة', function (): void {
    $job = readyJob();

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('job.published', false)
            // **ويُعرض الزرّ لأنّه يصلح للنشر** — لا يُخفى ولا يُعطَّل بلا سبب.
            ->where('job.publishable', true)
        );
});

/*
 * ═══ T-64 — روابطُ اللغات تحت المهمّة الواحدة ═══
 *
 * **بلاغُ مالك المنتج:** «لم أرَ محتوًى إنجليزياً» — والصفحةُ الإنجليزية
 * منشورةٌ فعلاً. والجذرُ أنّ `outputRow()` يقرأ `firstWhere('type', …)`
 * **بلا نظرٍ إلى اللغة**، فيعرض أوّلَ صفٍّ يجده أيّاً كانت لغتُه.
 *
 * فيدفع المستخدم ثمنَ لغةٍ ولا يعرف أين هي.
 */
it('يعرض صفحةَ كلّ لغةٍ على حدة تحت المهمّة الواحدة', function (): void {
    $job = livedJob();

    // مخرَجٌ ثانٍ بلغةٍ أخرى، كما ينتجه `secondaryLocales`.
    Output::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'type' => OutputType::Page->value,
        'locale' => Locale::En->value,
        'format' => OutputFormat::Html->value,
        'storage_path' => 'tenant-a/slug/en/index.html',
        'public_url' => 'https://cdn.khulasah.test/tenant-a/slug/en/index.html',
        'rendered_at' => now(),
        'renderer_version' => 1,
    ]);

    $this->actingAs($this->user)->get("/panel/summaries/{$job->id}")
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('outputs', function (Collection $outputs): bool {
                $pages = $outputs->where('type', OutputType::Page->value);

                // **صفحتان لا واحدة**، ولكلٍّ لغتُها ورابطُها.
                return $pages->count() === 2
                    && $pages->pluck('locale')->sort()->values()->all() === ['ar', 'en']
                    && $pages->firstWhere('locale', 'en')['public_url']
                        === 'https://cdn.khulasah.test/tenant-a/slug/en/index.html'
                    && $pages->every(fn (array $row): bool => $row['produced'] === true);
            })
        );
});
