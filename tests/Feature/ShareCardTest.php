<?php

declare(strict_types=1);

use App\Actions\Publish\DeleteSummary;
use App\Actions\Publish\GenerateShareCard;
use App\Actions\Publish\PublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Summary\TransitionJob;
use App\Contracts\ShareCardCapturer;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Jobs\GenerateShareCards;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Services\ShareCard\ChromeShareCardCapturer;
use App\Services\ShareCard\NullShareCardCapturer;
use App\Support\Publish\ShareCard;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * بطاقةُ مشاركة الخلاصة المنشورة — T-144.
 *
 * **والمحروسُ أوّلاً أنّ الخلاصةَ لا تُشارَك بلا صورة** في أيّ حال: مُلتقِطٌ
 * مُطفأ، أو ساقط، أو لم يبلغ دورَه. ثمّ أنّ المولَّدةَ تُخدَم مكانها متى
 * وُجدت، وأنّ ما سُحب لا يُعرض.
 */

final class FakeShareCardCapturer implements ShareCardCapturer
{
    public ?string $lastHtml = null;

    public function capture(string $html, int $width, int $height): ?string
    {
        $this->lastHtml = $html;

        return "\x89PNG-fake-{$width}x{$height}";
    }
}

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('local');
    config()->set('khulasah.publish.disk', 'public');
    config()->set('khulasah.publish.cdn_url', 'https://cdn.khulasah.test');
    config()->set('khulasah.share_card.disk', 'local');
    config()->set('app.url', 'https://khulasat.test');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-c', 'name_ar' => 'جهة البطاقة', 'name_latin' => 'Card Tenant']);
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
});

function shareCardPublish(SummaryJob $job): void
{
    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $output = app(RenderOutput::class)->handle($job->refresh(), app(PageRenderer::class));
    app(PublishSummary::class)->handle($job->refresh(), ['page:ar' => $output->contents]);
}

function shareCardHtml(SummaryJob $job): string
{
    return app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->refresh()), BrandKit::forTenant($job->tenant, $job->lecture))
        ->contents;
}

/*
 * ─── وسومُ الصفحة ─────────────────────────────────────────────────────
 */

it('gives every published summary page an absolute share image, its size and the site name', function (): void {
    shareCardPublish($this->job);

    $html = shareCardHtml($this->job);

    expect($html)
        ->toMatch('#<meta property="og:image" content="https://khulasat\.test/share/'.$this->job->id.'/ar\.png\?v=[0-9a-f]{12}">#')
        ->toContain('<meta property="og:image:width" content="1200">')
        ->toContain('<meta property="og:image:height" content="630">')
        ->toContain('<meta name="twitter:image" content="https://khulasat.test/share/'.$this->job->id.'/ar.png?v=')
        ->toContain('<meta property="og:site_name" content="خُلاصات">')
        ->toContain('<meta property="og:url" content="'.$this->job->outputs()->first()->public_url.'">');
});

it('points previews and downloads, which have no job number, at the platform card', function (): void {
    $content = ContentObject::fromJob($this->job)->withoutBeacon();
    $html = app(PageRenderer::class)->render($content, BrandKit::forTenant($this->tenant))->contents;

    expect($html)->toContain('<meta property="og:image" content="https://khulasat.test/landing/og-ar.png">');
});

it('changes the share image url when the title changes, so apps do not keep the old card', function (): void {
    shareCardPublish($this->job);
    preg_match('#share/\d+/ar\.png\?v=([0-9a-f]+)#', shareCardHtml($this->job), $before);

    $this->job->forceFill(['structure_json' => ['title_ar' => 'عنوانٌ آخر']])->saveQuietly();
    preg_match('#share/\d+/ar\.png\?v=([0-9a-f]+)#', shareCardHtml($this->job), $after);

    expect($after[1])->not->toBe($before[1]);
});

/*
 * ─── المسار ───────────────────────────────────────────────────────────
 */

it('serves the platform card for a published summary with no generated card', function (): void {
    shareCardPublish($this->job);

    $response = $this->get("/share/{$this->job->id}/ar.png")->assertOk()->assertHeader('Content-Type', 'image/png');

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toBe(file_get_contents(public_path('landing/og-ar.png')));
});

it('serves the generated card once it exists', function (): void {
    shareCardPublish($this->job);
    Storage::disk('local')->put(ShareCard::path($this->job->id, Locale::Ar), 'GENERATED-PNG');

    $this->get("/share/{$this->job->id}/ar.png")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($this->get("/share/{$this->job->id}/ar.png")->getContent())->toBe('GENERATED-PNG');
});

it('answers 404 for a summary that is not published, was unpublished, or does not exist', function (): void {
    $this->get("/share/{$this->job->id}/ar.png")->assertNotFound();

    shareCardPublish($this->job);
    Storage::disk('local')->put(ShareCard::path($this->job->id, Locale::Ar), 'GENERATED-PNG');
    $this->job->forceFill(['unpublished_at' => now()])->saveQuietly();

    $this->get("/share/{$this->job->id}/ar.png")->assertNotFound();
    $this->get('/share/999999/ar.png')->assertNotFound();
    $this->get("/share/{$this->job->id}/de.png")->assertNotFound();
});

/*
 * ─── التوليد ─────────────────────────────────────────────────────────
 */

it('draws the card with escaped content, no script, and stores it by job and language', function (): void {
    shareCardPublish($this->job);
    $this->job->forceFill(['structure_json' => ['title_ar' => '<script>alert(1)</script> عنوان']])->saveQuietly();

    $fake = new FakeShareCardCapturer;
    app()->instance(ShareCardCapturer::class, $fake);

    expect(app(GenerateShareCard::class)->handle($this->job->refresh(), Locale::Ar))->toBeTrue();

    expect($fake->lastHtml)
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;')
        ->toContain("default-src 'none'")
        ->toContain('جهة البطاقة')
        ->toContain('اسم الملقي');

    expect(Storage::disk('local')->get(ShareCard::path($this->job->id, Locale::Ar)))->toBe("\x89PNG-fake-1200x630");
});

it('generates nothing for an unpublished summary, or with the capturer off', function (): void {
    app()->instance(ShareCardCapturer::class, new FakeShareCardCapturer);
    expect(app(GenerateShareCard::class)->handle($this->job, Locale::Ar))->toBeFalse();

    shareCardPublish($this->job);
    app()->instance(ShareCardCapturer::class, new NullShareCardCapturer);
    expect(app(GenerateShareCard::class)->handle($this->job->refresh(), Locale::Ar))->toBeFalse();

    Storage::disk('local')->assertMissing(ShareCard::path($this->job->id, Locale::Ar));
});

it('queues card generation after publishing only when the chrome capturer is on', function (): void {
    Queue::fake();

    config()->set('khulasah.share_card.capturer', 'none');
    shareCardPublish($this->job);
    Queue::assertNotPushed(GenerateShareCards::class);

    config()->set('khulasah.share_card.capturer', 'chrome');
    app(PublishSummary::class)->handle($this->job->refresh(), [
        'page:ar' => app(RenderOutput::class)->handle($this->job, app(PageRenderer::class))->contents,
    ]);
    Queue::assertPushed(GenerateShareCards::class, fn (GenerateShareCards $queued): bool => $queued->summaryJob->is($this->job));
});

it('generates a card for every published language of the summary', function (): void {
    shareCardPublish($this->job);
    app()->instance(ShareCardCapturer::class, new FakeShareCardCapturer);

    (new GenerateShareCards($this->job))->handle(app(GenerateShareCard::class));

    Storage::disk('local')->assertExists(ShareCard::path($this->job->id, Locale::Ar));
});

it('removes the stored cards when the summary is deleted', function (): void {
    shareCardPublish($this->job);
    Storage::disk('local')->put(ShareCard::path($this->job->id, Locale::Ar), 'GENERATED-PNG');

    app(DeleteSummary::class)->handle($this->job->refresh());

    Storage::disk('local')->assertMissing(ShareCard::path($this->job->id, Locale::Ar));
});

/*
 * ─── الملتقِط ────────────────────────────────────────────────────────
 */

it('binds the null capturer by default and chrome only when asked', function (): void {
    config()->set('khulasah.share_card.capturer', 'none');
    expect(app(ShareCardCapturer::class))->toBeInstanceOf(NullShareCardCapturer::class);

    config()->set('khulasah.share_card.capturer', 'chrome');
    expect(app(ShareCardCapturer::class))->toBeInstanceOf(ChromeShareCardCapturer::class);
});

it('returns null and leaves no temporary files when the browser is missing', function (): void {
    $before = glob(storage_path('app/tmp/share-card-*')) ?: [];

    $png = (new ChromeShareCardCapturer('/nonexistent/chromium', 5))->capture('<p>x</p>', 1200, 630);

    expect($png)->toBeNull()
        ->and(glob(storage_path('app/tmp/share-card-*')) ?: [])->toBe($before);
});
