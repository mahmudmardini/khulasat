<?php

declare(strict_types=1);

namespace App\Support\Verify;

use App\Enums\HadithGrade;
use App\Enums\Locale;
use App\Enums\MatchStatus;
use App\Models\QuranAyah;
use App\Support\Arabic;
use App\Support\Hadith\MatnExtractor;
use App\Support\Hadith\MatnOpening;
use App\Support\Hadith\NarrationFormulas;
use App\Support\Render\RenderedEvidence;
use App\Support\Verification\VerificationResult;

/**
 * شاهدٌ واحد في تقرير «تحقّق» — T-181: لفظُه كما ورد، وحكمُه، وسببُه، ومصدرُه.
 *
 * ★ **والسببُ عبارةٌ ثابتة من `lang/ar/verify.php`** يختارها هذا الصنف من
 * نتيجة المطابقة، لا نصٌّ يكتبه نموذج. فالحكمُ نفسُه يُقال بالعبارة نفسها
 * في كلّ طلب، ويُقرأ سببُه من الكود لا من تخمين.
 *
 * **وأربعة أحكامٍ لا ثلاثة:** «لم نجده» غير «لا مصدر لنوعه». الأوّل بحثٌ في
 * مصدرٍ عندنا لم يُثمر، والثاني نوعٌ لا مصدر عندنا له أصلاً — أقوالُ العلماء.
 * وخلطُهما يوهم أنّ قولَ عالمٍ صحيحِ النسبة «غير موجود».
 */
final class Finding
{
    public const UNVERIFIABLE = 'unverifiable';

    /**
     * @param  array<string, mixed>  $extracted  شاهدٌ كما أخرجته مرحلة الاستخراج
     * @param  array{ayah: QuranAyah, similarity: float}|null  $nearest  أقربُ آيةٍ إلى آيةٍ لم تطابق ({@see NearestAyah})
     * @return array<string, mixed>
     */
    public static function build(int $index, array $extracted, ?VerificationResult $result, ?array $nearest = null): array
    {
        $kind = (string) ($extracted['kind'] ?? '');
        $quoted = trim((string) ($extracted['raw_text'] ?? ''));
        $meta = $result?->sourceMeta ?? [];

        // ★ **آيةٌ لم تطابق ولها آيةٌ قريبة: «قريبٌ من لفظ المصدر»** — T-182.
        //   لا «لم نجده»: النصُّ آيةٌ زاغ لفظُها، وموضعُها معروف. والحكمُ في
        //   طبقة التحقّق لم يتغيّر، والآيةُ تُعرض للمقارنة وحدها.
        $near = $kind === 'ayah' && $result?->status === MatchStatus::None ? $nearest : null;

        $verdict = match (true) {
            $result === null => self::UNVERIFIABLE,
            $near !== null => MatchStatus::Partial->value,
            default => $result->status->value,
        };

        $source = match (true) {
            $near !== null => self::nearSource($near),
            $result !== null && $result->matched() => self::source($kind, $result),
            default => null,
        };

        return [
            'index' => $index,
            'kind' => $kind,
            'quoted' => $quoted,
            'claimed' => array_filter([
                'source' => $extracted['claimed_source'] ?? null,
                'narrator' => $extracted['claimed_narrator'] ?? null,
                'takhrij' => $extracted['claimed_takhrij'] ?? null,
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''),
            'verdict' => $verdict,
            'reason' => self::reason($kind, $quoted, $result, $source),
            'notes' => self::notes($kind, $extracted, $result),
            'source' => $source,
            'is_fragment' => (bool) ($meta['is_fragment'] ?? false),
        ];
    }

    /**
     * ما وُجد في المصدر — **بلفظ المصدر لا بلفظ النصّ**، ومعه موضعُه ودرجتُه.
     *
     * @return array<string, mixed>
     */
    private static function source(string $kind, VerificationResult $result): array
    {
        $meta = $result->sourceMeta;

        $rendered = new RenderedEvidence(
            kind: $kind,
            text: (string) $result->matchedText,
            sourceRef: $result->sourceRef,
            takhrij: self::string($meta['takhrij'] ?? null),
            grade: self::string($meta['grade'] ?? null),
            narrator: self::string($meta['narrator'] ?? null),
            book: self::string($meta['book'] ?? null),
            hadithNumber: self::string($meta['hadith_number'] ?? null),
            surah: self::int($meta['surah_number'] ?? null),
            ayah: self::int($meta['ayah_number'] ?? null),
            ayahEnd: self::int($meta['ayah_number_end'] ?? null),
        );

        $grade = self::string($meta['grade'] ?? null);
        $text = (string) $result->matchedText;

        /*
         * ★ **المتنُ وحده للحديث** — والنصُّ كاملاً بسنده يبقى لمن أراده. فالمقارنةُ
         * على النصّ كاملاً تُظلّل السند كلَّه «فرقاً»، وما زاغ فيه الاقتباسُ
         * كلمةٌ في المتن تضيع بين عشرين في الإسناد.
         */
        $matn = $kind === 'ayah' ? null : self::matn($text);

        return array_filter([
            'text' => $text,
            'matn' => $matn !== null && $matn !== '' && $matn !== $text ? $matn : null,
            // الموضعُ بلا الدرجة: الدرجةُ حقلٌ وحدها، فلا تُقال مرّتين.
            'reference' => $kind === 'ayah'
                ? $result->sourceRef
                : (self::withoutGrade($rendered)->citation(Locale::Ar) ?: $result->sourceRef),
            'url' => $rendered->url(Locale::Ar),
            'surah' => $rendered->surah,
            'ayah' => $rendered->ayah,
            'ayah_end' => $rendered->ayahEnd,
            'book' => $rendered->book,
            'number' => $rendered->hadithNumber,
            'narrator' => $rendered->narrator,
            'takhrij' => $rendered->takhrij,
            'grade' => $grade === null ? null : [
                'value' => $grade,
                'label' => self::string($meta['grade_label'] ?? null) ?? HadithGrade::tryFrom($grade)?->label(),
                'stated' => (bool) ($meta['has_stated_grade'] ?? $grade !== HadithGrade::Unknown->value),
            ],
            'graders' => is_array($meta['graders'] ?? null) && $meta['graders'] !== [] ? array_values($meta['graders']) : null,
            'similarity' => isset($meta['similarity']) ? (int) round((float) $meta['similarity'] * 100) : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * أقربُ آيةٍ للمقارنة — **بالرسم الإملائي** لتُقارَن بالنصّ كلمةً كلمة، فالرسمُ
     * العثمانيّ يكتب كلماتٍ كثيرة بغير حروف النصّ، فتُظلَّل كلُّها فرقاً.
     *
     * @param  array{ayah: QuranAyah, similarity: float}  $near
     * @return array<string, mixed>
     */
    private static function nearSource(array $near): array
    {
        $ayah = $near['ayah'];

        $rendered = new RenderedEvidence(
            kind: 'ayah',
            text: (string) $ayah->text_imlaei,
            sourceRef: $ayah->reference(),
            surah: (int) $ayah->surah,
            ayah: (int) $ayah->ayah,
        );

        return [
            'text' => (string) $ayah->text_imlaei,
            'reference' => $ayah->reference(),
            'url' => $rendered->url(Locale::Ar),
            'surah' => (int) $ayah->surah,
            'ayah' => (int) $ayah->ayah,
            'similarity' => (int) round($near['similarity'] * 100),
        ];
    }

    /**
     * المتن: المستخرِجُ يقصّ ما بعد الحديث وما يُعرف من سنده، ثمّ يُؤخذ منه
     * مطلعُ الحديث كما تأخذه قائمةُ التخريج (T-116). وكلاهما يُرجع النصّ كما
     * دخل متى لم يتبيّن، فلا يُقصّ من لفظ المصدر ما لا يُعرف أنّه سند.
     */
    private static function matn(string $text): string
    {
        $matn = MatnOpening::of(MatnExtractor::extract($text));

        /*
         * **وما بعد علامة الإغلاق ليس من الحديث**: المدوّنةُ تضع لفظَ النبي ﷺ
         * بين علامتي تنصيص، وما بعد الثانية تعليقُ راوٍ («قال وكانت عائشة…»).
         * والمطلعُ يبدأ بعد الأولى، فتُقصّ عند الثانية — ما بقي ثلاثُ كلماتٍ فأكثر.
         */
        $close = mb_strpos($matn, '"');

        if ($close !== false) {
            $head = trim(mb_substr($matn, 0, $close));

            if (count(preg_split('/\s+/u', $head) ?: []) >= 3) {
                return $head;
            }
        }

        return $matn;
    }

    private static function withoutGrade(RenderedEvidence $evidence): RenderedEvidence
    {
        return new RenderedEvidence(
            kind: $evidence->kind,
            text: $evidence->text,
            sourceRef: $evidence->sourceRef,
            takhrij: $evidence->takhrij,
            narrator: $evidence->narrator,
            book: $evidence->book,
            hadithNumber: $evidence->hadithNumber,
        );
    }

    /** @return array{code: string, text: string} */
    private static function reason(string $kind, string $quoted, ?VerificationResult $result, ?array $source): array
    {
        $code = match (true) {
            $result === null => 'no_source',
            $kind === 'ayah' && $result->status === MatchStatus::None && $source !== null => 'ayah_near',
            $kind === 'ayah' && $result->status === MatchStatus::None => 'ayah_none',
            // T-169: طابق بالتسامح — يُقال ذلك، ولا يُطوى في «مطابق» وحده.
            $kind === 'ayah' && isset($result->sourceMeta['tolerance']) => 'ayah_tolerant',
            $kind === 'ayah' && (bool) ($result->sourceMeta['spans_multiple'] ?? false) => 'ayah_two',
            $kind === 'ayah' && (bool) ($result->sourceMeta['is_fragment'] ?? false) => 'ayah_fragment',
            $kind === 'ayah' => 'ayah_exact',
            $result->status === MatchStatus::None && NarrationFormulas::isFormulaic(Arabic::normalize($quoted)) => 'formula',
            $result->status === MatchStatus::None => 'hadith_none',
            $result->status === MatchStatus::Partial => 'hadith_partial',
            default => 'hadith_exact',
        };

        return [
            'code' => $code,
            'text' => (string) __("verify.reasons.{$code}", [
                'source' => (string) ($source['reference'] ?? ''),
                'similarity' => Arabic::toArabicIndicDigits((int) ($source['similarity'] ?? 0)),
            ]),
        ];
    }

    /**
     * ملاحظاتٌ تُقال مع الحكم ولا تغيّره: الدرجة، واختلافُ الراوي المدّعى.
     *
     * @param  array<string, mixed>  $extracted
     * @return list<array{code: string, text: string}>
     */
    private static function notes(string $kind, array $extracted, ?VerificationResult $result): array
    {
        if ($result === null || ! $result->matched()) {
            return [];
        }

        if ($kind === 'ayah') {
            $dropped = $result->sourceMeta['tolerance']['dropped'] ?? [];

            return $dropped === [] ? [] : [[
                'code' => 'ayah_dropped',
                'text' => (string) __('verify.reasons.ayah_dropped', ['words' => '«'.implode(' ', $dropped).'»']),
            ]];
        }

        $meta = $result->sourceMeta;
        $notes = [];

        // الدرجةُ المنصوصة حقلٌ في المصدر تُرسم شارةً. ويُقال غيابُها هنا،
        // لأنّ غياب الدرجة خبرٌ يحتاجه المراجع ولا شارة له.
        if (! ($meta['has_stated_grade'] ?? false)) {
            $notes[] = ['code' => 'grade_unknown', 'text' => (string) __('verify.reasons.grade_unknown')];
        }

        // اقتباسُ بعض الحديث لا كلِّه — يُقال، فلفظُ المصدر المعروض أطولُ ممّا في النصّ.
        if ((bool) ($meta['is_fragment'] ?? false)) {
            $notes[] = ['code' => 'hadith_fragment', 'text' => (string) __('verify.reasons.hadith_fragment')];
        }

        $claimed = self::string($extracted['claimed_narrator'] ?? null);
        $narrator = self::string($meta['narrator'] ?? null);

        if (($meta['narrator_corrected'] ?? false) && $claimed !== null && $narrator !== null) {
            $notes[] = [
                'code' => 'narrator_differs',
                'text' => (string) __('verify.reasons.narrator_differs', ['claimed' => $claimed, 'narrator' => $narrator]),
            ];
        }

        return $notes;
    }

    private static function string(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
