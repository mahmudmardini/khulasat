<?php

declare(strict_types=1);

use App\Actions\Compliance\ExportTenantData;
use App\Actions\Compliance\RecordComplaint;
use App\Actions\Compliance\RequestAccountClosure;
use App\Actions\Publish\UnpublishSummary;
use App\Actions\Render\RenderOutput;
use App\Enums\ComplaintKind;
use App\Models\Complaint;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * الحذف وتصدير البيانات ومسار الشكوى — T-24.
 *
 * **«الدراسة تَعِد بمسار حذف يُنفَّذ خلال 48 ساعة. والوعد بلا تنفيذ أسوأ
 * من عدمه، لأنّه يُذكر في العرض التجاري.»**
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'name_ar' => 'جهة الاختبار']);
    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'speaker_name' => 'اسم الملقي',
    ]);
    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => $this->lecture->id,
        'slug' => 'anuan-al-drs',
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'transcript_text' => 'نصّ التفريغ الأصلي للدرس.',
        'body_html' => '<p class="lead">متن.</p>',
    ]);
});

/*
 * ─── نموذج الاعتراض ──────────────────────────────────────────────────
 */

/** **بلا تسجيل دخول** — من يعترض غالباً ليس زبوناً. */
it('يقبل الاعتراض بلا تسجيل دخول', function (): void {
    $this->get('/complaint')->assertOk();

    $this->post('/complaint', [
        'kind' => ComplaintKind::Attribution->value,
        'url' => 'https://tenant-a.khulasah.app/anuan-al-drs',
        'contact' => 'sheikh@example.test',
        'detail' => 'لم ألقِ هذا الدرس.',
    ])->assertSessionHasNoErrors();

    expect(Complaint::query()->count())->toBe(1);
});

/** والشكوى تُربط بالجهة والملخّص من رابطها. */
it('يستنتج الجهة والملخّص من الرابط', function (): void {
    $complaint = app(RecordComplaint::class)->handle([
        'kind' => 'evidence',
        'url' => 'https://tenant-a.khulasah.app/anuan-al-drs',
        'contact' => 'reader@example.test',
    ]);

    expect($complaint->tenant_id)->toBe($this->tenant->id)
        ->and($complaint->summary_job_id)->toBe($this->job->id);
});

/**
 * **ورابطٌ لا يُطابق لا يُسقط الشكوى.**
 *
 * فمن رفض اعتراضاً لأنّ رابطه نُسخ خطأً أضاع اعتراضاً صحيحاً.
 */
it('يقيّد الشكوى وإن لم يُطابق رابطها شيئاً', function (): void {
    $complaint = app(RecordComplaint::class)->handle([
        'kind' => 'takedown',
        'url' => 'https://unknown.example.test/whatever',
        'contact' => 'someone@example.test',
    ]);

    expect($complaint->exists)->toBeTrue()
        ->and($complaint->tenant_id)->toBeNull();
});

/** **والوعد يُقاس**: زمن الوصول وزمن المعالجة والمهلة المعلنة. */
it('يجعل الوعد قابلاً للقياس', function (): void {
    $complaint = app(RecordComplaint::class)->handle([
        'kind' => ComplaintKind::Takedown->value,
        'url' => 'https://tenant-a.khulasah.app/anuan-al-drs',
        'contact' => 'a@b.test',
    ]);

    expect($complaint->received_at)->not->toBeNull()
        ->and($complaint->kind->slaHours())->toBe(48)
        ->and($complaint->dueAt()->diffInHours($complaint->received_at, absolute: true))->toBe(48.0)
        ->and($complaint->isOverdue())->toBeFalse();
});

it('يرصد تأخّر المعالجة عن المهلة', function (): void {
    $complaint = Complaint::query()->create([
        'kind' => ComplaintKind::Takedown->value,
        'url' => 'https://tenant-a.khulasah.app/x',
        'contact' => 'a@b.test',
        'status' => 'open',
        'received_at' => now()->subHours(72),
    ]);

    expect($complaint->isOverdue())->toBeTrue();
});

/** ولكلّ نوعٍ مهلته: طلب الإزالة أعجل من خطأ التخريج. */
it('يعطي كل نوع مهلته المعلنة', function (): void {
    expect(ComplaintKind::Takedown->slaHours())->toBe(48)
        ->and(ComplaintKind::Attribution->slaHours())->toBe(48)
        ->and(ComplaintKind::Evidence->slaHours())->toBe(168);
});

/** **ورابط الاعتراض في تذييل كل صفحة منشورة** — البند الأوّل. */
it('يضع رابط الاعتراض في تذييل الصفحة المنشورة', function (): void {
    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant))
        ->contents;

    expect($html)->toContain('/complaint')
        ->and($html)->toContain('للتنبيه على خطأ');
});

it('يحدّ من إغراق نموذج الاعتراض', function (): void {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r): bool => $r->uri() === 'complaint' && in_array('POST', $r->methods(), true));

    expect($route->gatherMiddleware())->toContain('throttle:10,60');
});

/*
 * ─── التصدير ─────────────────────────────────────────────────────────
 */

it('يصدّر بيانات الجهة أرشيفاً واحداً', function (): void {
    $path = app(ExportTenantData::class)->handle($this->tenant);

    Storage::disk('public')->assertExists($path);

    $zip = new ZipArchive;
    $temp = tempnam(sys_get_temp_dir(), 'check').'.zip';
    file_put_contents($temp, Storage::disk('public')->get($path));
    $zip->open($temp);

    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    expect($names)->toContain('tenant.json', 'summaries.json', 'README.txt');

    $summaries = json_decode((string) $zip->getFromName('summaries.json'), true);
    $zip->close();

    expect($summaries['count'])->toBe(1)
        ->and($summaries['summaries'][0]['title'])->toBe('عنوان الدرس')
        // **ونصّ التفريغ يخرج معها**: مادّتها الأصلية، ومن صدّر الملخّص
        // بلا أصله أعطى الجهة نصفَ ما تملك.
        ->and($summaries['summaries'][0]['transcript_text'])->toBe('نصّ التفريغ الأصلي للدرس.');
});

/** والصفحات المنشورة تخرج **كما تُقرأ**، لا معرّفاتٍ في جدول. */
it('يضمّ الصفحات المنشورة كما تُقرأ', function (): void {
    app(RenderOutput::class)->handle($this->job, app(PageRenderer::class));

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($this->job), BrandKit::forTenant($this->tenant))
        ->contents;

    Storage::disk('public')->put('tenant-a/anuan-al-drs/index.html', $html);
    $this->job->outputs()->update(['storage_path' => 'tenant-a/anuan-al-drs/index.html']);

    $path = app(ExportTenantData::class)->handle($this->tenant->refresh());

    $zip = new ZipArchive;
    $temp = tempnam(sys_get_temp_dir(), 'check').'.zip';
    file_put_contents($temp, Storage::disk('public')->get($path));
    $zip->open($temp);

    $page = $zip->getFromName('summaries/anuan-al-drs.html');
    $zip->close();

    expect($page)->toBeString()
        ->and($page)->toStartWith('<!DOCTYPE html>');
});

/** والشعار ملفّاً لا سطراً في JSON بطول مئة ألف حرف. */
it('يُخرج الشعار ملفّاً لا data URI', function (): void {
    $this->tenant->forceFill(['brand_kit' => [
        'logo_data_uri' => 'data:image/png;base64,'.base64_encode('fake-png-bytes'),
    ]])->save();

    $path = app(ExportTenantData::class)->handle($this->tenant->refresh());

    $zip = new ZipArchive;
    $temp = tempnam(sys_get_temp_dir(), 'check').'.zip';
    file_put_contents($temp, Storage::disk('public')->get($path));
    $zip->open($temp);

    $logo = $zip->getFromName('logo.png');
    $tenantJson = (string) $zip->getFromName('tenant.json');
    $zip->close();

    expect($logo)->toBe('fake-png-bytes')
        ->and($tenantJson)->not->toContain('logo_data_uri');
});

/*
 * ─── إغلاق الحساب ────────────────────────────────────────────────────
 */

/** **تصديرٌ إجباري ثمّ حذفٌ بعد مهلة، والتراجع متاح** — البند الخامس. */
it('يصدّر قبل أن يسجّل طلب الإغلاق', function (): void {
    $tenant = app(RequestAccountClosure::class)->handle($this->tenant);

    expect($tenant->closure_export_path)->not->toBeNull()
        ->and($tenant->closure_requested_at)->not->toBeNull()
        ->and($tenant->status)->toBe('closing');

    Storage::disk('public')->assertExists($tenant->closure_export_path);
});

it('يؤجّل الحذف مهلةً معلنة', function (): void {
    $tenant = app(RequestAccountClosure::class)->handle($this->tenant);

    // يُقارَن باليوم لا بفرقٍ عشري: الميكروثانية بين النداءين تُسقط
    // مساواةً صارمة على float بلا سببٍ يخصّ ما نختبره.
    expect($tenant->purge_after->toDateString())
        ->toBe($tenant->closure_requested_at->copy()->addDays(RequestAccountClosure::GRACE_DAYS)->toDateString());
});

it('يتيح التراجع خلال المهلة', function (): void {
    $closure = app(RequestAccountClosure::class);

    $tenant = $closure->handle($this->tenant);
    $tenant = $closure->cancel($tenant);

    expect($tenant->purge_after)->toBeNull()
        ->and($tenant->closure_requested_at)->toBeNull()
        ->and($tenant->status)->toBe('active');
});

/*
 * ─── 410 لا 404 ──────────────────────────────────────────────────────
 */

/** والبند الثالث يتقاطع مع T-15، ويُختبر هنا من وجهة الامتثال. */
it('يُبقي شاهدة 410 عند الإزالة استجابةً لاعتراض', function (): void {
    app(RenderOutput::class)->handle($this->job, app(PageRenderer::class));

    $this->job->outputs()->update(['storage_path' => 'tenant-a/anuan-al-drs/index.html']);
    Storage::disk('public')->put('tenant-a/anuan-al-drs/index.html', '<html>original</html>');

    app(UnpublishSummary::class)->handle($this->job->refresh());

    $left = Storage::disk('public')->get('tenant-a/anuan-al-drs/index.html');

    expect($left)->toContain('أُزيل هذا الملخّص')
        ->and($left)->not->toContain('original');
});
