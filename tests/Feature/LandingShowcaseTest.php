<?php

declare(strict_types=1);

use App\Actions\Landing\FindShowcaseSummaryUrl;
use App\Domain\Summary\JobState;
use App\Enums\MatchStatus;
use App\Enums\OutputFormat;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;

/*
 * رابطُ المثال المنشور الحقيقيّ في صفحة التعريف — T-119.
 *
 * والمعرّفان (جهة وملخّص) في `config('khulasah.landing.showcase')` تُخصَّص
 * قيمتُهما لكلّ اختبار — لا يُعتمَد على بيانات `tenant-a` الحقيقية في
 * الاختبارات، فتلك بيانات بيئة التطوير وحدها ولا تُضمَن في CI.
 */

function makePublishedShowcase(string $tenantSlug, string $summarySlug, ?string $extraLocaleUrl = null): SummaryJob
{
    $tenant = Tenant::factory()->create(['slug' => $tenantSlug]);
    $lecture = Lecture::factory()->for_($tenant)->create(['speaker_name' => 'ملقي العرض']);
    $job = SummaryJob::factory()
        ->for_($lecture)
        ->inState(JobState::Published)
        ->create(['slug' => $summarySlug]);

    Output::create([
        'summary_job_id' => $job->id,
        'tenant_id' => $tenant->id,
        'type' => OutputType::Page,
        'locale' => 'ar',
        'format' => OutputFormat::Html,
        'public_url' => "https://{$tenantSlug}.khulasah.app/{$summarySlug}/index.html",
        'renderer_version' => '1',
    ]);

    if ($extraLocaleUrl !== null) {
        // لغةٌ ثانية — `unique(summary_job_id, type, locale)` تمنع تكرار
        // الأولى (T-38).
        Output::create([
            'summary_job_id' => $job->id,
            'tenant_id' => $tenant->id,
            'type' => OutputType::Page,
            'locale' => 'en',
            'format' => OutputFormat::Html,
            'public_url' => $extraLocaleUrl,
            'renderer_version' => '1',
        ]);
    }

    return $job;
}

beforeEach(function (): void {
    config([
        'khulasah.landing.showcase.tenant_slug' => 'showcase-tenant',
        'khulasah.landing.showcase.summary_slug' => 'showcase-summary',
    ]);
});

it('finds the public url of the configured published summary', function (): void {
    makePublishedShowcase('showcase-tenant', 'showcase-summary');

    $url = (new FindShowcaseSummaryUrl)->handle();

    expect($url)->toBe('https://showcase-tenant.khulasah.app/showcase-summary/index.html');
});

it('picks the shortest url, because that is the root — no language segment', function (): void {
    // رابطٌ أطول بمقطع لغة يجب ألّا يُفضَّل على رابط الجذر.
    makePublishedShowcase(
        'showcase-tenant',
        'showcase-summary',
        extraLocaleUrl: 'https://showcase-tenant.khulasah.app/showcase-summary/en/index.html',
    );

    $url = (new FindShowcaseSummaryUrl)->handle();

    expect($url)->toBe('https://showcase-tenant.khulasah.app/showcase-summary/index.html');
});

it('returns null quietly when the configured tenant does not exist', function (): void {
    // بيئةٌ جديدة (CI، قاعدة بيانات طازجة) بلا هذا الصفّ بعينه.
    expect((new FindShowcaseSummaryUrl)->handle())->toBeNull();
});

it('returns null for a summary that exists but is not published', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'showcase-tenant']);
    $lecture = Lecture::factory()->for_($tenant)->create();
    SummaryJob::factory()->for_($lecture)->inState(JobState::Queued)
        ->create(['slug' => 'showcase-summary']);

    expect((new FindShowcaseSummaryUrl)->handle())->toBeNull();
});

it('returns null for a summary that was unpublished', function (): void {
    $job = makePublishedShowcase('showcase-tenant', 'showcase-summary');
    $job->forceFill(['unpublished_at' => now()])->saveQuietly();

    expect((new FindShowcaseSummaryUrl)->handle())->toBeNull();
});

it('shows the real showcase link on the landing page when configured', function (): void {
    makePublishedShowcase('showcase-tenant', 'showcase-summary');

    $this->get('/')
        ->assertOk()
        ->assertSee('https://showcase-tenant.khulasah.app/showcase-summary/index.html', escape: false)
        ->assertSee('افتح الخلاصة المنشورة');
});

it('hides the showcase link gracefully when nothing is configured or found', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('افتح الخلاصة المنشورة')
        // بالمفتاح لا بنصّه: البديلُ نصٌّ تسويقيّ يتبدّل بمراجعة المحرّر (T-138)،
        // والمقيسُ هنا أنّه ظهر مكانَ رابط الخلاصة — لا بأيّ عبارةٍ ظهر.
        ->assertSee(__('landing.invite.alt_anatomy'));
});

/*
 * ─── لا نسبةَ إلى شركةٍ صانعة — T-153 ────────────────────────────────
 *
 * بطلب مالك المنتج: المنتجُ يُعرَف باسمه وحده، في الذيل وفي البيانات
 * المنظّمة وبلغات الصفحة الأربع.
 */
it('names no parent company anywhere on the landing page', function (string $path): void {
    $html = $this->get($path)->assertOk()->getContent();

    expect($html)->not->toContain('parentOrganization');
})->with(['/', '/en', '/tr', '/ru']);

/*
 * ─── المثالُ الحيّ في البطل وقسم «ما تحصل عليه» — T-143 ─────────────────
 *
 * قرارُ مالك المنتج، ١٦ أيلول ٢٠٢٦: الخلاصةُ المنشورة نفسُها تُعرض، لا
 * مواضعُ موصوفة. **والوعدُ الذي تُقدَّم برهاناً عليه** — كلُّ شاهدٍ بلفظ
 * مصدره — يُحرس هنا: لا يبلغ الصفحةَ شاهدٌ لم يُطابَق.
 */

function showcaseWithContent(): SummaryJob
{
    $job = makePublishedShowcase('showcase-tenant', 'showcase-summary');

    $job->forceFill(['structure_json' => [
        'title_ar' => 'عنوان معروض',
        'subtitle_ar' => 'عنوان فرعي معروض',
        'core_concept' => 'العلمُ ميراثُ الأنبياء، ومن أخذه أخذ بحظٍّ وافر.',
        'axes' => [
            ['name' => 'فضلُ العلم', 'summary' => 'يرفع اللهُ به أقواماً.'],
            ['name' => 'آدابُ الطالب', 'summary' => 'الإخلاصُ قبل الطلب.'],
        ],
    ]])->saveQuietly();

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'ayah',
        'raw_text' => 'قل هل يستوي الذين يعلمون',
        'matched_text' => 'قُلْ هَلْ يَسْتَوِي الَّذِينَ يَعْلَمُونَ وَالَّذِينَ لَا يَعْلَمُونَ',
        'source_ref' => 'سورة الزمر، الآية ٩',
        'source_meta' => ['surah_number' => 39, 'ayah_number' => 9],
    ]);

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'hadith',
        'matched_text' => 'مَن سلك طريقاً يلتمس فيه علماً سهّل اللهُ له به طريقاً إلى الجنّة',
        'source_ref' => 'صحيح مسلم، ٢٦٩٩',
        'source_meta' => ['takhrij' => 'رواه مسلم، رقم ٢٦٩٩'],
    ]);

    return $job;
}

it('shows the real published summary in the hero when one is configured', function (): void {
    showcaseWithContent();

    $this->get('/')
        ->assertOk()
        ->assertSee('عنوان معروض')
        ->assertSee('عنوان فرعي معروض')
        ->assertSee('ملقي العرض')
        ->assertSee('قُلْ هَلْ يَسْتَوِي الَّذِينَ يَعْلَمُونَ', false)
        ->assertSee('مَن سلك طريقاً يلتمس فيه علماً', false)
        ->assertSee(__('landing.hero.cta_real'))
        ->assertSee(__('landing.anatomy.note_real'))
        ->assertDontSee(__('landing.demo.speaker'))
        ->assertDontSee(__('landing.anatomy.note'));
});

it('never shows evidence that was not matched to its source', function (): void {
    $job = showcaseWithContent();

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'hadith',
        'raw_text' => 'حديثٌ أُثبت بقرارٍ بشريّ ولم يُطابَق',
        'matched_text' => null,
        'match_status' => MatchStatus::None,
        'review_status' => ReviewStatus::Approved,
    ]);

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'hadith',
        'matched_text' => 'لفظٌ طُوبِق جزئيّاً ثمّ صُحِّح',
        'match_status' => MatchStatus::Partial,
        'review_status' => ReviewStatus::Corrected,
    ]);

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'hadith',
        'matched_text' => 'شاهدٌ حُذف من الخلاصة',
        'review_status' => ReviewStatus::Removed,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('حديثٌ أُثبت بقرارٍ بشريّ ولم يُطابَق')
        ->assertDontSee('لفظٌ طُوبِق جزئيّاً ثمّ صُحِّح')
        ->assertDontSee('شاهدٌ حُذف من الخلاصة');
});

it('skips a citation too long for the panel instead of truncating it', function (): void {
    $job = showcaseWithContent();

    $long = str_repeat('كلامٌ طويلٌ من متن الحديث ', 20);

    EvidenceItem::factory()->for_($job)->create(['kind' => 'hadith', 'matched_text' => $long]);

    $this->get('/')->assertOk()->assertDontSee(mb_substr($long, 0, 40));
});

it('falls back to the described placeholders once the example is unpublished', function (): void {
    $job = showcaseWithContent();
    $job->forceFill(['unpublished_at' => now()])->saveQuietly();

    $this->get('/')
        ->assertOk()
        ->assertSee(__('landing.demo.speaker'))
        ->assertDontSee('عنوان معروض');
});

it('uses the page-language title when the summary is published in it, and marks Arabic otherwise', function (): void {
    $job = showcaseWithContent();

    // بلا ترجمة: العنوانُ عربيٌّ موسومٌ بلسانه في الصفحة الإنجليزية.
    $this->get('/en')
        ->assertOk()
        ->assertSee('<p class="d-title" lang="ar" dir="rtl">عنوان معروض</p>', false);

    SummaryTranslation::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $job->tenant_id,
        'locale' => 'en',
        'title' => 'The Treasure That Does Not Perish',
        'subtitle' => 'On the virtue of seeking knowledge',
        'body_html' => '<p>Body</p>',
    ]);

    $this->get('/en')
        ->assertOk()
        ->assertSee('<p class="d-title" lang="en" dir="ltr">The Treasure That Does Not Perish</p>', false);
});

it('gives each landing language its own share image, alt text and locale alternates', function (string $path, string $locale): void {
    $response = $this->get($path)->assertOk();

    $response
        ->assertSee('landing/og-'.$locale.'.png', false)
        ->assertSee('<meta property="og:image:alt" content="'.e(__('landing.meta.og_image_alt', [], $locale)).'">', false)
        ->assertDontSee('<meta property="og:locale:alternate" content="'.__('landing.meta.og_locale', [], $locale).'">', false);

    expect(file_exists(public_path('landing/og-'.$locale.'.png')))->toBeTrue();
})->with([['/', 'ar'], ['/en', 'en'], ['/tr', 'tr'], ['/ru', 'ru']]);

/*
 * ─── مراجعةُ الجوال وقسمان يُعادان — T-145 وT-132 ────────────────────
 */

it('shows the sign-in button inside the mobile drawer, and hides it in the bar alone', function (): void {
    // ★ T-132: القاعدةُ كانت على `.navcta` كلِّها فأسقطت «دخول» من الدرج.
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)
        ->toContain('.topbar .navcta .btn-ghost{display:none}')
        ->not->toContain("\n  .navcta .btn-ghost{display:none}");

    // والدرجُ يحمل الزرّين معاً.
    $drawer = mb_substr($content, mb_strpos($content, '<div class="drawer"'), 900);
    expect($drawer)->toContain(__('landing.nav.login'))->toContain(__('landing.nav.contact'));
});

it('keeps the open drawer in place instead of scrolling away with the page', function (): void {
    // ★ بلاغُ مالك المنتج: القائمةُ تذهب عند التمرير. فصارت مثبَّتةً والخلفُ محبوس.
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)
        ->toContain('.drawer.open{position:fixed')
        ->toContain('body.nav-open{overflow:hidden}')
        ->toContain("drawer.style.top=open?topbar.offsetHeight+'px':''")
        // والزرُّ لا يفقد توسيطَه: `ul a` لا `a`.
        ->toContain('.drawer ul a{display:block');
});

it('heads the problem table with what its columns mean, in every language', function (string $path, string $locale): void {
    $this->get($path)
        ->assertOk()
        ->assertSee(__('landing.problem.head_aspect', [], $locale))
        ->assertSee(__('landing.problem.head_now', [], $locale))
        ->assertSee(__('landing.problem.head_effect', [], $locale));
})->with([['/', 'ar'], ['/en', 'en'], ['/tr', 'tr'], ['/ru', 'ru']]);

it('drops the stated-limits section, whose every promise is kept elsewhere', function (): void {
    /*
     * ★ قرارُ مالك المنتج، ١٦ أيلول ٢٠٢٦. **ولا يسقط به تعهّد**: بنودُه
     * الأربعة قائمةٌ في السؤال الأوّل، وفي «حدٌّ نقف عنده»، وفي «النسبة
     * الأمينة»، وفي المسار الثالث من التوثيق، وفي السؤال الأخير.
     */
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)
        ->not->toContain('id="limits"')
        ->toContain(__('landing.verify.note_body'))
        ->toContain(__('landing.faq.items.0.a'))
        ->toContain(__('landing.anatomy.rows.2.b'));
});

/*
 * ─── الأخصّيّة تغلب الاستعلام — T-146 ────────────────────────────────
 */

it('stacks the audience cards on a narrow screen, past the id rule that outranks the query', function (): void {
    /*
     * ★ `#audience .bento` قاعدةٌ بمُعرِّفٍ خارج كلّ استعلام، وأخصُّ من
     * `.bento`. **والاستعلامُ لا يزيد أخصّيّة**، فلا يكفي `.bento{1fr}`.
     */
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)->toContain('.bento,#audience .bento{grid-template-columns:1fr}');
});

it('puts a citation above its wording on a phone, instead of beside it', function (): void {
    $content = $this->get('/')->assertOk()->getContent();

    expect($content)
        ->toContain('.d-list li{display:grid; grid-template-columns:auto 1fr')
        ->toContain('.d-list .x{grid-column:2; grid-row:2');
});
