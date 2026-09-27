<?php

declare(strict_types=1);

use App\Actions\Summary\ResumeFailedJob;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;

/*
 * الاستئناف من آخر مرحلةٍ ناجحة — T-93.
 *
 * **كان هذا موجوداً في لوحة المشرف وحدها منذ T-21**، فمن استعمل زرّ
 * «أعدْ المحاولة» من شاشته أعاد المراحل الغالية كلَّها. والمنطق هنا فعلٌ
 * واحد يستعمله المساران معاً — انظر اختبارات المسارين في
 * `AdminPanelTest.php` و`TenantScreensTest.php` لتغطية الطبقة الأعلى.
 *
 * **وكلُّ اختبار هنا يبني تاريخاً حقيقياً بـ`TransitionJob`** لا يصنع
 * `state` مباشرة: نقطة الاستئناف تُقرأ من `from_state` آخر انتقال
 * ({@see \App\Support\Ui\JobProgress::lastRunningState()}), فاختبارٌ
 * يصنع الحالة بلا انتقالٍ يقود إليها لا يقيس شيئاً حقيقياً.
 */

/** يبني مهمّةً مرّت بالحالات المعطاة حقّاً، ثمّ أخفقت من آخرها. */
function realFailedJob(Tenant $tenant, array $through, string $errorCode = 'connection_failed'): SummaryJob
{
    $lecture = Lecture::factory()->for_($tenant)->create();
    $job = SummaryJob::factory()->for_($lecture)->create(['tenant_id' => $tenant->id]);
    $transition = app(TransitionJob::class);

    foreach ($through as $to) {
        $transition->handle($job, $to);
    }

    // آخر انتقال: من الحالة الجارية إلى `failed`. و`from_state` هنا هو
    // ما تقرأه `JobProgress::lastRunningState()` نقطةَ استئنافٍ — أي
    // المرحلة التي ستُعاد، لا المرحلة السابقة الناجحة.
    $transition->handle($job, JobState::Failed, errorCode: $errorCode);

    return $job->refresh();
}

it('يبدأ من الصفر حين لا سجلّ انتقالات له', function (): void {
    // مهمّةٌ صُنعت بحالة `failed` مباشرة (كما تفعل مصانع الاختبار)، بلا
    // تاريخٍ حقيقيّ — فلا شيء يُستأنف منه، والافتراض `queued`.
    $tenant = Tenant::factory()->create();
    $job = SummaryJob::factory()->inState(JobState::Failed)->create(['tenant_id' => $tenant->id]);

    $replacement = app(ResumeFailedJob::class)->handle($job);

    expect($replacement->state)->toBe(JobState::Queued)
        ->and($replacement->id)->not->toBe($job->id);
});

it('يستأنف من استخراج البنية بعد أن نجحت التنقية', function (): void {
    // نجحت `transcribing` و`cleaning`، فالمهمّة دخلت `extracting_structure`
    // فعلاً حين أخفقت فيها — فهذا موضع الاستئناف، لا ما بعده. والحالةُ
    // الراهنة عند الإخفاق هي آخر عنصرٍ في القائمة، فهي المرحلة الجارية.
    $tenant = Tenant::factory()->create();
    $job = realFailedJob($tenant, [
        JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
    ]);
    $job->forceFill(['transcript_text' => 'نصٌّ منظَّف'])->save();

    $replacement = app(ResumeFailedJob::class)->handle($job);

    expect($replacement->state)->toBe(JobState::ExtractingStructure)
        ->and($replacement->transcript_text)->toBe('نصٌّ منظَّف')
        // ولا بنية بعد: هذه هي المرحلة التي ستُحاوَل من جديد.
        ->and($replacement->structure_json)->toBeNull()
        // ولم تُصرف الحصّةُ الآلية الكاملة على الخَلَف: هذه محاولةٌ ثانية.
        ->and($replacement->attempt)->toBe(1);
});

it('يستأنف من الكتابة حاملاً البنية والشواهد المحسومة', function (): void {
    // نجحت البنية والشواهد والتحقّق، ودخلت `writing` فعلاً حين أخفقت.
    $tenant = Tenant::factory()->create();
    $job = realFailedJob($tenant, [
        JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing,
    ]);
    $job->forceFill(['transcript_text' => 'نصٌّ منظَّف', 'structure_json' => ['title_ar' => 'عنوان']])->save();

    $resolver = User::factory()->owner()->for_($tenant)->create();
    $removed = EvidenceItem::factory()->for_($job)->settledAs(ReviewStatus::Removed)->create([
        'raw_text' => 'حديثٌ استبعده المراجع',
        'resolved_by' => $resolver->id,
    ]);
    $autoPassed = EvidenceItem::factory()->for_($job)->create([
        'raw_text' => 'حديثٌ اجتاز التحقّق تلقائياً',
    ]);

    $replacement = app(ResumeFailedJob::class)->handle($job);

    expect($replacement->state)->toBe(JobState::Writing)
        ->and($replacement->transcript_text)->toBe('نصٌّ منظَّف')
        ->and($replacement->structure_json)->toBe(['title_ar' => 'عنوان']);

    $items = $replacement->evidenceItems()->get();

    expect($items)->toHaveCount(2)
        // ★ القرار البشريّ يُنسخ بحاله — الجهةُ المراجِعة ووقت الحسم،
        // لا حالةً معادَ تخمينها.
        ->and($items->firstWhere('raw_text', $removed->raw_text)->review_status)->toBe(ReviewStatus::Removed)
        ->and($items->firstWhere('raw_text', $removed->raw_text)->resolved_by)->toBe($resolver->id)
        ->and($items->firstWhere('raw_text', $autoPassed->raw_text)->review_status)->toBe(ReviewStatus::AutoPassed)
        ->and($items->firstWhere('raw_text', $autoPassed->raw_text)->match_status)->toBe(MatchStatus::Exact)
        // والأصل يبقى كما كان — لا نسخ يُحذف منه ولا يُعاد ربطه.
        ->and($job->fresh()->evidenceItems()->count())->toBe(2);
});

// ★★ الإصلاح الحاكم في T-93: `body_json` كان يُهمَل، فالترجمة بعد
// استئنافٍ عند `rendering` كانت تُترجم من متنٍ فارغ — صامتةً، بلا خطأ يُرى.
it('يستأنف من الرسم حاملاً body_json مع body_html والشواهد', function (): void {
    // نجحت الكتابة، ودخلت `rendering` فعلاً حين أخفقت.
    $tenant = Tenant::factory()->create();
    $job = realFailedJob($tenant, [
        JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering,
    ]);
    $body = ['sections' => [['blocks' => [['type' => 'lead', 'text' => 'الفقرة الأولى']]]]];
    $job->forceFill(['body_json' => $body, 'body_html' => '<p class="lead">الفقرة الأولى</p>'])->save();
    EvidenceItem::factory()->for_($job)->create();

    $replacement = app(ResumeFailedJob::class)->handle($job);

    expect($replacement->state)->toBe(JobState::Rendering)
        ->and($replacement->body_json)->toBe($body)
        ->and($replacement->body_html)->toBe('<p class="lead">الفقرة الأولى</p>')
        // والشواهد لازمةٌ هنا أيضاً — صفحة المصادر تُبنى منها لا من المتن.
        ->and($replacement->evidenceItems()->count())->toBe(1);
});
