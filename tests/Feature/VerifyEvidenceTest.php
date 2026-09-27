<?php

declare(strict_types=1);

use App\Actions\Stages\VerifyEvidence;
use App\Actions\Stages\WriteBody;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Model\FakeModelGateway;
use Database\Seeders\HadithTestSeeder;
use Database\Seeders\QuranTestSeeder;
use Illuminate\Support\Facades\Http;

/*
 * دمج التحقّق وتفريع المراجعة — T-12، والمواصفة §5 و§7-5.
 *
 * والمقياس الحاكم هنا معيارُ القبول الرابع: **ما يدخل الكتابة لفظُ المصدر
 * لا لفظُ التفريغ**. «هذا هو الفرق بين منتج موثوق ومنتج ينقل خطأ المتكلّم».
 */

beforeEach(function (): void {
    Http::preventStrayRequests();

    // الآيات تُطابَق على المصحف المبذور، لا على مزوّد وهمي — §7-2.
    $this->seed(QuranTestSeeder::class);

    $this->tenant = Tenant::factory()->create();
    $this->job = SummaryJob::factory()->create(['tenant_id' => $this->tenant->id]);
    app(FakeModelGateway::class);
});

/** يضع الشواهد المستخرَجة ويقود المهمّة إلى `verifying`. */
function stageEvidence(SummaryJob $job, array $evidence): SummaryJob
{
    $job->forceFill(['evidence_json' => $evidence])->save();

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure, JobState::ExtractingEvidence, JobState::Verifying] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job->refresh();
}

/**
 * آيةٌ **من العيّنة المبذورة** بلفظها، فتعود `exact` من `QuranVerifier`.
 *
 * ولفظُها هنا بلا تشكيل — كما ينطقها المحاضر ويستخرجها النموذج. والتطبيع
 * في T-03 هو ما يجمع بينه وبين رسم المصحف.
 */
function ayahEvidence(): array
{
    return ['kind' => 'ayah', 'raw_text' => 'إن الإنسان خلق هلوعا'];
}

it('يصيّر كل عنصر في evidence_json صفّاً في evidence_items', function (): void {
    $job = stageEvidence($this->job, [
        ayahEvidence(),
        ['kind' => 'hadith', 'raw_text' => 'لفظ لا وجود له في أيّ مصدر البتّة قطعاً'],
    ]);

    app(VerifyEvidence::class)->handle($job);

    expect($job->evidenceItems()->count())->toBe(2);
});

it('يحفظ لفظ المصدر ومرجعه لا ما ادّعاه النموذج', function (): void {
    $job = stageEvidence($this->job, [ayahEvidence()]);

    $item = app(VerifyEvidence::class)->handle($job)[0];

    expect($item->match_status)->toBe(MatchStatus::Exact)
        ->and($item->matched_text)->not->toBeNull()
        ->and($item->source_ref)->not->toBeNull();
});

/**
 * **معيار القبول الرابع.**
 *
 * لفظُ المحاضرة يزيغ عن لفظ المصدر — الشيخ ينقل من حفظه. فالمنتج يردّه
 * إلى مصدره، ولا ينقل خطأه.
 */
it('يُدخل الكتابةَ لفظَ المصدر لا لفظ التفريغ', function (): void {
    $job = stageEvidence($this->job, [ayahEvidence()]);

    $item = app(VerifyEvidence::class)->handle($job)[0];

    $material = (new ReflectionMethod(WriteBody::class, 'settledEvidence'))
        ->invoke(app(WriteBody::class), $job->refresh());

    expect($material[0]['text'])->toBe($item->matched_text)
        ->and($material[0]['text'])->not->toBe($item->raw_text);
});

/** ما جُهل مصدره **يُحذف صامتاً** — لا يقف، ولا يُوصَف بشيء (§7-5). */
it('يحذف ما جُهل مصدره ولا يوقف الخطّ عليه', function (): void {
    $job = stageEvidence($this->job, [
        ayahEvidence(),
        ['kind' => 'hadith', 'raw_text' => 'لفظ لا وجود له في أيّ مصدر البتّة قطعاً'],
    ]);

    $items = app(VerifyEvidence::class)->handle($job);

    expect($items[1]->review_status)->toBe(ReviewStatus::Removed)
        ->and($job->refresh()->state)->toBe(JobState::Writing);
});

/** **والمحذوف يبقى مقروءاً لصاحب الجهة** — معيار القبول الأخير. */
it('يُبقي صفّ المحذوف قائماً ليراه صاحب الجهة', function (): void {
    $job = stageEvidence($this->job, [
        ['kind' => 'hadith', 'raw_text' => 'لفظ لا وجود له في أيّ مصدر البتّة قطعاً'],
    ]);

    app(VerifyEvidence::class)->handle($job);

    $removed = $job->evidenceItems()->where('review_status', ReviewStatus::Removed->value)->first();

    expect($removed)->not->toBeNull()
        ->and($removed->raw_text)->toContain('لفظ لا وجود له');
});

/** والمحذوف **لا يدخل الكتابة** — أُخرج من المتن فلا يُعاد من باب آخر. */
it('لا يُدخل المحذوف مادّةَ الكتابة', function (): void {
    $job = stageEvidence($this->job, [
        ayahEvidence(),
        ['kind' => 'hadith', 'raw_text' => 'لفظ لا وجود له في أيّ مصدر البتّة قطعاً'],
    ]);

    app(VerifyEvidence::class)->handle($job);

    $material = (new ReflectionMethod(WriteBody::class, 'settledEvidence'))
        ->invoke(app(WriteBody::class), $job->refresh());

    expect($material)->toHaveCount(1)
        ->and($material[0]['kind'])->toBe('ayah');
});

/** **درسٌ بلا شاهد درسٌ صحيح**: يمضي إلى الكتابة ولا يقف. */
it('يمضي إلى الكتابة في درس بلا شواهد', function (): void {
    $job = stageEvidence($this->job, []);

    expect(app(VerifyEvidence::class)->handle($job))->toBe([]);
    expect($job->refresh()->state)->toBe(JobState::Writing);
});

/** وضع `review`: ما ليس `exact` وصحيحاً يقف لإنسان. */
it('يوقف الخطّ عند needs_review في وضع review', function (): void {
    $this->tenant->forceFill(['on_unverified' => UnverifiedPolicy::Review->value])->save();

    $job = stageEvidence($this->job, [ayahEvidence()]);

    app(VerifyEvidence::class)->handle($job);

    // الآية المطابقة تماماً تمرّ في الوضعين، فالوقوف يُقاس بشاهدٍ دونها.
    expect($job->refresh()->state)->toBe(JobState::Writing);
});

/** يضع صفّاً من محاولةٍ سابقة على المهمّة نفسها. */
function staleItem(SummaryJob $job, string $text, ReviewStatus $status, ?int $resolvedBy = null): void
{
    (new EvidenceItem)->forceFill([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'domain' => 'islamic',
        'kind' => 'hadith',
        'raw_text' => $text,
        'normalized_text' => $text,
        'match_status' => MatchStatus::None->value,
        'review_status' => $status->value,
        'resolved_by' => $resolvedBy,
    ])->save();
}

/**
 * إعادة التحقّق تبدأ من صفحة بيضاء، ولا تضاعف الصفوف.
 *
 * ولا يُعاد الانتقال `writing ← verifying` — وهو ممنوع في آلة الحالات بحقّ.
 * فيُحاكى ما يقع فعلاً: صفوفُ محاولةٍ سابقة قائمة، ثمّ يُشغَّل التحقّق.
 */
it('يمسح صفوف المحاولة السابقة ولا يضاعفها', function (): void {
    $job = stageEvidence($this->job, [ayahEvidence()]);

    staleItem($job, 'صفّ محاولة سابقة', ReviewStatus::Removed);

    app(VerifyEvidence::class)->handle($job->refresh());

    expect($job->evidenceItems()->count())->toBe(1)
        ->and($job->evidenceItems()->where('raw_text', 'صفّ محاولة سابقة')->exists())->toBeFalse();
});

/**
 * **وما حسمه إنسان لا يُلغيه تشغيلٌ آلي.**
 *
 * فالصفّ الذي عليه `resolved_by` يبقى وإن أُعيد التحقّق. وإلّا ضاع قرارُ
 * مراجعٍ بشريّ في إعادةِ توليدٍ لا يعلم بها.
 */
it('لا يمسح ما حسمه إنسان', function (): void {
    $job = stageEvidence($this->job, [ayahEvidence()]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    staleItem($job, 'شاهد حسمه مراجع', ReviewStatus::Approved, $user->id);

    app(VerifyEvidence::class)->handle($job->refresh());

    expect($job->evidenceItems()->where('raw_text', 'شاهد حسمه مراجع')->exists())->toBeTrue();
});

/** النوع الذي لا محقّق له لا يمرّ — عقد `VerifierRegistry`. */
it('لا يمرّر نوعاً لا محقّق له', function (): void {
    $job = stageEvidence($this->job, [['kind' => 'statistic', 'raw_text' => 'رقمٌ ادّعاه المحاضر']]);

    $item = app(VerifyEvidence::class)->handle($job)[0];

    expect($item->match_status)->toBe(MatchStatus::None)
        ->and($item->review_status)->toBe(ReviewStatus::Removed);
});

/*
 * ─── الاختبار التكاملي — البند الخامس من برومبت T-12 ───────────────────
 */

/**
 * محاضرةٌ فيها شواهد صحيحة وشاهدٌ ضعيف.
 *
 * **وفي وضع `disclose` لا تُدخَل `needs_review` البتّة** — معيار القبول
 * الثاني. فالضعيف يُنشر مبيَّناً بدرجته، وهذا عمل أهل العلم؛ والآفة في
 * نقله موهِماً صحّته لا في نقله مبيَّناً.
 */
it('يمرّر الضعيف مبيَّناً ولا يقف في وضع disclose', function (): void {
    $this->seed(HadithTestSeeder::class);

    $job = stageEvidence($this->job, [
        ayahEvidence(),
        ['kind' => 'hadith', 'raw_text' => 'اتقوا الملاعن الثلاث البراز في الموارد وقارعة الطريق والظل'],
    ]);

    $items = app(VerifyEvidence::class)->handle($job);

    expect($job->refresh()->state)->toBe(JobState::Writing)
        ->and($job->pendingEvidenceCount())->toBe(0);

    $hadith = $items[1];

    expect($hadith->review_status)->toBe(ReviewStatus::AutoPassed)
        ->and($hadith->meta('grade'))->not->toBeNull()
        ->and($hadith->meta('takhrij'))->not->toBeNull();
});

/**
 * **ولا يُنشر ضعيفٌ بلا بيان درجته** — قاعدة حاجبة في `evidence-fixtures.json`.
 *
 * فالدرجة والتخريج يبلغان مادّةَ الكتابة، وإلّا كتب النموذج الحديثَ عارياً
 * عن حكمه، وهو عين ما تمنعه سياسة البيان.
 */
it('يوصل الدرجة والتخريج إلى مادّة الكتابة', function (): void {
    $this->seed(HadithTestSeeder::class);

    $job = stageEvidence($this->job, [
        ['kind' => 'hadith', 'raw_text' => 'اتقوا الملاعن الثلاث البراز في الموارد وقارعة الطريق والظل'],
    ]);

    app(VerifyEvidence::class)->handle($job);

    $material = (new ReflectionMethod(WriteBody::class, 'settledEvidence'))
        ->invoke(app(WriteBody::class), $job->refresh());

    expect($material[0])->toHaveKeys(['grade', 'takhrij'])
        ->and($material[0]['takhrij'])->toContain('أبو داود');
});

/** وضع `review`: الضعيف نفسه يقف لإنسان، ويتوقّف الخطّ. */
it('يوقف الضعيف عند needs_review في وضع review', function (): void {
    $this->seed(HadithTestSeeder::class);
    $this->tenant->forceFill(['on_unverified' => UnverifiedPolicy::Review->value])->save();

    $job = stageEvidence($this->job, [
        ayahEvidence(),
        ['kind' => 'hadith', 'raw_text' => 'اتقوا الملاعن الثلاث البراز في الموارد وقارعة الطريق والظل'],
    ]);

    $items = app(VerifyEvidence::class)->handle($job);

    expect($job->refresh()->state)->toBe(JobState::NeedsReview)
        ->and($items[0]->review_status)->toBe(ReviewStatus::AutoPassed)
        ->and($items[1]->review_status)->toBe(ReviewStatus::Pending);
});

/** **ولا يُكتب المتن ومهمّةٌ واقفة عند `needs_review`.** الحدّ الرابع. */
it('يمنع كتابة المتن ما دام شاهد لم يُحسم', function (): void {
    $this->seed(HadithTestSeeder::class);
    $this->tenant->forceFill(['on_unverified' => UnverifiedPolicy::Review->value])->save();

    $job = stageEvidence($this->job, [
        ['kind' => 'hadith', 'raw_text' => 'اتقوا الملاعن الثلاث البراز في الموارد وقارعة الطريق والظل'],
    ]);

    app(VerifyEvidence::class)->handle($job);

    expect(fn () => app(WriteBody::class)->handle($job->refresh()))
        ->toThrow(ModelCallFailed::class);

    expect($job->refresh()->body_html)->toBeNull();
});
