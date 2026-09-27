<?php

declare(strict_types=1);

use App\Actions\Stages\GuardEvidenceText;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\Tenant;

/*
 * T-73: الحاجزُ الحتميّ بعد الكتابة — CLAUDE.md §2 الحدّ الثالث.
 *
 * `BodyBlocks::evidence()` كانت ترسم `text` كما كتبه نموذجُ الكتابة بلا
 * مقارنةٍ بالشاهد المثبَّت. وهذا الحارس يسدّ الفجوة: **بلا نموذج**،
 * مطابقةً بعد `Arabic::normalize()` فقط.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);
});

function evidenceBlocks(array $evidenceBlock): array
{
    return [
        'sections' => [
            [
                'heading' => 'عنوان',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'فقرةٌ لا شاهد فيها.'],
                    $evidenceBlock,
                ],
            ],
        ],
        'closing' => 'خاتمة',
    ];
}

// معيار القبول الثالث: «اختبارٌ بشاهدٍ غُيّرت فيه كلمة».
it('replaces a reworded evidence with the settled wording, verbatim', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'matched_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        // كلمةٌ واحدة بُدّلت: «بالنيات» ← «بالنوايا».
        'text' => 'إنما الأعمال بالنوايا وإنما لكل امرئ ما نوى',
        'source' => 'رواه البخاري ومسلم',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    $evidence = $guarded['sections'][0]['blocks'][1];

    expect($evidence['text'])->toBe('إنما الأعمال بالنيات وإنما لكل امرئ ما نوى')
        // وباقي الكتلة يبقى كما كتبه النموذج — المصدرُ وحده وسمُ التوثيق.
        ->and($evidence['source'])->toBe('رواه البخاري ومسلم')
        ->and($guarded['sections'][0]['blocks'])->toHaveCount(2);
});

// وشاهدٌ طابق حرفاً بحرف يبقى كما هو — لا يُسقط الحارسُ ما لم يُخالَف فيه شيء.
it('leaves an exactly matching evidence untouched', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'matched_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        'text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    expect($guarded['sections'][0]['blocks'][1]['text'])->toBe('من حسن إسلام المرء تركه ما لا يعنيه');
});

// معيار القبول الثالث: «وآخرُ بشاهدٍ لم يصل الكاتبَ أصلاً».
it('drops an evidence block that matches no settled item at all', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'matched_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        // حديثٌ آخر كلّياً، لم يكن بين شواهد هذه المهمّة المثبَّتة.
        'text' => 'من كذب علي متعمدا فليتبوأ مقعده من النار',
        'source' => 'رواه البخاري',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    // تسقط كتلة الشاهد وحدها، والفقرة إلى جانبها تبقى.
    expect($guarded['sections'][0]['blocks'])->toHaveCount(1)
        ->and($guarded['sections'][0]['blocks'][0]['type'])->toBe('paragraph');
});

// خطرُ الالتباس: شاهدان مثبَّتان متقاربا اللفظ، وكتلةٌ مشوَّهة تقع بينهما
// بفارقٍ لا يحسم أيّهما المقصود. الأسلمُ إسقاطُها لا التخمين.
it('drops an evidence block that is ambiguous between two close settled items', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'matched_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'من حسن إسلام المرء تركه الكلام فيما لا يعنيه',
        'matched_text' => 'من حسن إسلام المرء تركه الكلام فيما لا يعنيه',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        // نصٌّ مشوَّه يقع قريباً من الاثنين معاً بفارقٍ لا يحسم.
        'text' => 'من حسن إسلام المرء تركه شيء لا يعنيه',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    expect($guarded['sections'][0]['blocks'])->toHaveCount(1)
        ->and($guarded['sections'][0]['blocks'][0]['type'])->toBe('paragraph');
});

// وتطابقٌ شبه حرفيّ يبقى حاسماً ولو قارَبه شاهدٌ آخر — لا يُبتلع بالالتباس.
it('accepts a near-exact match even when another settled item is somewhat close', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'matched_text' => 'من حسن إسلام المرء تركه ما لا يعنيه',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'من حسن إيمان المرء تركه ما لا يعنيه',
        'matched_text' => 'من حسن إيمان المرء تركه ما لا يعنيه',
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        // طابقُ الشاهدَ الأوّل حرفاً بحرف بعد التطبيع.
        'text' => 'من حسن اسلام المرء تركه ما لا يعنيه',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    expect($guarded['sections'][0]['blocks'])->toHaveCount(2)
        ->and($guarded['sections'][0]['blocks'][1]['text'])->toBe('من حسن إسلام المرء تركه ما لا يعنيه');
});

// شاهدٌ مُحذوف لا يُستعمل مرجعاً — حُسم إخراجه بحذفه، فلا يعود من هذا الباب.
it('does not match against a removed evidence item', function (): void {
    EvidenceItem::factory()->create([
        'summary_job_id' => $this->job->id,
        'tenant_id' => $this->tenant->id,
        'raw_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'matched_text' => 'إنما الأعمال بالنيات وإنما لكل امرئ ما نوى',
        'review_status' => ReviewStatus::Removed,
    ]);

    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        'text' => 'إنما الأعمال بالنوايا وإنما لكل امرئ ما نوى',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    expect($guarded['sections'][0]['blocks'])->toHaveCount(1);
});

// وما ليس شاهداً لا يمسّه الحارس.
it('leaves non-evidence blocks untouched', function (): void {
    $blocks = evidenceBlocks([
        'type' => 'evidence',
        'kind' => 'hadith',
        'text' => 'نصٌّ لا يقابل شيئاً',
    ]);

    $guarded = app(GuardEvidenceText::class)->handle($this->job, $blocks);

    expect($guarded['sections'][0]['blocks'][0])->toBe(['type' => 'paragraph', 'text' => 'فقرةٌ لا شاهد فيها.']);
});
