<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\EvidenceItem;
use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Models\Tenant;
use App\Services\Render\PageRenderer;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedEvidence;

/*
 * قائمةُ التخريج — T-81، بلاغُ مالك المنتج.
 *
 * كانت تُعيد نصَّ كلّ شاهدٍ كاملاً — آيةُ النور سطران ونصف — فتصير نسخةً
 * ثانيةً من المتن. **وفي الصفحة الإنجليزية تخريجُ الآية عربيٌّ** بعد لفظٍ
 * عربيّ في سطرٍ لاتينيّ، فتضطرب الأسطر ولا يعرف قارئُها أين يجد الآية.
 */

/** آيةُ النور كما تُحفظ: مقوَّسةً معلَّمة، وفيها رمزُ الحزب وعلامتا وقف. */
function nurAyah(): RenderedEvidence
{
    return new RenderedEvidence(
        kind: 'ayah',
        text: '﴿۞ ٱللَّهُ نُورُ ٱلسَّمَـٰوَٰتِ وَٱلْأَرْضِ ۚ مَثَلُ نُورِهِۦ كَمِشْكَوٰةٍ فِيهَا مِصْبَاحٌ ۖ ٱلْمِصْبَاحُ فِى زُجَاجَةٍ ۝٣٥﴾',
        sourceRef: 'سورة النور، الآية ٣٥',
        surah: 24,
        ayah: 35,
    );
}

it('يبني موضع الآية بلسان الصفحة ويُبقي العربيّ على محفوظه', function (): void {
    expect(nurAyah()->citation(Locale::En))->toBe('Surah An-Nur 24:35')
        ->and(nurAyah()->citation(Locale::Ar))->toBe('سورة النور، الآية ٣٥')
        // وآيةٌ لا يُعرف موضعُها تسقط إلى المحفوظ — نصٌّ قائم خيرٌ من فراغ.
        ->and((new RenderedEvidence(kind: 'ayah', text: 'ن', sourceRef: 'سورة القلم، الآية ١'))->citation(Locale::En))
        ->toBe('سورة القلم، الآية ١');
});

it('يربط الآية بموضعها في quran.com بترجمة الصفحة نفسها', function (): void {
    expect(nurAyah()->url(Locale::En))->toBe('https://quran.com/24/35?translations=20')
        ->and(nurAyah()->url(Locale::Ar))->toBe('https://quran.com/24/35')
        ->and((new RenderedEvidence(kind: 'ayah', text: 'ن', surah: 2, ayah: 1, ayahEnd: 2))->url(Locale::Tr))
        ->toBe('https://quran.com/2/1-2?translations=77')
        // وما ليس آيةً ولا حديثاً بلا رابط.
        ->and((new RenderedEvidence(kind: 'athar', text: 'متن'))->url(Locale::En))->toBeNull();
});

/*
 * ★ **والحديثُ رابطُ بحثٍ في الدرر السنية** — T-211. بمطلع المتن بلا تشكيلٍ
 * ولا علامات، فيرى القارئ فيه أحكام المحدّثين بنفسه.
 */
it('يربط الحديث ببحثٍ في الدرر السنية بمطلع متنه بلا تشكيل', function (): void {
    $hadith = new RenderedEvidence(kind: 'hadith', text: 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ، وَإِنَّمَا لِكُلِّ امْرِئٍ مَا نَوَى');

    $url = $hadith->url(Locale::En);
    parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://dorar.net/hadith/search?q=')
        ->and($query['q'])->toBe('إنما الأعمال بالنيات وإنما لكل امرئ')
        // واللغةُ لا تغيّره: الدررُ عربية، والبحثُ بلفظ المصدر.
        ->and($hadith->url(Locale::Ar))->toBe($url);
});

/*
 * ★ **طرفُ الشاهد لا نصُّه كلُّه** — كصنيع كتب الأطراف. ولفظُه لفظُ المصدر
 * حرفاً: يُقطع ولا يُبدَّل.
 */
it('يعرض طرف الآية لا نصّها كلّه', function (): void {
    // رمزُ الحزب وعلامةُ الوقف يُحملان ولا يُعدّان كلمات.
    expect(nurAyah()->excerpt())
        ->toBe('﴿۞ ٱللَّهُ نُورُ ٱلسَّمَـٰوَٰتِ وَٱلْأَرْضِ ۚ مَثَلُ نُورِهِۦ كَمِشْكَوٰةٍ…﴾');

    // وما لا يزيد على الحدّ إلّا كلمةً يُعرض كاملاً، بعلامته.
    $short = new RenderedEvidence(kind: 'ayah', text: '﴿وَلَقَدْ يَسَّرْنَا ٱلْقُرْءَانَ لِلذِّكْرِ فَهَلْ مِن مُّدَّكِرٍ ۝١٧﴾');

    expect($short->excerpt())->toBe($short->text);
});

it('يعرض طرف الحديث بلا قوسَي الآية', function (): void {
    $hadith = new RenderedEvidence(
        kind: 'hadith',
        text: 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ وَإِنَّمَا لِكُلِّ امْرِئٍ مَا نَوَى فَمَنْ كَانَتْ هِجْرَتُهُ إِلَى دُنْيَا يُصِيبُهَا',
    );

    expect($hadith->excerpt())->toBe('إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ وَإِنَّمَا لِكُلِّ امْرِئٍ مَا…');
});

/*
 * ═══ T-116 — طرفُ الشاهد راوٍ لا حديث، بلاغُ مالك المنتج ١٢ أيلول ٢٠٢٦ ═══
 *
 * **والسطورُ في اللقطة تتشابه كلُّها** — «عَنْ أَبِيهِ، قَالَ قَالَ رَسُولُ
 * اللَّهِ صلى…» و«عَنْ أَبِي هُرَيْرَةَ ـ رضى الله عنه…» — فلا يُعرف منها
 * حديثٌ من حديث، وهي إنّما وُضعت ليُعرف.
 */

// وهذا الشاهدُ السابعُ في اللقطة بلفظه: البخاري ٦٣٨.
it('يبدأ طرفُ الحديث من لفظه لا من راويه', function (): void {
    $hadith = new RenderedEvidence(
        kind: 'hadith',
        text: 'عَنْ أَبِيهِ، قَالَ قَالَ رَسُولُ اللَّهِ صلى الله عليه وسلم " إِذَا أُقِيمَتِ الصَّلاَةُ '
            .'فَلاَ تَقُومُوا حَتَّى تَرَوْنِي وَعَلَيْكُمْ بِالسَّكِينَةِ ".',
    );

    expect($hadith->excerpt())
        ->toBe('إِذَا أُقِيمَتِ الصَّلاَةُ فَلاَ تَقُومُوا حَتَّى تَرَوْنِي…')
        ->not->toContain('عَنْ أَبِيهِ');
});

// **ولا اقتباسَ في ٤٠٪ من الصفوف** — فيُبدأ بما بعد ذكر النبيّ ﷺ وصيغةِ قوله.
it('يبدأ بما بعد ذكر النبيّ ﷺ حين لا علامةَ اقتباس', function (): void {
    $hadith = new RenderedEvidence(
        kind: 'hadith',
        text: 'عَنْ أَبِي هُرَيْرَةَ ـ رضى الله عنه ـ قَالَ قَالَ رَسُولُ اللَّهِ صلى الله عليه وسلم '
            .'لَنْ يُنَجِّيَ أَحَدًا مِنْكُمْ عَمَلُهُ قَالُوا وَلاَ أَنْتَ يَا رَسُولَ اللَّهِ',
    );

    expect($hadith->excerpt())->toBe('لَنْ يُنَجِّيَ أَحَدًا مِنْكُمْ عَمَلُهُ قَالُوا وَلاَ…');
});

// **ولفظُ الطرف لفظُ المصدر حرفاً**: ما لم يتبيّن مطلعُه يخرج كما دخل —
// طرفٌ طويلٌ أهونُ من طرفٍ مخترع.
it('يردّ طرفَ ما لا يتبيّن مطلعُه كما هو', function (): void {
    $hadith = new RenderedEvidence(
        kind: 'hadith',
        text: 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ وَإِنَّمَا لِكُلِّ امْرِئٍ مَا نَوَى فَمَنْ كَانَتْ هِجْرَتُهُ',
    );

    expect($hadith->excerpt())->toBe('إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ وَإِنَّمَا لِكُلِّ امْرِئٍ مَا…');
});

// **ولا يُختم الطرفُ بعلامة** — والمتنُ القصير يخرج بعلامة إغلاق اقتباسه،
// فتُقرأ اقتباساً بلا فاتحة: الفاتحةُ هي التي بدأ منها الطرف.
it('لا يختم الطرفَ القصيرَ بعلامة إغلاق الاقتباس', function (): void {
    $hadith = new RenderedEvidence(
        kind: 'hadith',
        text: 'سَمِعْتُ رَسُولَ اللَّهِ صلى الله عليه وسلم يَقُولُ : " شَرُّ مَا فِي رَجُلٍ شُحٌّ هَالِعٌ وَجُبْنٌ خَالِعٌ " .',
    );

    expect($hadith->excerpt())->toBe('شَرُّ مَا فِي رَجُلٍ شُحٌّ هَالِعٌ وَجُبْنٌ خَالِعٌ');
});

// والآيةُ لا يمسّها هذا: مطلعُها لفظُها، وقوساها يبقيان.
it('لا يمسّ طرفَ الآية', function (): void {
    expect(nurAyah()->excerpt())->toStartWith('﴿۞ ٱللَّهُ نُورُ');
});

it('يكتب القائمة بالموضع أوّلاً وطرفِ الشاهد في الصفحة الإنجليزية', function (): void {
    $tenant = Tenant::factory()->create();
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id]);
    $job = SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => $lecture->id,
        'body_html' => '<p class="lead">فقرة.</p>',
    ]);

    EvidenceItem::factory()->for_($job)->create([
        'kind' => 'ayah',
        'matched_text' => nurAyah()->text,
        'source_ref' => 'سورة النور، الآية ٣٥',
        'source_meta' => ['surah_number' => 24, 'ayah_number' => 35],
    ]);

    SummaryTranslation::query()->create([
        'summary_job_id' => $job->id,
        'tenant_id' => $tenant->id,
        'locale' => 'en',
        'title' => 'Light',
        'body_html' => '<p class="lead">English.</p>',
    ]);

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->fresh(), Locale::En), BrandKit::forTenant($tenant))
        ->contents;

    $list = substr($html, (int) strpos($html, '<div class="sources">'));

    expect($list)
        ->toContain('<a class="ref" href="https://quran.com/24/35?translations=20" target="_blank" rel="noopener">Surah An-Nur 24:35</a>')
        ->toContain('<span class="excerpt" lang="ar" dir="rtl">﴿۞ ٱللَّهُ')
        // ★ التخريجُ العربيّ لا يقع في سطرٍ لاتينيّ.
        ->not->toContain('سورة النور، الآية ٣٥')
        // والنصُّ كاملاً في المتن لا هنا.
        ->not->toContain('زُجَاجَةٍ');
});

/*
 * ═══ T-117 — الشاهدُ المكرَّر سطرٌ واحد في القائمة، قرار مالك المنتج ═══
 *
 * **والتمييزُ بالموضع لا باللفظ**، والمتنُ و`evidence_items` لا يُمسّان.
 */
function bukhari(string $number, string $text = 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ'): RenderedEvidence
{
    return new RenderedEvidence(kind: 'hadith', text: $text, book: 'bukhari', hadithNumber: $number, grade: 'sahih');
}

it('يجمع الشاهد المكرَّر بكتابه ورقمه ويُبقي ما اختلف موضعه', function (): void {
    $first = bukhari('1');
    $other = bukhari('54', 'الأَعْمَالُ بِالنِّيَّةِ');

    $list = RenderedEvidence::distinct([
        $first,
        nurAyah(),
        bukhari('1', 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ، وَإِنَّمَا لِامْرِئٍ مَا نَوَى'),
        $other,
        nurAyah(),
    ]);

    expect($list)->toHaveCount(3)
        ->and($list[0])->toBe($first)
        ->and($list[1]->kind)->toBe('ayah')
        ->and($list[2])->toBe($other);
});

it('يُبقي بعدده ما لا يُعرف موضعه', function (): void {
    $unplaced = new RenderedEvidence(kind: 'athar', text: 'أثر');

    expect(RenderedEvidence::distinct([$unplaced, $unplaced]))->toHaveCount(2)
        // آيتان من سورةٍ واحدة بموضعين مختلفين شاهدان.
        ->and(RenderedEvidence::distinct([
            new RenderedEvidence(kind: 'ayah', text: 'أ', surah: 2, ayah: 1),
            new RenderedEvidence(kind: 'ayah', text: 'أ', surah: 2, ayah: 1, ayahEnd: 2),
        ]))->toHaveCount(2);
});

it('يكتب الحديث المكرَّر في القائمة مرّةً ويُبقي صفّيه', function (): void {
    $tenant = Tenant::factory()->create();
    $lecture = Lecture::factory()->create(['tenant_id' => $tenant->id]);
    $job = SummaryJob::factory()->create([
        'tenant_id' => $tenant->id,
        'lecture_id' => $lecture->id,
        'body_html' => '<p class="lead">فقرة.</p>',
    ]);

    foreach ([1, 2] as $_) {
        EvidenceItem::factory()->for_($job)->create([
            'kind' => 'hadith',
            'matched_text' => 'إِنَّمَا الأَعْمَالُ بِالنِّيَّاتِ',
            'source_ref' => 'صحيح البخاري، رقم ١',
            'source_meta' => ['book' => 'bukhari', 'hadith_number' => '1', 'grade' => 'sahih'],
        ]);
    }

    $html = app(PageRenderer::class)
        ->render(ContentObject::fromJob($job->fresh(), Locale::Ar), BrandKit::forTenant($tenant))
        ->contents;

    $list = substr($html, (int) strpos($html, '<div class="sources">'));

    expect(substr_count($list, '<li>'))->toBe(1)
        ->and($job->evidenceItems()->count())->toBe(2);
});
