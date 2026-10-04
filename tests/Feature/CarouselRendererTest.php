<?php

declare(strict_types=1);

use App\Actions\Render\RenderCarousel;
use App\Actions\Stages\CondenseForCarousel;
use App\Actions\Stages\RenderAndPublish;
use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Enums\VenueMode;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Model\FakeModelGateway;
use Illuminate\Support\Facades\Storage;

/*
 * عارض الكاروسيل — T-19، والمواصفة §8-أ، والمرحلة ٧ في حزمة التعليمات.
 *
 * **والقيد الحاكم هنا واحد:** الآيات والأحاديث تُنقل بلفظ مصادرها كاملةً،
 * ولا تُقتطع بحال. وما عداه — العدد، والأربعون كلمة، والهوية — تفاصيلُ
 * تخدمه. فأكثر هذا الملفّ عليه.
 */

/** لفظ الحديث كما في مصدره — وهو ما يجب أن يخرج على الشريحة كاملاً. */
const HADITH = 'احفظ الله يحفظك، احفظ الله تجده تجاهك، إذا سألت فاسأل الله، وإذا استعنت فاستعن بالله، '
    .'واعلم أن الأمة لو اجتمعت على أن ينفعوك بشيء لم ينفعوك إلا بشيء قد كتبه الله لك، '
    .'ولو اجتمعوا على أن يضروك بشيء لم يضروك إلا بشيء قد كتبه الله عليك، '
    .'رفعت الأقلام وجفت الصحف';

const AYAH = 'مَنْ عَمِلَ صَالِحًا مِنْ ذَكَرٍ أَوْ أُنْثَىٰ وَهُوَ مُؤْمِنٌ فَلَنُحْيِيَنَّهُ حَيَاةً طَيِّبَةً '
    .'وَلَنَجْزِيَنَّهُمْ أَجْرَهُم بِأَحْسَنِ مَا كَانُوا يَعْمَلُونَ';

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create([
        'name_ar' => 'جهة الاختبار',
        'name_ar_full' => 'جهة الاختبار الكاملة',
        'name_latin' => 'Tenant Name',
        'brand_kit' => [
            'palette' => 'indigo',
            'logo_data_uri' => 'data:image/svg+xml;base64,PHN2Zy8+',
        ],
    ]);

    $this->lecture = Lecture::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title_ar' => 'عنوان الدرس',
        'subtitle_ar' => 'عنوان فرعي',
        'speaker_name' => 'اسم الملقي',
        'speaker_title' => 'الشيخ',
        'venue_mode' => VenueMode::Institution->value,
    ]);

    $this->job = SummaryJob::factory()->for_($this->lecture)->inState(JobState::Published)->create([
        'structure_json' => [
            'title_ar' => 'عنوان الدرس',
            'subtitle_ar' => 'عنوان فرعي',
            'core_concept' => 'الحياة الطيبة أثرٌ للعمل الصالح.',
            'axes' => [['name' => 'المحور الأول'], ['name' => 'المحور الثاني']],
            'closing_line' => 'سطر الختام.',
        ],
    ]);

    // شاهدان محسومان بلفظ مصدرهما — وهما ما تُثبَّت عليه الشرائح.
    EvidenceItem::factory()->for_($this->job)->create([
        'kind' => 'ayah',
        'raw_text' => 'من عمل صالحا من ذكر أو أنثى وهو مؤمن فلنحيينه حياة طيبة',
        'matched_text' => AYAH,
        'source_ref' => 'النحل: ٩٧',
        'match_status' => MatchStatus::Exact,
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    EvidenceItem::factory()->for_($this->job)->create([
        'kind' => 'hadith',
        'raw_text' => 'احفظ الله يحفظك',
        'matched_text' => HADITH,
        'source_ref' => 'سنن الترمذي',
        'source_meta' => ['takhrij' => 'رواه الترمذي', 'grade' => 'hasan'],
        'match_status' => MatchStatus::Exact,
        'review_status' => ReviewStatus::AutoPassed,
    ]);

    $this->gateway = app(FakeModelGateway::class);
});

function carouselHtml(SummaryJob $job): string
{
    return app(RenderCarousel::class)->handle($job)->contents;
}

/** يقود المهمّة إلى `rendering` بالمسار الشرعي، فتصلح لـ`RenderAndPublish`. */
function pipelineReady(SummaryJob $job): SummaryJob
{
    $job->forceFill(['state' => JobState::Queued->value])->saveQuietly();

    foreach ([JobState::Transcribing, JobState::Cleaning, JobState::ExtractingStructure,
        JobState::ExtractingEvidence, JobState::Verifying, JobState::Writing, JobState::Rendering] as $state) {
        app(TransitionJob::class)->handle($job, $state);
    }

    return $job->refresh();
}

it('يرفض شريحةً تجاوز نصّها الحرّ أربعين كلمة ولا يقتطعها', function (): void {
    $this->gateway->willReturn(Stage::Carousel, 'overlong');

    expect(fn () => app(CondenseForCarousel::class)->handle($this->job))
        ->toThrow(ModelCallFailed::class);
});

it('يرفض عدداً دون الستّ', function (): void {
    $this->gateway->willReturn(Stage::Carousel, 'too_few');

    expect(fn () => app(CondenseForCarousel::class)->handle($this->job))
        ->toThrow(ModelCallFailed::class);
});

/*
 * ─── الحدّ الرابع: لا يُرسَم ما لم يُحسم ──────────────────────────────
 */

it('لا يرسم الشرائح وفي المهمّة شاهدٌ لم يُحسم', function (): void {
    EvidenceItem::factory()->for_($this->job)->pending()->create();

    expect(fn () => app(RenderCarousel::class)->handle($this->job))
        ->toThrow(RuntimeException::class);
});

it('لا يبني الكاروسيل حين لا تطلبه', function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $this->tenant->forceFill(['plan' => 'business'])->save();

    $job = pipelineReady($this->job);

    app(RenderAndPublish::class)->handle($job);

    expect($job->refresh()->outputs()->where('type', OutputType::Carousel->value)->exists())->toBeFalse();
});

/**
 * ★ **وإخفاقُ المخرَج الثاني لا يُسقط الأوّل.**
 *
 * فالصفحة نُشرت وبلغت المهمّةُ `published`. ورميُ الاستثناء يوقف مهمّةً تمّ
 * عملُها كلُّه من أجل شرائح، **ويُري الجهةَ ملخّصاً «متوقّفاً» وهو منشورٌ
 * يُخدَم على الرابط**.
 */
it('ينشر الصفحة ولو أخفق تكثيف الشرائح', function (): void {
    Storage::fake('public');
    config()->set('khulasah.publish.disk', 'public');

    $this->tenant->forceFill(['plan' => 'business'])->save();
    $this->lecture->forceFill(['want_carousel' => true])->save();

    // مخرَجٌ يخالف عقد المرحلة السابعة: أقلّ من ستّ شرائح.
    $this->gateway->willReturn(Stage::Carousel, 'too_few');

    $job = pipelineReady($this->job);

    $urls = app(RenderAndPublish::class)->handle($job);

    expect($urls)->toHaveKey(OutputType::Page->value)
        ->and($urls)->not->toHaveKey(OutputType::Carousel->value)
        ->and($job->refresh()->state)->toBe(JobState::Published)
        ->and($job->published_at)->not->toBeNull();
});

// ★ T-204 — صفحةُ الشرائح ومعاينتُها حُذفتا بلا تحويل (قرار @HasanSiwi): الشرائحُ
// في تبويبها بالمعاينة. فرابطُهما القديم «غير موجود»، لا «طريقةٌ غيرُ مسموحة».
it('لا صفحةَ شرائح منفصلة ولا معاينةَ لها', function (): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get("/panel/jobs/{$this->job->id}/carousel")->assertNotFound();
    $this->actingAs($user)->get("/panel/jobs/{$this->job->id}/carousel/preview")->assertNotFound();
});
