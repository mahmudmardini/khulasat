<?php

declare(strict_types=1);

use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\UnpublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Summary\TransitionJob;
use App\Contracts\PublishStore;
use App\Domain\Summary\JobState;
use App\Enums\AuditAction;
use App\Enums\ComplaintKind;
use App\Enums\ComplaintStatus;
use App\Models\AuditEvent;
use App\Models\Complaint;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Render\PageRenderer;
use App\Support\Publish\Paths;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

/*
 * لوحة الاعتراضات — SCREENS.md §هـ، والمهمّة T-28.
 *
 * **وهي النصف الغائب من T-24.** فذاك بنى النموذج العامّ والجدول وفعلَ
 * الإزالة، ولا موضع يُقرأ فيه ما وصل — فالشكوى تُسجَّل ولا يراها أحد،
 * ومهلةُ الثمانِ والأربعين ساعة تمضي بلا علم.
 */

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a', 'name_ar' => 'جهة الاختبار']);
    $this->owner = User::factory()->owner()->for_($this->tenant)->create();

    $this->admin = User::factory()->superAdmin()->create();

    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title_ar' => 'عنوان الدرس',
        ])->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_html' => '<p class="lead">متن.</p>',
    ]);
});

/** ينشر الملخّص بالمسار الشرعي، فتصير له صفحةٌ تُزال. */
function publishTheJob(SummaryJob $job): SummaryJob
{
    app(RenderOutput::class)->handle($job, app(PageRenderer::class));

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->refresh()), BrandKit::forTenant($job->tenant))
        ->contents;

    app(PublishSummary::class)->handle($job->refresh(), ['page' => $html]);

    return $job->refresh();
}

function complaintFor(?SummaryJob $job, ComplaintKind $kind = ComplaintKind::Takedown, int $hoursAgo = 1): Complaint
{
    return Complaint::query()->create([
        'tenant_id' => $job?->tenant_id,
        'summary_job_id' => $job?->id,
        'kind' => $kind->value,
        'url' => 'https://tenant-a.khulasah.app/'.($job?->slug ?? 'x'),
        'contact' => 'complainant@example.test',
        'detail' => 'نُسب إليّ كلامٌ لم أقله.',
        'status' => ComplaintStatus::Open->value,
        'received_at' => now()->subHours($hoursAgo),
    ]);
}

/*
 * ─── الباب ───────────────────────────────────────────────────────────
 */

it('يفتح لوحة الاعتراضات للمشرف وحده', function (): void {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.takedowns.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Takedowns/Index'));
});

it('لا يفتحها لمستخدم جهة', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.takedowns.index'))
        ->assertNotFound();
});

/*
 * ─── الترتيب: الأقرب إلى مهلته أوّلاً ─────────────────────────────────
 */

/**
 * ★ **الاختبار الحاكم في الترتيب** — والمهلة تختلف بنوع الاعتراض.
 *
 * فشكوى تخريجٍ وصلت قبل يومين مهلتُها أسبوع، وطلبُ إزالةٍ وصل اليوم مهلتُه
 * ثمانٍ وأربعون ساعة — **والثاني أولى**. وصندوقٌ مرتَّبٌ بالأحدث وصولاً
 * يدفن ما بقيت له ساعتان تحت ما بقي له خمسة أيّام.
 */
it('يرتّب بالمهلة لا بالوصول', function (): void {
    // وصل قبل يومين، ومهلته أسبوع — فيستحقّ بعد خمسة أيّام.
    $evidence = complaintFor(null, ComplaintKind::Evidence, hoursAgo: 48);

    // وصل قبل ساعة، ومهلته 48 — فيستحقّ بعد سبعٍ وأربعين ساعة.
    $takedown = complaintFor(null, ComplaintKind::Takedown, hoursAgo: 1);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.takedowns.index'))
        ->assertInertia(fn ($page) => $page
            ->where('complaints.data.0.id', $takedown->id)
            ->where('complaints.data.1.id', $evidence->id));
});

/** والمهلة المتبقّية محسوبةٌ في الخادم لا في المتصفّح. */
it('يحسب المهلة المتبقّية والمتأخّرة', function (): void {
    complaintFor(null, ComplaintKind::Takedown, hoursAgo: 12);
    complaintFor(null, ComplaintKind::Takedown, hoursAgo: 72);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.takedowns.index'))
        ->assertInertia(fn ($page) => $page
            // المتأخّر أوّلاً، وساعاته سالبة.
            ->where('complaints.data.0.overdue', true)
            ->where('complaints.data.0.hours_left', -24)
            ->where('complaints.data.1.overdue', false)
            ->where('complaints.data.1.hours_left', 36)
            ->where('counts.overdue', 1)
            ->where('counts.open', 2));
});

/**
 * حالُ الصفحة **ثلاثٌ لا اثنتان**.
 *
 * وكانت منطقيّةً (`is_published`)، فرابطٌ لم يُطابق شيئاً يقرأ `false`
 * **فيُعرض «الصفحة مُزالة»** — وهي لم تكن موجودةً أصلاً، فيظنّ المشرف أنّ
 * الإزالة نُفّذت.
 */
it('يفرّق بين صفحةٍ قائمة ومُزالة ورابطٍ لا يطابق شيئاً', function (): void {
    $live = publishTheJob($this->job);
    complaintFor($live, ComplaintKind::Evidence, hoursAgo: 1);

    $removed = publishTheJob(SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $this->tenant->id, 'title_ar' => 'درس آخر'])->id,
        'structure_json' => ['title_ar' => 'درس آخر'],
        'body_html' => '<p class="lead">متن.</p>',
    ]));
    app(UnpublishSummary::class)->handle($removed);
    complaintFor($removed, ComplaintKind::Evidence, hoursAgo: 2);

    complaintFor(null, ComplaintKind::Evidence, hoursAgo: 3);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.takedowns.index'))
        ->assertInertia(fn ($page) => $page
            // مرتَّبةٌ بالمهلة: الأقدمُ وصولاً أوّلاً حين يتساوى النوع.
            ->where('complaints.data.0.target', 'unknown')
            ->where('complaints.data.1.target', 'removed')
            ->where('complaints.data.2.target', 'live'));
});

/*
 * ─── الإزالة: 410 لا 404 ─────────────────────────────────────────────
 */

/** ★ معيار القبول الثاني: الإزالة تستدعي `UnpublishSummary`، فتبقى الشاهدة. */
it('يزيل الصفحة ويترك شاهدة ٤١٠ مكانها', function (): void {
    $job = publishTheJob($this->job);
    $complaint = complaintFor($job);

    $path = Paths::tombstone($this->tenant->slug, (string) $job->slug);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'أُزيلت بطلب الملقي، وقد أثبت هويّته.',
        ])->assertRedirect();

    $store = app(PublishStore::class);

    expect($store->exists($path))->toBeTrue()
        // **والشاهدة لا الصفحة**: «كان هنا وأُزيل» لا «لم يكن هنا شيء».
        ->and(Storage::disk('public')->get($path))->toContain('أُزيل هذا الملخّص')
        ->and(Storage::disk('public')->get($path))->not->toContain('class="lead"');

    $job->refresh();

    expect($job->unpublished_at)->not->toBeNull()
        ->and($job->published_at)->toBeNull();

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Unpublished)
        ->and($complaint->resolved_at)->not->toBeNull();
});

/** **ولا يُحسم بلا نصٍّ مكتوب** — وهو ما يُبلَّغ به صاحب الجهة. */
it('يرفض الحسم بلا نصّ مكتوب', function (): void {
    $job = publishTheJob($this->job);
    $complaint = complaintFor($job);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
        ])->assertSessionHasErrors('resolution');

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Open)
        ->and($job->refresh()->published_at)->not->toBeNull();
});

/** والمحسوم لا يُحسم مرّتين: شاهدةٌ فوق شاهدة وقيدٌ لفعلٍ لم يقع. */
it('لا يحسم اعتراضاً محسوماً', function (): void {
    $complaint = complaintFor(publishTheJob($this->job));

    $complaint->forceFill([
        'status' => ComplaintStatus::Dismissed->value,
        'resolved_at' => now(),
    ])->save();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'محاولة ثانية.',
        ])->assertSessionHasErrors('status');

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Dismissed);
});

/**
 * **ولا تُزال صفحةٌ لا نعرفها.**
 *
 * والرابط يُقرأ عند الاستقبال ولا يُوثَق به (T-24): قد يصل خطأً أو لصفحةٍ
 * حُذفت. فمن أراد إزالةً بلا ملخّصٍ مرتبط فليُحلّها يدوياً.
 */
it('يرفض الإزالة حين لا ملخّص مرتبطاً بالرابط', function (): void {
    $complaint = complaintFor(null);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'طلبٌ صحيح لكنّ رابطه لا يُطابق شيئاً.',
        ])->assertSessionHasErrors('status');

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Open);
});

/** ومخرجان بغير إزالة: عولجت، ولا إجراء — ولا يمسّان المنشور. */
it('يحسم بغير إزالة ولا يمسّ الصفحة', function (): void {
    $job = publishTheJob($this->job);

    foreach ([ComplaintStatus::Resolved, ComplaintStatus::Dismissed] as $status) {
        $complaint = complaintFor($job, ComplaintKind::Evidence);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.takedowns.update', $complaint), [
                'status' => $status->value,
                'resolution' => 'صُحّح التخريج في المتن.',
            ])->assertRedirect();

        expect($complaint->refresh()->status)->toBe($status);
    }

    expect($job->refresh()->published_at)->not->toBeNull()
        ->and($job->unpublished_at)->toBeNull();
});

/*
 * ─── القيد في سجلّ التدقيق ───────────────────────────────────────────
 */

/** ★ معيار القبول الرابع: كل إجراء يُقيَّد كما تُقيَّد أفعال T-21. */
it('يقيّد كل حسم بمن فعله وبنصّه', function (): void {
    $job = publishTheJob($this->job);
    $complaint = complaintFor($job);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'أُزيلت بطلب الملقي.',
        ]);

    $event = AuditEvent::query()->latest('id')->firstOrFail();

    expect($event->action)->toBe(AuditAction::ComplaintUnpublished)
        ->and($event->admin_email)->toBe($this->admin->email)
        ->and($event->subject_label)->toBe($complaint->url)
        ->and($event->note)->toBe('أُزيلت بطلب الملقي.')
        ->and($event->changes['status'])->toEqual(['from' => 'open', 'to' => 'unpublished'])
        // وإزالةُ منشورٍ تُبرز في السجلّ كما تُبرز الأفعال المالية.
        ->and($event->action->isSensitive())->toBeTrue();
});

/** ولكلّ مخرَجٍ فعلُه في السجلّ، فلا يختلط ما أُزيل بما رُدّ. */
it('يفرّق في السجلّ بين الإزالة والمعالجة والردّ', function (): void {
    $job = publishTheJob($this->job);

    $expected = [
        ComplaintStatus::Resolved->value => AuditAction::ComplaintResolved,
        ComplaintStatus::Dismissed->value => AuditAction::ComplaintDismissed,
    ];

    foreach ($expected as $status => $action) {
        $complaint = complaintFor($job, ComplaintKind::Evidence);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.takedowns.update', $complaint), [
                'status' => $status,
                'resolution' => 'نصّ الحسم.',
            ]);

        expect(AuditEvent::query()->latest('id')->firstOrFail()->action)->toBe($action);
    }
});

/*
 * ─── إشعار الجهة ─────────────────────────────────────────────────────
 */

/**
 * ★ معيار القبول الثالث: **إشعار الجهة بما وقع على صفحتها ولماذا.**
 *
 * ولا بريد في هذه المرحلة، فالإشعار حيث ينظر صاحب الجهة: شاشة ملخّصه.
 */
it('يُعلم صاحب الجهة بإزالة صفحته وسببها', function (): void {
    $job = publishTheJob($this->job);
    $complaint = complaintFor($job);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'أُزيلت بطلب الملقي، وقد أثبت هويّته.',
        ]);

    $this->actingAs($this->owner)
        ->get(route('jobs.show', $job))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('job.takedown.reason', 'أُزيلت بطلب الملقي، وقد أثبت هويّته.')
            // ★ **والشارة تقول «غير منشور» وإن بقيت الحالة `published`.**
            ->where('job.status', 'unpublished'));
});

/**
 * **ولا يُعرض من الشكوى إلّا نصُّ الحسم.**
 *
 * فالمعترض غالباً ليس زبوناً — شيخٌ أو قارئ — وبريدُه وتفصيلُ شكواه ليسا
 * للجهة المعترَض عليها.
 */
it('لا يسرّب بريد المعترض ولا نصّ شكواه إلى الجهة', function (): void {
    $job = publishTheJob($this->job);
    $complaint = complaintFor($job);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.takedowns.update', $complaint), [
            'status' => ComplaintStatus::Unpublished->value,
            'resolution' => 'أُزيلت بطلب صاحب الحقّ.',
        ]);

    $response = $this->actingAs($this->owner)->get(route('jobs.show', $job));

    expect($response->getContent())->not->toContain('complainant@example.test')
        ->and($response->getContent())->not->toContain('نُسب إليّ كلامٌ لم أقله');
});

/** والفهرس كذلك: صفٌّ أُزيلت صفحتُه لا يُقرأ «منشور». */
it('يعرض المُزالة «غير منشور» في الفهرس', function (): void {
    $job = publishTheJob($this->job);

    app(UnpublishSummary::class)->handle($job);

    $this->actingAs($this->owner)
        ->get('/panel')
        ->assertInertia(fn ($page) => $page->where('jobs.data.0.status', 'unpublished'));
});

/*
 * ─── النصوص ──────────────────────────────────────────────────────────
 */

/**
 * لكلّ حالةٍ ونوعٍ اسمٌ عربيّ في `lang`.
 *
 * ويُحرَس بالتعداد لا بالكتابة: من زاد حالةً ونسي نصّها أظهر المفتاح خاماً
 * في الشاشة — وهي علّةٌ صامتة لا تُرى إلّا بفتحها.
 */
it('يترجم كل حالة وكل نوع اعتراض', function (): void {
    foreach (ComplaintStatus::cases() as $status) {
        expect(Lang::has("admin.takedowns.status.{$status->value}"))->toBeTrue($status->value);
    }

    foreach (ComplaintKind::cases() as $kind) {
        expect(Lang::has("admin.takedowns.kinds.{$kind->value}"))->toBeTrue($kind->value);
    }
});

/** وأفعالُ السجلّ الثلاثة كذلك — و`t()` تشقّ المفتاح عند النقاط. */
it('يترجم أفعال الاعتراضات في سجلّ التدقيق', function (): void {
    foreach ([AuditAction::ComplaintUnpublished, AuditAction::ComplaintResolved, AuditAction::ComplaintDismissed] as $action) {
        expect(Lang::has('admin.audit.actions.'.$action->value))->toBeTrue($action->value);
    }
});
