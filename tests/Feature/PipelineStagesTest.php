<?php

declare(strict_types=1);

use App\Actions\Stages\BuildOutputMeta;
use App\Actions\Stages\CleanTranscript;
use App\Actions\Stages\ExtractEvidence;
use App\Actions\Stages\StageSchemas;
use App\Actions\Stages\WriteBody;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Services\Model\FakeModelGateway;
use App\Support\Model\JsonSchema;
use Illuminate\Support\Facades\Http;

// T-11: «الاختبارات بردود مسجَّلة في fixtures. لا نداء حقيقي في CI».

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create([
        'tenant_id' => $this->tenant->id,
        'transcript_text' => 'نصّ التفريغ الخام قبل التنظيف، وفيه حشو وتكرار.',
    ]);
    $this->gateway = app(FakeModelGateway::class);
});

/** يقود المهمّة إلى حالةٍ بالمسار الشرعي نفسه. */
function driveTo(SummaryJob $job, JobState ...$states): SummaryJob
{
    foreach ($states as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job;
}

it('refuses to clean an empty transcript', function (): void {
    driveTo($this->job, JobState::Transcribing, JobState::Cleaning);
    $this->job->forceFill(['transcript_text' => ''])->save();

    expect(fn () => app(CleanTranscript::class)->handle($this->job))
        ->toThrow(ModelCallFailed::class);
});

// المخطّط يرفض صنف شاهدٍ لا محقّق له — فلا يُتحقّق منه فلا يُنشر.
it('rejects an evidence kind that has no verifier', function (): void {
    driveTo($this->job, JobState::Transcribing, JobState::Cleaning,
        JobState::ExtractingStructure, JobState::ExtractingEvidence);

    $this->gateway->willReturn(Stage::ExtractingEvidence, 'schema_mismatch');

    expect(fn () => app(ExtractEvidence::class)->handle($this->job))
        ->toThrow(ModelCallFailed::class);
});

// ── المرحلة ٥: كتابة المتن — الحاجز الحاكم ───────────────────────

// **أهمّ اختبار في هذا الملفّ.** المواصفة §6-4: «لا يُستدعى النموذج قبل
// انتهاء التحقّق». وحارسُ TransitionJob يمسك needs_review→writing ولا يمسك
// verifying→writing — وهذا ما يُثبته الاختبار: المهمّة **تعبر فعلاً** إلى
// writing وفيها شاهدٌ معلّق، فلولا حاجزُ WriteBody لكُتب المتن عليه.
it('refuses to write the body while any evidence is unsettled', function (): void {
    driveTo($this->job, JobState::Transcribing, JobState::Cleaning,
        JobState::ExtractingStructure, JobState::ExtractingEvidence,
        JobState::Verifying, JobState::Writing);

    $this->job->forceFill(['structure_json' => ['title_ar' => 'عنوان']])->save();

    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'review_status' => ReviewStatus::Pending,
    ]);

    try {
        app(WriteBody::class)->handle($this->job);
    } catch (ModelCallFailed $failure) {
        expect($failure->errorCode)->toBe('evidence_unsettled')
            // ولم يُنادَ نموذجٌ أصلاً: المنع قبل النداء لا بعده.
            ->and($this->gateway->calls)->toBeEmpty();

        return;
    }

    $this->fail('كان يجب أن يمتنع عن الكتابة.');
});

// **وسقوطٌ معلَنٌ خيرٌ من انحدارٍ صامت**: متنٌ بلا تفريغ هو العطبُ الذي
// أصلحته T-52، فلا يُسمح بالعودة إليه من باب المهمّة الناقصة.
it('refuses to write the body with no transcript to write from', function (): void {
    driveTo($this->job, JobState::Transcribing, JobState::Cleaning,
        JobState::ExtractingStructure, JobState::ExtractingEvidence,
        JobState::Verifying, JobState::Writing);

    $this->job->forceFill([
        'structure_json' => ['title_ar' => 'عنوان'],
        'transcript_text' => '   ',
    ])->save();

    try {
        app(WriteBody::class)->handle($this->job);
    } catch (ModelCallFailed $failure) {
        expect($failure->errorCode)->toBe('transcript_missing')
            ->and($this->gateway->calls)->toBeEmpty();

        return;
    }

    $this->fail('كان يجب أن يمتنع عن الكتابة بلا تفريغ.');
});

it('refuses to build metadata with no structure', function (): void {
    expect(fn () => app(BuildOutputMeta::class)->handle($this->job))
        ->toThrow(ModelCallFailed::class);
});

/*
 * **التنظيف وحده بلا مخطّط** — §6، وT-43.
 *
 * وكانت الكتابةُ معه، تُخرج HTML يكتب النموذجُ أصنافَه بيده. فصارت كتلاً
 * يتحقّق المخطّط منها: **مرحلةٌ بلا مخطّط لا يُمسك خطؤها**، ولا حارس لها
 * إلّا منقٍّ يُسقط ولا يُخطئ.
 */
it('asks for no schema from the prose stage alone', function (): void {
    expect(StageSchemas::for(Stage::Cleaning))->toBeNull()
        ->and(StageSchemas::for(Stage::Writing))->not->toBeNull()
        ->and(StageSchemas::for(Stage::ExtractingStructure))->not->toBeNull()
        ->and(StageSchemas::for(Stage::ExtractingEvidence))->not->toBeNull();
});

// ★ T-71 — المساران اختياريّان كالمقارنة: درسٌ بلا طريقين درسٌ صحيح.
it('accepts a structure with two paths and one without', function (): void {
    $schema = StageSchemas::for(Stage::ExtractingStructure);
    $base = ['title_ar' => 'عنوان الدرس', 'core_concept' => 'السكينة', 'axes' => [['name' => 'في العبادة']]];
    $path = ['title' => 'طريق المداوم', 'nodes' => ['عمل قليل', 'قصد وتدرّج', 'ثبات'], 'final' => 'سكينة'];

    expect(JsonSchema::violations($base, $schema))->toBe([])
        ->and(JsonSchema::violations([...$base, 'paths' => null], $schema))->toBe([])
        ->and(JsonSchema::violations([...$base, 'paths' => ['good' => $path, 'bad' => $path]], $schema))->toBe([])
        // والعُقدُ نصوصٌ لا غير — فالكاتبُ ينقلها إلى كتلة `paths` بلا تحويل.
        ->and(JsonSchema::violations([...$base, 'paths' => ['good' => [...$path, 'nodes' => [['x']]]]], $schema))->not->toBe([]);
});
