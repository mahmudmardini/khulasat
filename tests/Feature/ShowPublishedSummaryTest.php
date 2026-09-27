<?php

declare(strict_types=1);

use App\Actions\Publish\DeleteSummary;
use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\UnpublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Storage;

/*
 * الرابط المنشور النظيف — T-127. `Paths::publicUrl` (T-15) يحسب شكل
 * الرابط النهائيّ، لكنّ الخدمة الفعلية بلا CDN تمرّ من هنا.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', '');

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

function publishForShow(SummaryJob $job): void
{
    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->refresh()), BrandKit::forTenant($job->tenant))
        ->contents;

    app(PublishSummary::class)->handle($job->refresh(), ['page' => $html]);
}

it('يخدم الصفحة المنشورة على رابطٍ نظيف', function (): void {
    publishForShow($this->job);

    $this->get('/tenant-a/anuan-al-drs')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

it('يردّ 404 لجهةٍ لا وجود لها', function (): void {
    $this->get('/no-such-tenant/whatever')->assertNotFound();
});

it('يردّ 404 لملخّصٍ لا وجود له عند جهةٍ قائمة', function (): void {
    $this->get('/tenant-a/no-such-summary')->assertNotFound();
});

it('يردّ 404 لمهمّةٍ لم تُنشر بعد', function (): void {
    $this->job->forceFill(['slug' => 'still-drafting'])->save();

    $this->get('/tenant-a/still-drafting')->assertNotFound();
});

/** **410 حقيقيّ لا نصّاً وحده** — العرض هنا يمرّ على Laravel فعلاً. */
it('يردّ 410 حقيقياً على الجذر بعد إلغاء النشر', function (): void {
    publishForShow($this->job);
    app(UnpublishSummary::class)->handle($this->job->refresh());

    $response = $this->get('/tenant-a/anuan-al-drs');

    $response->assertStatus(410);
    expect($response->getContent())->toContain('أُزيل هذا الملخّص');
});

/** والشاهدة كُتبت قبل حذف الصفّ، فلا تُفقد بفقدانه — DeleteSummary. */
it('يردّ 410 على الجذر بعد الحذف الكلّي', function (): void {
    publishForShow($this->job);
    app(DeleteSummary::class)->handle($this->job->refresh());

    $this->get('/tenant-a/anuan-al-drs')->assertStatus(410);
});

it('يردّ 404 للكاروسيل بعد إلغاء النشر لا شاهدة', function (): void {
    publishForShow($this->job);
    app(UnpublishSummary::class)->handle($this->job->refresh());

    $this->get('/tenant-a/anuan-al-drs/carousel')->assertNotFound();
});

it('يردّ 404 على شكلٍ غير صالح من الرابط', function (): void {
    publishForShow($this->job);

    $this->get('/tenant-a/anuan-al-drs/xx')->assertNotFound();
    $this->get('/tenant-a/anuan-al-drs/ar/xx')->assertNotFound();
});
