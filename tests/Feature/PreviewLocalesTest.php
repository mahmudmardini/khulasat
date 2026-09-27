<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * المعاينة بلغات النشر — T-84.
 *
 * ★ **وُجد في T-82:** المعاينة كانت ترسم العربية وحدها ولو كانت المهمّة
 * تنشر الإنجليزية والتركية — فيدفع صاحب الجهة ثمن ترجمةٍ لا يراها قبل
 * نشرها. **والمقياس هنا: ما يُعاين هو ما يُنشر بلغته.**
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'name_ar' => 'جهة الاختبار']);
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

/**
 * @param  list<string>  $locales  لغات النشر المختارة للدرس
 * @param  array<string, string>  $translations  متنُ كلّ لغةٍ مترجَمة
 */
function previewJob(array $locales, array $translations = []): SummaryJob
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    $job = SummaryJob::factory()->inState(JobState::Rendering)->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => Lecture::factory()->create([
            'tenant_id' => $tenant->id,
            'title_ar' => 'عنوان الدرس',
            'locales' => $locales,
        ])->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن الملخّص كما كُتب.</p>',
    ]);

    foreach ($translations as $locale => $body) {
        SummaryTranslation::query()->create([
            'summary_job_id' => $job->id,
            'tenant_id' => $tenant->id,
            'locale' => $locale,
            'title' => 'The Lost Treasure',
            'body_html' => $body,
        ]);
    }

    return $job;
}

it('previews each publish language in its own translation', function (): void {
    $job = previewJob(['ar', 'en'], ['en' => '<p class="lead">The body as translated.</p>']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page?locale=en")
        ->assertOk()
        ->assertSee('The body as translated.', escape: false)
        ->assertDontSee('متن الملخّص كما كُتب', escape: false);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page?locale=ar")
        ->assertOk()
        ->assertSee('متن الملخّص كما كُتب', escape: false);
});

it('opens on the first publish language, not always on Arabic', function (): void {
    // ملخّصٌ إنجليزيٌّ وحده — T-51: العربية لغةُ المصدر لا لغةُ نشرٍ مفروضة.
    $job = previewJob(['en'], ['en' => '<p class="lead">The body as translated.</p>']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page")
        ->assertOk()
        ->assertSee('The body as translated.', escape: false);
});

it('refuses a language the job does not publish', function (string $locale): void {
    $job = previewJob(['ar', 'en'], ['en' => '<p>x</p>']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview/page?locale={$locale}")->assertNotFound();
    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/page?locale={$locale}")->assertNotFound();
})->with(['not chosen' => 'ru', 'unknown' => 'zz']);

it('tells which publish languages are translated yet', function (): void {
    // ★ لغةٌ لم تُترجَم بعدُ تُعلَّم لا تُخفى — والعارضُ يسقط فيها إلى العربية.
    $job = previewJob(['ar', 'en', 'tr'], ['en' => '<p>x</p>']);

    $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/preview")
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('primary_locale', 'ar')
            ->where('locales', function (Collection $locales): bool {
                return $locales->pluck('key')->all() === ['ar', 'en', 'tr']
                    && $locales->pluck('translated')->all() === [true, true, false]
                    && $locales->pluck('direction')->all() === ['rtl', 'ltr', 'ltr'];
            })
        );
});

it('downloads the chosen language as its own file', function (): void {
    $job = previewJob(['ar', 'en'], ['en' => '<p class="lead">The body as translated.</p>']);

    $response = $this->actingAs($this->user)->get("/panel/jobs/{$job->id}/download/page?locale=en")->assertOk();

    expect($response->streamedContent())->toContain('The body as translated.')
        ->and((string) $response->headers->get('Content-Disposition'))->toContain('-en.html');
});
