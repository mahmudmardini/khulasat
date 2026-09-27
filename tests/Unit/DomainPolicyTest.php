<?php

declare(strict_types=1);

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Enums\UnverifiedPolicy;
use App\Services\Verification\QuranVerifier;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\VerificationResult;

function islamicPolicy(): DomainPolicy
{
    return DomainPolicy::for(DomainPolicy::DEFAULT);
}

/**
 * مجالٌ مصنوع للاختبار وحده — **ولا يُضاف إلى المنتج**.
 *
 * غرضه إثبات أنّ السياسة تُقرأ من المجال لا من الشيفرة. ولو اختُبرت
 * بالمجال الشرعي وحده لمرّ التنفيذُ الذي يكتب «exact أو يقف» ثابتةً في
 * الكود، وهو عين ما تمنعه T-02ب.
 */
function loosePolicy(): DomainPolicy
{
    config()->set('khulasah.domains.test_loose', [
        'verifiers' => ['citation' => QuranVerifier::class],
        // مجالٌ لا يشدّد: يُمرّر القريب حتى وهو يطلب مراجعة.
        'settling' => [
            'disclose' => ['statuses' => ['exact', 'partial'], 'grades' => null],
            'review' => ['statuses' => ['exact', 'partial'], 'grades' => null],
        ],
        'thresholds' => ['exact' => 0.9, 'partial' => 0.5],
    ]);

    return DomainPolicy::for('test_loose');
}

// ── قراءة السياسة ───────────────────────────────────────────────

it('reads the Islamic domain from configuration', function (): void {
    $policy = islamicPolicy();

    expect($policy->domain)->toBe('islamic')
        ->and($policy->kinds())->toBe(['ayah', 'hadith', 'athar'])
        ->and($policy->threshold('exact'))->toBe(0.95)
        ->and($policy->threshold('partial'))->toBe(0.75);
});

it('refuses a domain it has no policy for instead of falling back', function (): void {
    // **ولا يُردّ إلى الشرعي صامتاً.** جهةٌ مجالها غير مضبوط تُصنَّف شواهدُها
    // بسياسةٍ ليست سياستها، وذلك أخفى من إخفاقٍ ظاهر.
    expect(fn () => DomainPolicy::for('academic'))
        ->toThrow(RuntimeException::class, 'لا سياسة مضبوطة للمجال «academic»');
});

it('refuses a threshold it was never given', function (): void {
    expect(fn () => islamicPolicy()->threshold('almost'))->toThrow(RuntimeException::class);
});

// ── قائمة سماح الأنواع ─────────────────────────────────────────

it('knows only the evidence kinds its domain declares', function (): void {
    $policy = islamicPolicy();

    expect($policy->knows('ayah'))->toBeTrue()
        ->and($policy->knows('hadith'))->toBeTrue()
        ->and($policy->knows('athar'))->toBeTrue()
        ->and($policy->knows('citation'))->toBeFalse()
        ->and($policy->verifierFor('citation'))->toBeNull();
});

// ── ★ الحسم خاصّ بالمجال لا عامّ ★ ─────────────────────────────

it('keeps the Islamic domain on exact-or-hold when review is asked for', function (): void {
    expect(islamicPolicy()->allowsAutoPass(MatchStatus::Exact, UnverifiedPolicy::Review))->toBeTrue()
        ->and(islamicPolicy()->allowsAutoPass(MatchStatus::Partial, UnverifiedPolicy::Review))->toBeFalse()
        ->and(islamicPolicy()->allowsAutoPass(MatchStatus::None, UnverifiedPolicy::Review))->toBeFalse();
});

it('accepts the near match under the disclosure policy', function (): void {
    // ★ سياسة البيان — §7-5. كانت «exact أو يقف»، وصارت «يُنشر ما عُرف
    //   مصدره مقروناً بدرجته». والقريب يُنشر **بلفظ مصدره** لا بلفظ المحاضرة.
    expect(islamicPolicy()->allowsAutoPass(MatchStatus::Partial, UnverifiedPolicy::Disclose))->toBeTrue()
        ->and(islamicPolicy()->allowsAutoPass(MatchStatus::None, UnverifiedPolicy::Disclose))->toBeFalse();
});

it('lets another domain settle by rules of its own', function (): void {
    // ★ الاختبار الحاكم: لو كانت السياسة مكتوبةً في الشيفرة لأخفق هذا.
    $loose = loosePolicy();

    expect($loose->allowsAutoPass(MatchStatus::Partial, UnverifiedPolicy::Review))->toBeTrue()
        ->and($loose->threshold('partial'))->toBe(0.5)
        // والمجال الشرعي لم يتأثّر بوجود غيره.
        ->and(islamicPolicy()->allowsAutoPass(MatchStatus::Partial, UnverifiedPolicy::Review))->toBeFalse();
});

it('refuses a settling mode its domain never declared', function (): void {
    config()->set('khulasah.domains.test_bare', [
        'verifiers' => [],
        'settling' => ['disclose' => ['statuses' => ['exact'], 'grades' => null]],
        'thresholds' => [],
    ]);

    expect(fn () => DomainPolicy::for('test_bare')->allowsAutoPass(MatchStatus::Exact, UnverifiedPolicy::Review))
        ->toThrow(RuntimeException::class);
});

// ── ★ الضمانة الحاكمة: ما جُهل مصدره لا يُنشر ★ ────────────────

it('refuses to call an unmatched result sourced', function (): void {
    expect(islamicPolicy()->hasStatedSource(VerificationResult::none()))->toBeFalse();
});

it('refuses to call a match with no stated grade sourced', function (): void {
    // «عجزُنا عن التخريج ليس حكماً بالوضع، فلا يُوصَف بشيء — بل يُحذف
    //  صامتاً ويُسجَّل لصاحب الجهة» — §7-5.
    $result = new VerificationResult(MatchStatus::Exact, sourceMeta: ['has_stated_grade' => false]);

    expect(islamicPolicy()->hasStatedSource($result))->toBeFalse()
        ->and(ReviewStatus::decide($result, islamicPolicy()))->toBe(ReviewStatus::Removed);
});

it('treats a missing grade flag as a stated source', function (): void {
    // كالآية: مطابقتها المصحفَ هي نصُّ مصدرها، ولا درجة تُطلب فيها. ولو
    // طُلبت لوقفت كلّ آية صحيحة على مراجع.
    $result = new VerificationResult(MatchStatus::Exact, sourceMeta: ['surah_number' => 2]);

    expect(islamicPolicy()->hasStatedSource($result))->toBeTrue()
        ->and(ReviewStatus::decide($result, islamicPolicy()))->toBe(ReviewStatus::AutoPassed);
});

// ── ★ الوضعان: البيان والمراجعة ★ ──────────────────────────────

it('publishes a weak hadith under disclosure and holds it under review', function (): void {
    // «ولماذا جاز نشر الضعيف: لأنّه يُنشر مقروناً ببيانه، وهذا عمل أهل
    //  العلم. والآفة في نقل الضعيف موهِماً صحّته، لا في نقله مبيَّناً.»
    $weak = new VerificationResult(MatchStatus::Exact, sourceMeta: [
        'grade' => 'daif',
        'has_stated_grade' => true,
    ]);

    expect(islamicPolicy()->publishes($weak, UnverifiedPolicy::Disclose))->toBeTrue()
        ->and(islamicPolicy()->publishes($weak, UnverifiedPolicy::Review))->toBeFalse()
        ->and(ReviewStatus::decide($weak, islamicPolicy(), UnverifiedPolicy::Review))->toBe(ReviewStatus::Pending);
});

it('publishes a fabricated hadith disclosed, rather than passing it off as sound', function (): void {
    // الموضوع **الموجود في المدوّنة موصوفاً بالوضع** يُنشر مبيَّناً — §7-5.
    // وهو غيرُ الموضوع الذي لا نجده أصلاً: ذاك يعود `none` ويُحذف صامتاً.
    $fabricated = new VerificationResult(MatchStatus::Exact, sourceMeta: [
        'grade' => 'mawdu',
        'has_stated_grade' => true,
    ]);

    expect(ReviewStatus::decide($fabricated, islamicPolicy()))->toBe(ReviewStatus::AutoPassed)
        ->and(ReviewStatus::decide($fabricated, islamicPolicy(), UnverifiedPolicy::Review))->toBe(ReviewStatus::Pending);
});

it('never lets the review mode publish what disclosure would not', function (): void {
    // **الحدّ الرابع لا يُمسّ:** الإعداد يغيّر متى تُدخَل حالة الوقوف، لا ما
    // تفعله فيها. فوضع المراجعة أضيق من وضع البيان، ولا يوسّعه أبداً.
    foreach ([MatchStatus::Exact, MatchStatus::Partial, MatchStatus::None] as $status) {
        foreach ([null, 'sahih', 'hasan', 'daif', 'mawdu'] as $grade) {
            $result = new VerificationResult($status, sourceMeta: array_filter(['grade' => $grade]));

            if (islamicPolicy()->publishes($result, UnverifiedPolicy::Review)) {
                expect(islamicPolicy()->publishes($result, UnverifiedPolicy::Disclose))->toBeTrue();
            }
        }
    }
});
