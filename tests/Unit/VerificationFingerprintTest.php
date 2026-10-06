<?php

declare(strict_types=1);

use App\Actions\Stages\GuardEvidenceText;
use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Services\Hadith\LocalCorpusProvider;
use App\Services\Verification\HadithVerifier;
use App\Services\Verification\QuranVerifier;
use App\Support\Arabic;
use App\Support\Hadith\GradeVocabulary;
use App\Support\Hadith\NarrationFormulas;

/**
 * قفلُ بصمة طبقة التحقّق — T-162.
 *
 * **يحرس السلوكَ لا النصّ.** تعديلُ تعليقٍ لا يكسره، وتغييرُ حرفٍ فيما
 * يُطابَق عليه أو يُحكم به يكسره. فالتطبيع والعتبات وجدول الأحكام أدقُّ ما
 * في المنتج، وتغييرٌ صغيرٌ فيها يُمرّر ضعيفاً أو يُسقط صحيحاً بلا أن يُرى.
 *
 * ★ **وتحديثُ البصمة هنا قرارٌ لا إصلاح.** من كسره عمداً يُشغّل عيّنة
 * الشواهد المدسوسة أوّلاً، ثمّ يحدّث القيمة ويذكر السبب في رسالة الالتزام.
 * ولا يُحدَّث ليمرّ اختبار.
 */
const FINGERPRINT_ADVICE = 'هذا تغييرٌ في سلوك طبقة التحقّق (T-162). شغّل `php artisan test --filter=Evidence` '
    .'وعيّنة الشواهد المدسوسة، ثمّ حدّث البصمة هنا عمداً واذكر السبب في رسالة الالتزام.';

function verificationConstant(string $class, string $name): mixed
{
    return (new ReflectionClass($class))->getConstant($name);
}

it('keeps the normalizer output unchanged over the whole seeded hadith corpus', function (): void {
    $out = [];

    // الكتبُ المحكومة — والطبقةُ الثانية (T-170) ببصمتها وحدها تحت هذا.
    foreach (HadithBook::primary() as $book) {
        $rows = json_decode(
            (string) gzdecode((string) file_get_contents(database_path("data/hadith/{$book->value}.json.gz"))),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($rows as $row) {
            $out[] = Arabic::normalize($row['text']);
        }
    }

    expect(count($out))->toBe(35_982)
        ->and(hash('sha256', implode("\n", $out)))
        ->toBe('a9f00738ce8d73948febeb212a3367bbb2046911b06b4f5ca2a0e640252da503', FINGERPRINT_ADVICE);
});

it('keeps the normalizer output unchanged over the second tier of the corpus', function (): void {
    // T-170: مسند أحمد وسنن الدارمي من Open-Hadith-Data (`1515f6c`)، بلا أحكام.
    $out = [];

    foreach (HadithBook::secondary() as $book) {
        $rows = json_decode(
            (string) gzdecode((string) file_get_contents(database_path("data/hadith/{$book->value}.json.gz"))),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach ($rows as $row) {
            $out[] = Arabic::normalize($row['text']);
        }
    }

    expect(count($out))->toBe(29_730)
        ->and(hash('sha256', implode("\n", $out)))
        ->toBe('75279b2dd8241728529618e731ab208e39a67f6b2e696683ff765077d1f62d11', FINGERPRINT_ADVICE);
});

it('keeps the normalizer treatment of every Arabic, presentation, space and control character', function (): void {
    // كلّ محرفٍ بين كلمتين وحده — فما لا يرد في المدوّنة يُحرس كذلك.
    $ranges = [
        [0x0020, 0x007E], [0x00A0, 0x00A0], [0x0600, 0x06FF], [0x0750, 0x077F],
        [0x08A0, 0x08FF], [0x2000, 0x206F], [0xFB50, 0xFDFF], [0xFE70, 0xFEFF],
    ];

    $probe = [];

    foreach ($ranges as [$from, $to]) {
        for ($code = $from; $code <= $to; $code++) {
            $char = mb_chr($code, 'UTF-8');

            if ($char !== false) {
                $probe[] = Arabic::normalize("كتب{$char}علم {$char} نص");
            }
        }
    }

    expect(count($probe))->toBe(1_440)
        ->and(hash('sha256', implode("\n", $probe)))
        ->toBe('a8b0e2b1086c156c628fd26af5759b6f591e1de9560163d732731f8c2f12e296', FINGERPRINT_ADVICE);
});

it('keeps the islamic domain thresholds and settling policy', function (): void {
    expect(config('khulasah.domains.islamic.thresholds'))
        ->toBe(['exact' => 0.95, 'partial' => 0.75], FINGERPRINT_ADVICE)
        ->and(config('khulasah.domains.islamic.settling'))->toBe([
            'disclose' => ['statuses' => ['exact', 'partial'], 'grades' => null],
            'review' => ['statuses' => ['exact'], 'grades' => ['sahih']],
        ], FINGERPRINT_ADVICE);
});

it('keeps the matching limits inside the verifiers and the evidence guard', function (): void {
    expect([
        'hadith.min_fragment_words' => verificationConstant(HadithVerifier::class, 'MIN_FRAGMENT_WORDS'),
        'hadith.min_fragment_coverage' => verificationConstant(HadithVerifier::class, 'MIN_FRAGMENT_COVERAGE'),
        'hadith.citable_words' => verificationConstant(HadithVerifier::class, 'CITABLE_WORDS'),
        'quran.span_candidates' => verificationConstant(QuranVerifier::class, 'SPAN_CANDIDATES'),
        'quran.tolerance_max_dropped' => verificationConstant(QuranVerifier::class, 'TOLERANCE_MAX_DROPPED'),
        'quran.tolerance_min_words' => verificationConstant(QuranVerifier::class, 'TOLERANCE_MIN_WORDS'),
        'quran.tolerance_candidates' => verificationConstant(QuranVerifier::class, 'TOLERANCE_CANDIDATES'),
        'corpus.candidates' => verificationConstant(LocalCorpusProvider::class, 'CANDIDATES'),
        'corpus.shortlist_threshold' => verificationConstant(LocalCorpusProvider::class, 'SHORTLIST_THRESHOLD'),
        'guard.match_threshold' => verificationConstant(GuardEvidenceText::class, 'MATCH_THRESHOLD'),
        'guard.near_exact_threshold' => verificationConstant(GuardEvidenceText::class, 'NEAR_EXACT_THRESHOLD'),
        'guard.ambiguity_margin' => verificationConstant(GuardEvidenceText::class, 'AMBIGUITY_MARGIN'),
    ])->toBe([
        'hadith.min_fragment_words' => 4,
        'hadith.min_fragment_coverage' => 0.6,
        'hadith.citable_words' => 6,
        // T-168: المِجسُّ ذو الكلمات الأربع حلّ محلَّه تجريبُ كلّ موضعِ وصل.
        'quran.span_candidates' => 50,
        // T-169: التسامحُ في آيةٍ من الحفظ — قرار مالك المنتج، ٤ أكتوبر ٢٠٢٦.
        'quran.tolerance_max_dropped' => 3,
        'quran.tolerance_min_words' => 4,
        'quran.tolerance_candidates' => 100,
        'corpus.candidates' => 20,
        'corpus.shortlist_threshold' => 0.45,
        'guard.match_threshold' => 65.0,
        'guard.near_exact_threshold' => 98.0,
        'guard.ambiguity_margin' => 10.0,
    ], FINGERPRINT_ADVICE);
});

it('keeps the grade vocabulary and the order of severity', function (): void {
    $table = array_map(
        static fn (HadithGrade $grade): string => $grade->value,
        verificationConstant(GradeVocabulary::class, 'TABLE'),
    );

    expect(count($table))->toBe(66)
        ->and(verificationConstant(GradeVocabulary::class, 'SEVERITY'))
        ->toBe(['mawdu' => 4, 'daif' => 3, 'hasan' => 2, 'sahih' => 1], FINGERPRINT_ADVICE)
        ->and(hash('sha256', json_encode([$table, verificationConstant(GradeVocabulary::class, 'SEVERITY')], JSON_UNESCAPED_UNICODE)))
        ->toBe('33845987e98de825b6a73a7b9c09e9aaeae169c216b05cc56c71a7f716642a3e', FINGERPRINT_ADVICE)
        ->and(hash('sha256', json_encode(verificationConstant(HadithGrade::class, 'MARKERS'), JSON_UNESCAPED_UNICODE)))
        ->toBe('a4567e99f5ab98d910b6442d11bd68054a4dbcaf21d1912e9dbcaaebc07f98fe', FINGERPRINT_ADVICE);
});

it('keeps the narration formulas that are never taken for a hadith', function (): void {
    expect(hash('sha256', json_encode([
        verificationConstant(NarrationFormulas::class, 'FORMULAS'),
        verificationConstant(NarrationFormulas::class, 'MIN_MATN_WORDS'),
    ], JSON_UNESCAPED_UNICODE)))
        ->toBe('456769cbc8c61108d77471df85f8a92f6e38eb07a32f3ff2450366785d6dede5', FINGERPRINT_ADVICE);
});

it('keeps the honorifics that are never counted as wording', function (): void {
    // T-219: تُنزع من الطرفين قبل قياس التشابه وحدّ الشذرة.
    expect(hash('sha256', json_encode(verificationConstant(NarrationFormulas::class, 'HONORIFICS'), JSON_UNESCAPED_UNICODE)))
        ->toBe('7235e1ab6b2e5138a3f4d5608a2b9d48766b3b71501d7d77b6852eea124c2d1b', FINGERPRINT_ADVICE);
});

it('keeps the order in which books win a tie', function (): void {
    // T-219: الأعلى رتبةً يُعزى إليه الحديث حين يطابقه كتابان بالتمام نفسه.
    expect(array_map(static fn (HadithBook $book): string => $book->value, HadithBook::cases()))
        ->toBe(['bukhari', 'muslim', 'abudawud', 'tirmidhi', 'nasai', 'ibnmajah', 'malik', 'ahmad', 'darimi'], FINGERPRINT_ADVICE);
});

it('keeps the sahihayn exception and the grades that may pass without a human', function (): void {
    $sahihayn = array_values(array_map(
        static fn (HadithBook $book): string => $book->value,
        array_filter(HadithBook::all(), static fn (HadithBook $book): bool => $book->isSahihayn()),
    ));

    $passing = array_values(array_map(
        static fn (HadithGrade $grade): string => $grade->value,
        array_filter(HadithGrade::cases(), static fn (HadithGrade $grade): bool => $grade->mayAutoPass()),
    ));

    expect($sahihayn)->toBe(['bukhari', 'muslim'], FINGERPRINT_ADVICE)
        ->and($passing)->toBe(['sahih', 'hasan'], FINGERPRINT_ADVICE);
});
