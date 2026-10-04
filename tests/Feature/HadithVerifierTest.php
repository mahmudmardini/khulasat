<?php

declare(strict_types=1);

use App\Contracts\SupplementaryHadithProvider;
use App\Enums\HadithGrade;
use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Exceptions\HadithCorpusUnavailable;
use App\Services\Verification\HadithVerifier;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\HadithMatch;
use App\Support\Verification\VerificationResult;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\FakeHadithProvider;

function verifyHadith(string $text, ?string $narrator = null, ?array $providers = null): VerificationResult
{
    $verifier = new HadithVerifier(
        $providers ?? [FakeHadithProvider::withKnownHadiths()],
        DomainPolicy::for(DomainPolicy::DEFAULT),
    );

    return $verifier->verify(new EvidenceInput(
        kind: 'hadith',
        rawText: $text,
        claimedNarrator: $narrator,
    ));
}

// ── H-EXACT: الصحيح بلفظه يمرّ آلياً ────────────────────────────

it('auto-passes a sound hadith quoted verbatim', function (): void {
    // H-EXACT-01
    $r = verifyHadith('أحب الأعمال إلى الله أدومها وإن قل', 'عائشة');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['grade'])->toBe('sahih')
        ->and($r->sourceMeta['has_stated_grade'])->toBeTrue();
});

it('auto-passes a longer sound hadith', function (): void {
    // H-EXACT-02
    $r = verifyHadith('المسلم من سلم المسلمون من لسانه ويده', 'أبو موسى الأشعري');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['has_stated_grade'])->toBeTrue();
});

// ── H-ALTERED: القريب يُنشر **بلفظ مصدره** لا بلفظ المحاضرة ─────

it('keeps both wordings when the quote is one word off', function (): void {
    // H-ALTERED-01 — «أدومها» ← «أكثرها».
    // **وسياسة البيان تنشره بلفظ مصدره** (§7-5)، فلا يبقى الخطأ منقولاً.
    // والمستخرَج محفوظ إلى جانبه فيرى المراجع ما جرى ولا يُبدَّل صامتاً.
    $r = verifyHadith('أحب الأعمال إلى الله أكثرها وإن قل', 'عائشة');

    expect($r->status)->toBe(MatchStatus::Partial)
        ->and($r->matchedText)->toContain('أدومها')
        ->and($r->sourceMeta['extracted_text'])->toContain('أكثرها')
        ->and($r->sourceMeta['has_stated_grade'])->toBeTrue();
});

it('reads a hadith with words inserted as a near match, not an exact one', function (): void {
    // H-ALTERED-02 — الزيادة المقحمة تُنزله عن التمام، فيُنشر لفظ المصدر
    // وحده. **ولا تمرّ الزيادة إلى المنشور البتّة.**
    $r = verifyHadith('المسلم من سلم المسلمون من لسانه ويده وقلبه ونيته');

    expect($r->status)->toBe(MatchStatus::Partial)
        ->and($r->matchedText)->not->toContain('ونيته');
});

// ── الاقتباس الناقص: يُقاس على ما اقتُبس ────────────────────────

it('matches a verbatim quote of part of a hadith', function (): void {
    // H-EXACT-02 — المتكلّم يقول «من سلم…» ولفظ البخاري «المسلم من سلم…».
    // ولو قيس على النصّ كلّه لعُدّ صدرٌ لم يُقتبس تحريفاً.
    $r = verifyHadith('من سلم المسلمون من لسانه ويده', 'أبو موسى الأشعري');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['similarity'])->toBe(1.0)
        ->and($r->sourceMeta['is_fragment'])->toBeTrue()
        ->and($r->sourceMeta['has_stated_grade'])->toBeTrue();
});

it('still reads that quote as near when words are inserted into it', function (): void {
    // H-ALTERED-02 — الاقتباس الناقص لا يُذهب الزيادة المقحمة.
    $r = verifyHadith('من سلم المسلمون من لسانه ويده وقلبه ونيته');

    expect($r->status)->toBe(MatchStatus::Partial);
});

it('refuses a fragment too short to be a citation', function (string $fragment): void {
    // ★ الثغرة التي تفتحها المحاذاة بالنافذة: كلّ شذرة قصيرة نافذةٌ تامّة
    // في نصٍّ ما. والحدّ: تغطية ٦٠٪ و٤ كلمات — قرار مالك المنتج ٦ أيلول ٢٠٢٦.
    expect(verifyHadith($fragment)->status)->toBe(MatchStatus::None);
})->with([
    'كلمة واحدة' => ['ويده'],
    'ثلاث كلمات — دون حدّ العدد' => ['من لسانه ويده'],
    'أربع كلمات — دون حدّ التغطية' => ['المسلمون من لسانه ويده'],
]);

it('exempts a short hadith quoted whole from the fragment floor', function (): void {
    // «إنما الأعمال بالنيات» ثلاث كلمات وهو حديث تامّ لا شذرة.
    $r = verifyHadith('إنما الأعمال بالنيات', providers: [
        new FakeHadithProvider([new HadithMatch(
            text: 'إنما الأعمال بالنيات',
            ruling: 'صحيح',
        )]),
    ]);

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['is_fragment'])->toBeFalse();
});

// ── ★ القاعدة الحاكمة: الحكم يغلب التطابق ★ ─────────────────────

it('never publishes a fabricated hadith as though it were sound', function (string $text): void {
    // H-FABRICATED-01 و H-FABRICATED-02 و H-WEAK-01.
    // اللفظ مطابق تماماً، **والحكم لا يُطوى**. وهذا جوهر المنتج.
    //
    // وسياسة البيان (§7-5) لم تُرخِ هذا: الموضوع يُنشر **مقروناً بأنّه لا
    // يصحّ**، ويقف في وضع المراجعة. والممنوع أن يمرّ صامتاً كأنّه ثابت.
    $r = verifyHadith($text);

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['similarity'])->toBe(1.0)
        ->and($r->sourceMeta['held_for_grade'])->toBeTrue()
        ->and(ReviewStatus::decide($r, DomainPolicy::for(DomainPolicy::DEFAULT), UnverifiedPolicy::Review))
        ->toBe(ReviewStatus::Pending);
})->with([
    'H-FABRICATED-01 — حب الوطن' => ['حب الوطن من الإيمان'],
    'H-FABRICATED-02 — من عرف نفسه' => ['من عرف نفسه فقد عرف ربه'],
    'H-WEAK-01 — اطلبوا العلم' => ['اطلبوا العلم ولو بالصين'],
]);

it('shows the muhaddith ruling on a weak hadith', function (): void {
    // H-WEAK-01 — must_show_grade
    $r = verifyHadith('اطلبوا العلم ولو بالصين');

    expect($r->sourceMeta['grade'])->toBe('daif')
        ->and($r->sourceMeta['grade_label'])->toBe('ضعيف')
        ->and($r->sourceMeta['grade_raw'])->toBe('ضعيف جدا');
});

// ── H-MISATTRIBUTED: التخريج من المصدر لا من الادّعاء ───────────

it('flags a narrator that differs from the source', function (): void {
    // H-MISATTRIBUTED-01 — اللفظ صحيح والراوي المدّعى غير راويه
    $r = verifyHadith('أحب الأعمال إلى الله أدومها وإن قل', 'أبو هريرة');

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['narrator_corrected'])->toBeTrue()
        ->and($r->sourceMeta['narrator'])->toContain('عائشة')
        ->and($r->sourceMeta['claimed_narrator'])->toBe('أبو هريرة');
});

// ── H-UNKNOWN: لا تخمين ─────────────────────────────────────────

it('never guesses the nearest hadith', function (): void {
    // H-UNKNOWN-01 — must_not_guess_nearest
    $r = verifyHadith('من داوم على قراءة سورة الكهف كل خميس رفع الله عنه هم الدنيا سبعين مرة');

    expect($r->status)->toBe(MatchStatus::None)
        ->and($r->matchedText)->toBeNull();
});

// ── صمود المزوّدين ──────────────────────────────────────────────

it('survives a provider that throws and falls through to the next', function (): void {
    $r = verifyHadith('أحب الأعمال إلى الله أدومها وإن قل', providers: [
        new FakeHadithProvider(failWith: 'انقطاع الشبكة', name: 'broken'),
        FakeHadithProvider::withKnownHadiths(),
    ]);

    expect($r->status)->toBe(MatchStatus::Exact);
});

/*
 * ★ **بحثٌ لم يجرِ لا يُقال فيه «لم يُعثر عليه»** — T-213، قرار مالك المنتج.
 * كان هذا يعود `none` (§7-4)، فقالت أداةُ «تحقّق» عن حديثٍ في البخاري إنّه
 * غير موجود، ونُشر الملخّصُ بلا أحاديثه حين تعطّلت القاعدة.
 */
it('throws, rather than saying not found, when no graded provider answers', function (): void {
    verifyHadith('أحب الأعمال إلى الله أدومها وإن قل', providers: [
        new FakeHadithProvider(failWith: 'مهلة', name: 'a'),
        new FakeHadithProvider(failWith: '500', name: 'b'),
    ]);
})->throws(HadithCorpusUnavailable::class);

it('throws when the graded books fail even if the supplementary tier answers', function (): void {
    // ما في الطبقة الثانية لا يُنشر، وقد يكون الحديثُ في البخاري بحكمه.
    verifyHadith('أحب الأعمال إلى الله أدومها وإن قل', providers: [
        new FakeHadithProvider(failWith: 'مهلة', name: 'graded'),
        supplementaryProvider(FakeHadithProvider::withKnownHadiths()),
    ]);
})->throws(HadithCorpusUnavailable::class);

it('passes over a failing supplementary tier once the graded books have answered', function (): void {
    $r = verifyHadith('نص لا وجود له في أي كتاب من الكتب البتة', providers: [
        FakeHadithProvider::withKnownHadiths(),
        supplementaryProvider(new FakeHadithProvider(failWith: 'مهلة', name: 'second')),
    ]);

    expect($r->status)->toBe(MatchStatus::None);
});

/** مزوّدٌ تكميليٌّ بلا حكم، كالطبقة الثانية — يُغلّف مزوّداً وهمياً. */
function supplementaryProvider(FakeHadithProvider $inner): SupplementaryHadithProvider
{
    return new class($inner) implements SupplementaryHadithProvider
    {
        public function __construct(private readonly FakeHadithProvider $inner) {}

        public function name(): string
        {
            return 'supplementary';
        }

        public function search(string $normalized): array
        {
            return $this->inner->search($normalized);
        }
    };
}

it('keeps no cache layer between itself and the corpus', function (): void {
    // كان `hadith_cache` يمنع نداءً خارجياً يتكرّر. ولم يبقَ نداء خارجي —
    // T-05ب — فصار الكاش طبقةً بين الشيء ونفسه، وأُسقط جدولُه.
    expect(Schema::hasTable('hadith_cache'))->toBeFalse();

    $r = verifyHadith('أحب الأعمال إلى الله أدومها وإن قل');

    expect($r->status)->toBe(MatchStatus::Exact);
});

// ── قراءة الأحكام ───────────────────────────────────────────────

it('reads a provider ruling into a grade', function (?string $ruling, HadithGrade $expected): void {
    expect(HadithGrade::fromRuling($ruling))->toBe($expected);
})->with([
    ['صحيح', HadithGrade::Sahih],
    ['متفق عليه', HadithGrade::Sahih],
    ['حسن', HadithGrade::Hasan],
    ['حسن صحيح', HadithGrade::Hasan],
    ['ضعيف', HadithGrade::Daif],
    ['ضَعِيفٌ جِدًّا', HadithGrade::Daif],
    ['منكر', HadithGrade::Daif],
    ['موضوع', HadithGrade::Mawdu],
    ['لا أصل له', HadithGrade::Mawdu],
    ['لا يصح', HadithGrade::Mawdu],
    [null, HadithGrade::Unknown],
    ['', HadithGrade::Unknown],
    ['رواه الترمذي', HadithGrade::Unknown],
    ['رواه البخاري ومسلم', HadithGrade::Unknown],
    ['ضعيفة', HadithGrade::Daif],
]);

it('reads a mixed ruling by its weakest part', function (): void {
    // «ضعيف بهذا اللفظ وصحيح بغيره» يجب أن يُقرأ ضعيفاً.
    // ولو قُدّم «صحيح» في الفحص لمرّ ما لا يُمرَّر.
    expect(HadithGrade::fromRuling('ضعيف بهذا اللفظ وصحيح بغيره'))->toBe(HadithGrade::Daif);
});

it('never lets an unruled hadith auto-pass', function (): void {
    $r = verifyHadith('نص بلا حكم', providers: [
        new FakeHadithProvider([new HadithMatch(text: 'نص بلا حكم', ruling: null)]),
    ]);

    expect($r->status)->toBe(MatchStatus::Exact)
        ->and($r->sourceMeta['grade'])->toBe('unknown')
        ->and($r->sourceMeta['held_for_grade'])->toBeTrue();
});
