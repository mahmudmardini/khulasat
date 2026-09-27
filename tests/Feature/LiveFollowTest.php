<?php

declare(strict_types=1);

use App\Domain\Summary\JobState;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;

/*
 * شاشة المتابعة الحيّة — T-83، ونُقّحت في T-92.
 *
 * ★ **والمقياس الحاكم: ما يُعرض حقيقيٌّ كلُّه.** ملامحُ من البنية كما
 * استُخرجت، وحصيلةٌ بأرقامها — **لا نداءَ نموذج** ولا نصَّ آيةٍ أو حديثٍ
 * قبل التحقّق. و«من نصّ الدرس» أُزيلت بملاحظة مالك المنتج (T-92).
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->owner()->for_($this->tenant)->create();
});

function liveJob(JobState $state, array $attributes = []): SummaryJob
{
    /** @var Tenant $tenant */
    $tenant = test()->tenant;

    return SummaryJob::factory()->inState($state)->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => Lecture::factory()->create(['tenant_id' => $tenant->id])->id,
        ...$attributes,
    ]);
}

/** @return array<string, mixed> */
function outline(): array
{
    return [
        'title_ar' => 'عنوان آخر',
        'core_concept' => 'القرآن المرجعية العليا في مجالات الحياة',
        'axes' => [
            ['name' => 'هداية العقل', 'summary' => 'يضبط التفكير بميزان الوحي'],
            ['name' => 'هداية السلوك'],
        ],
        // ★ نصُّ آيةٍ كتبه النموذج ولم يُطابَق بعدُ بالمصحف.
        'key_ayah' => ['text' => 'إن هذا القرآن يهدي للتي هي أقوم', 'surah' => 'الإسراء', 'ayah_number' => 9],
        // سقالةٌ لصياغة المرحلة الخامسة، لا نصٌّ للعرض — ContentObject.
        'closing_line' => 'سطرُ ختامٍ للكاتب وحده',
    ];
}

it('shows no outline before the structure exists, but counts the transcript', function (): void {
    $job = liveJob(JobState::Cleaning, ['transcript_text' => 'نصٌّ مفرَّغ من الدرس.', 'transcript_word_count' => 693]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertOk()
        ->assertJsonPath('job.live.highlights', [])
        ->assertJsonPath('job.live.word_count', 693)
        ->assertJsonPath('job.live.evidence_count', null);
});

it('no longer quotes the raw transcript', function (): void {
    // ترجماتُ يوتيوب بلا ترقيمٍ ولا همزات تُقرأ ضجيجاً — ملاحظة مالك المنتج، T-92.
    $job = liveJob(JobState::ExtractingStructure, ['transcript_text' => 'احيانا نحن ننشغل بتوليد المعاني الزائده المتعلقه بالاسم']);

    $response = $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertOk()
        ->assertJsonMissingPath('job.live.excerpts');

    expect(json_encode($response->json('job.live'), JSON_UNESCAPED_UNICODE))->not->toContain('ننشغل');
});

it('shows the lecture outline once structured, and never the unverified ayah', function (): void {
    $job = liveJob(JobState::ExtractingEvidence, ['structure_json' => outline()]);

    $response = $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")->assertOk();

    expect($response->json('job.live.highlights'))->toBe([
        ['kind' => 'concept', 'text' => 'القرآن المرجعية العليا في مجالات الحياة', 'detail' => null],
        ['kind' => 'axis', 'text' => 'هداية العقل', 'detail' => 'يضبط التفكير بميزان الوحي'],
        ['kind' => 'axis', 'text' => 'هداية السلوك', 'detail' => null],
    ]);

    $live = json_encode($response->json('job.live'), JSON_UNESCAPED_UNICODE);

    expect($live)->not->toContain('يهدي للتي هي أقوم')
        ->not->toContain('سطرُ ختامٍ');
});

it('counts the evidence rows once verification created them', function (): void {
    $job = liveJob(JobState::Writing, ['evidence_json' => [['raw_text' => 'أ'], ['raw_text' => 'ب']]]);
    EvidenceItem::factory()->count(3)->create(['summary_job_id' => $job->id]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertJsonPath('job.live.evidence_count', 3);
});

it('counts what was extracted while verification has not created rows yet', function (): void {
    // وإلّا قالت الشاشة «لم تُستخرج شواهد» والتحقّقُ يجري على شاهدين.
    $job = liveJob(JobState::Verifying, ['evidence_json' => [['raw_text' => 'أ'], ['raw_text' => 'ب']]]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertJsonPath('job.live.evidence_count', 2);
});

it('does not count evidence before extraction ran', function (): void {
    // الصفر يُقرأ «لا شواهد في الدرس» — خبرٌ لم يقع بعد.
    $job = liveJob(JobState::ExtractingStructure, ['evidence_json' => null]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertJsonPath('job.live.evidence_count', null);
});

it('keeps the tally but drops the outline once the job stops running', function (JobState $state): void {
    $job = liveJob($state, [
        'transcript_text' => 'نصٌّ مفرَّغ.',
        'transcript_word_count' => 693,
        'structure_json' => outline(),
        'evidence_json' => [['raw_text' => 'أ']],
    ]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertJsonPath('job.live.highlights', [])
        ->assertJsonPath('job.live.word_count', 693)
        ->assertJsonPath('job.live.evidence_count', 1);
})->with([
    'published' => JobState::Published,
    'failed' => JobState::Failed,
    'needs review' => JobState::NeedsReview,
]);

it('names the output languages the job will produce', function (): void {
    $job = liveJob(JobState::Writing);
    $job->lecture->update(['locales' => ['ar', 'en']]);

    $this->actingAs($this->user)->getJson("/panel/jobs/{$job->id}/status")
        ->assertJsonPath('job.live.locales', ['العربية', 'الإنجليزية']);
});
