<?php

declare(strict_types=1);

use App\Actions\Stages\TranslateSummary;
use App\Contracts\ModelGateway;
use App\Enums\Locale;
use App\Enums\Stage;
use App\Models\Lecture;
use App\Models\QuranTranslation;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Support\Arabic;
use App\Support\Model\ModelResponse;
use Database\Seeders\ModelConfigSeeder;
use Illuminate\Support\Facades\Log;

/*
 * لا ترجمةَ مدفوعةٌ تُعاد، ولا ترجمةَ آيةٍ تُمحى — T-141.
 *
 * ★★ **والضررُ كان واقعاً على الخادم قبل هذه المهمّة.** فزرُّ «حدّثْ
 * المنشور» كان ينادي {@see TranslateSummary} لكلّ لغةٍ في كلّ ضغطة، فصُرف
 * على ملخّصين ~$0.65 في ضغطةٍ واحدة — **وخرجا بآياتٍ عاريةٍ من ترجمتها**،
 * لأنّ `quran_translations` لم يكن مبذوراً هناك.
 *
 * فالمقيسُ هنا أمران: ألّا يُنادى النموذجُ لما تُرجم، وأن تعود الآياتُ
 * بلا فلس.
 */

beforeEach(function (): void {
    // الترجمةُ مرحلةٌ تُنادى، فتحتاج صفَّها — والبوّابةُ الوهميّة هي
    // الافتراض (CLAUDE.md §2 القاعدة السابعة)، فلا نداءَ حقيقيّ.
    (new ModelConfigSeeder)->run();

    $this->tenant = Tenant::factory()->create(['slug' => 'tenant-a']);

    /*
     * ★ **والنداءاتُ تُعدّ على البوّابة لا في `model_calls`.**
     *
     * فـ{@see \App\Services\Model\ModelCallRecorder} لا يكتب صفّاً لكلفةٍ
     * صفرية، والبوّابةُ الوهميّة كلفتُها صفر. **والمقيسُ هنا النداءُ نفسُه**
     * — هو ما يُدفع ثمنُه في الإنتاج — فيُلفّ العقدُ بعدّادٍ يُحصيه.
     */
    $this->calls = new class(app(ModelGateway::class)) implements ModelGateway
    {
        /** @var array<string, int> */
        public array $perStage = [];

        public function __construct(private readonly ModelGateway $inner) {}

        public function call(Stage $stage, array $messages, ?array $schema = null, ?SummaryJob $job = null): ModelResponse
        {
            $this->perStage[$stage->value] = ($this->perStage[$stage->value] ?? 0) + 1;

            return $this->inner->call($stage, $messages, $schema, $job);
        }
    };

    app()->instance(ModelGateway::class, $this->calls);
});

/** مهمّةٌ بمتنٍ فيه فقرةٌ واحدة، جاهزةٌ للترجمة. */
function translatableJob(Tenant $tenant, string $text = 'متنُ الفقرة.'): SummaryJob
{
    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar', 'en'],
    ]);

    return SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => $text]]]]],
        'body_html' => '<p class="lead">فقرة.</p>',
    ])->fresh();
}

/** نداءاتُ مرحلة الترجمة التي بلغت البوّابة. */
function translationCalls(SummaryJob $job): int
{
    return test()->calls->perStage[Stage::Translating->value] ?? 0;
}

it('لا ينادي النموذج ثانيةً لمتنٍ لم يتبدّل', function (): void {
    $job = translatableJob($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);

    expect(translationCalls($job))->toBe(1);

    /*
     * ★★ **وهذه هي الضغطةُ الثانية على «حدّثْ المنشور».**
     *
     * وكانت تصرف نداءً كاملاً على عملٍ محفوظٍ في الجدول — مالٌ يُدفع مرّتين
     * لنصٍّ واحد.
     */
    app(TranslateSummary::class)->handle($job->fresh(), Locale::En);

    expect(translationCalls($job))->toBe(1);
});

it('يترجم من جديد متناً بُدّل، فلا تُعرض ترجمةٌ لا تطابق صفحتَها', function (): void {
    $job = translatableJob($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);

    expect(translationCalls($job))->toBe(1);

    /*
     * ★ **والشرطُ تطابقُ البصمة لا وجودُ الصفّ** — T-141. فملخّصٌ أُعيدت
     * كتابتُه متنُه غيرُ متنِه، **وترجمةٌ قديمةٌ تُعرض له أسوأ من نداءٍ
     * يُدفع**: صفحةٌ بلغةٍ لا تطابق أصلَها، ولا أحدَ يُخبر بذلك.
     */
    $job->forceFill([
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [['type' => 'paragraph', 'text' => 'متنٌ آخرُ تماماً.']]]]],
    ])->save();

    app(TranslateSummary::class)->handle($job->fresh(), Locale::En);

    expect(translationCalls($job))->toBe(2);
});

it('يجدّد الترجمة حين يُطلب صريحاً', function (): void {
    $job = translatableJob($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);
    app(TranslateSummary::class)->handle($job->fresh(), Locale::En, force: true);

    expect(translationCalls($job))->toBe(2);
});

it('يترجم مرّةً أخيرةً صفّاً بلا بصمة، ثمّ يستقرّ', function (): void {
    $job = translatableJob($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);

    /*
     * صفوفُ ما قبل T-141 لا بصمةَ لها. **ونسبتُها إلى بصمةٍ محسوبةٍ اليوم
     * تفترض أنّ متنَها لم يتبدّل قطّ** — وهو ما لا نعلمه، فتُترجَم مرّةً.
     */
    SummaryTranslation::query()->withoutGlobalScopes()
        ->where('summary_job_id', $job->id)
        ->update(['source_hash' => null]);

    app(TranslateSummary::class)->handle($job->fresh(), Locale::En);

    expect(translationCalls($job))->toBe(2);

    // ثمّ تستقرّ: البصمةُ كُتبت في النداء الثاني.
    app(TranslateSummary::class)->handle($job->fresh(), Locale::En);

    expect(translationCalls($job))->toBe(2);
});

/*
 * ═══ عودةُ ترجمات الآيات بلا فلس ═══
 *
 * والمقيسُ الضررُ الذي وقع بعينه: بيئةٌ لم يُبذَر فيها الجدول كتبت
 * `body_html` بلا ترجماتِ آيات، **فمحت ما كان**. ولا يُصلحها إعادةُ الرسم:
 * العطبُ في الصفّ المحفوظ.
 */

/** آيةٌ في المتن، بشاهدٍ مثبَّتٍ لها — النحل ٩٧. */
function jobWithAyah(Tenant $tenant): SummaryJob
{
    $text = 'مَنْ عَمِلَ صَالِحًا';

    $lecture = Lecture::factory()->create([
        'tenant_id' => $tenant->id,
        'title_ar' => 'عنوان الدرس',
        'locales' => ['ar', 'en'],
    ]);

    $job = SummaryJob::factory()->for($lecture)->create([
        'tenant_id' => $tenant->id,
        'structure_json' => ['title_ar' => 'عنوان الدرس'],
        'body_json' => ['sections' => [['heading' => 'المقدّمة', 'blocks' => [
            ['type' => 'evidence', 'kind' => 'ayah', 'text' => $text],
        ]]]],
        'body_html' => '<p class="lead">فقرة.</p>',
    ])->fresh();

    $job->evidenceItems()->create([
        'tenant_id' => $tenant->id,
        'kind' => 'ayah',
        'raw_text' => $text,
        'normalized_text' => Arabic::normalize($text),
        'matched_text' => $text,
        'match_status' => 'exact',
        'review_status' => 'approved',
        'source_meta' => ['surah_number' => 16, 'ayah_number' => 97],
    ]);

    return $job->fresh();
}

it('يعيد ترجمات الآيات بعد بذر الجدول، بلا نداء نموذج', function (): void {
    $job = jobWithAyah($this->tenant);

    // ★ **الجدولُ فارغ** — وهو حالُ الخادم يوم وقع الضرر.
    app(TranslateSummary::class)->handle($job, Locale::En);

    $stored = SummaryTranslation::query()->withoutGlobalScopes()
        ->where('summary_job_id', $job->id)->sole();

    expect($stored->body_html)->not->toContain('ayah-tr');

    $calls = translationCalls($job);

    // ثمّ يُبذَر، كما فُعل على الخادم بعد البلاغ.
    QuranTranslation::query()->create([
        'locale' => 'en',
        'surah' => 16,
        'ayah' => 97,
        'text' => 'Whoever does righteousness, whether male or female…',
        'translation_id' => 20,
    ]);

    // وإعادةُ النشر تُصلحها الآن — **ولا نداءَ يُصرف**.
    app(TranslateSummary::class)->handle($job->fresh(), Locale::En);

    expect($stored->fresh()->body_html)->toContain('ayah-tr')
        ->and($stored->fresh()->body_html)->toContain('Whoever does righteousness')
        ->and(translationCalls($job))->toBe($calls);
});

it('يصلح الترجمات المحفوظة كلَّها بأمرٍ واحد، بلا فلس', function (): void {
    $job = jobWithAyah($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);

    $calls = translationCalls($job);

    QuranTranslation::query()->create([
        'locale' => 'en',
        'surah' => 16,
        'ayah' => 97,
        'text' => 'Whoever does righteousness, whether male or female…',
        'translation_id' => 20,
    ]);

    $this->artisan('khulasah:rebuild-translation-html')
        ->expectsOutputToContain('refresh-pages')
        ->assertExitCode(0);

    $stored = SummaryTranslation::query()->withoutGlobalScopes()
        ->where('summary_job_id', $job->id)->sole();

    expect($stored->body_html)->toContain('Whoever does righteousness')
        // **ولا فلسٌ صُرف**: الأمرُ حسابٌ محضٌ على بياناتٍ عندنا.
        ->and(translationCalls($job))->toBe($calls);
});

it('لا يكتب شيئاً في التجريب', function (): void {
    $job = jobWithAyah($this->tenant);

    app(TranslateSummary::class)->handle($job, Locale::En);

    QuranTranslation::query()->create([
        'locale' => 'en', 'surah' => 16, 'ayah' => 97,
        'text' => 'Whoever does righteousness…', 'translation_id' => 20,
    ]);

    $this->artisan('khulasah:rebuild-translation-html', ['--dry-run' => true])->assertExitCode(0);

    $stored = SummaryTranslation::query()->withoutGlobalScopes()
        ->where('summary_job_id', $job->id)->sole();

    expect($stored->body_html)->not->toContain('ayah-tr');
});

it('يقيّد في السجلّ أنّ الجدول غير مبذور، فلا يسقط صامتاً', function (): void {
    $job = jobWithAyah($this->tenant);

    $records = [];

    Log::listen(function ($message) use (&$records): void {
        $records[] = $message;
    });

    app(TranslateSummary::class)->handle($job, Locale::En);

    /*
     * ★★ **وصمتُها هو سببُ أنّ العطب مضى بلا أن يُرى** حتى بلّغ مالكُ
     * المنتج. فسطرٌ واحدٌ يقول العلّةَ ويقول أمرَ إصلاحها.
     */
    $warning = collect($records)->first(
        static fn ($record): bool => str_contains((string) $record->message, 'locale_not_seeded')
    );

    expect($warning)->not->toBeNull()
        ->and($warning->context['locale'])->toBe('en')
        ->and($warning->context['fix'])->toContain('khulasah:seed-quran-translations');
});
