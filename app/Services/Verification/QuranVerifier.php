<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\EvidenceVerifier;
use App\Enums\MatchStatus;
use App\Models\QuranAyah;
use App\Support\Arabic;
use App\Support\Quran\AyahText;
use App\Support\Verification\EvidenceInput;
use App\Support\Verification\VerificationResult;

/**
 * Matches a quoted ayah against the local Qur'an — المواصفة §7-2.
 *
 * **المطابقة هنا حتمية، لا احتمالية.** القرآن يُطابَق حرفياً أو لا يُطابَق:
 * لا عتبة تشابه، ولا أقرب تطابق، ولا سؤال نموذج. وما لم يُطابَق يعود `none`
 * ويُرفع للمراجعة، ولا يُملأ الفراغ باجتهاد.
 *
 * والمطابقة تجري على `text_imlaei` المطبَّع لا على العثماني، لأنّ رسم
 * المصحف يخالف الإملاء المعاصر الذي يكتب به المفرِّغ — انظر T-03ب.
 * **ويُعاد اللفظ عثمانياً دائماً**، فلا يرى القارئ إلا رسم المصحف.
 */
class QuranVerifier implements EvidenceVerifier
{
    /**
     * أقصى ما يُفحص من مرشّحي الجانب الأندر عند كلّ موضعِ وصل — T-168.
     * والجانبُ الأطول نادرُ الوقوع، فخمسون أوسعُ ممّا يُحتاج إليه عادةً.
     */
    private const SPAN_CANDIDATES = 50;

    public function verify(EvidenceInput $input): VerificationResult
    {
        $needle = Arabic::normalize($input->rawText);

        if ($needle === '') {
            return VerificationResult::none();
        }

        return $this->matchSingleAyah($needle)
            ?? $this->matchAcrossTwoAyat($needle)
            ?? VerificationResult::none();
    }

    /**
     * النصّ داخل آية واحدة.
     */
    private function matchSingleAyah(string $needle): ?VerificationResult
    {
        $ayah = QuranAyah::query()
            ->whereRaw('text_normalized LIKE ?', ['%'.$needle.'%'])
            ->orderBy('surah')
            ->orderBy('ayah')
            ->first();

        if ($ayah === null) {
            return null;
        }

        $meta = [
            'surah_number' => $ayah->surah,
            'ayah_number' => $ayah->ayah,
            'surah_name_ar' => $ayah->surah_name_ar,
            // جزءٌ من آية لا آية تامّة — يُذكر في التخريج «من الآية كذا».
            'is_fragment' => $ayah->text_normalized !== $needle,
        ];

        return new VerificationResult(
            status: MatchStatus::Exact,
            // **مقوَّساً ومعلَّماً كما يُنشر** — T-56، و{@see AyahText}.
            matchedText: AyahText::decorate($ayah->text_uthmani, $meta),
            sourceRef: $ayah->reference(),
            sourceMeta: $meta,
        );
    }

    /**
     * النصّ ممتدّ على آيتين متتاليتين في السورة نفسها.
     *
     * **متتاليتين في سورة واحدة، لا أيّ آيتين.** ولو جُرّب كلّ زوج لأمكن
     * تركيب نصّ من سورتين مختلفتين فيُقبل، وهو بالضبط ما تمنعه الحالة
     * `Q-STITCHED-01` في عيّنة القبول.
     *
     * ★ **ويُجرَّب كلُّ موضعٍ للوصل** — T-168. والنصُّ الممتدّ آخرُ الأولى
     * ثمّ أوّلُ الثانية، ولا يُعرف أين ينتهي هذا ويبدأ ذاك. وكان المِجسّ
     * يفترض أنّ أوّل أربع كلماتٍ هي آخرُ الأولى بالضبط، فلا يُطابَق اقتباسٌ
     * إلّا إن بدأ في موضعٍ بعينه — صدفةً. فيُقسَم النصّ عند كلّ كلمة: ما
     * قبلها يجب أن يكون آخرَ آية، وما بعدها أوّلَ التي تليها، **بحدود
     * الكلمات** لا بأجزائها.
     */
    private function matchAcrossTwoAyat(string $needle): ?VerificationResult
    {
        $words = explode(' ', $needle);
        $count = count($words);

        for ($split = 1; $split < $count; $split++) {
            $left = implode(' ', array_slice($words, 0, $split));
            $right = implode(' ', array_slice($words, $split));

            $pair = $this->pairAt($left, $right, $split >= $count - $split);

            if ($pair !== null) {
                return $this->spanning(...$pair);
            }
        }

        return null;
    }

    /**
     * آيتان متتاليتان: الأولى تنتهي بـ`$left`، والثانية تبدأ بـ`$right`.
     *
     * **ويُبحث من الجانب الأطول** لأنّه الأندر: كلمةٌ واحدة تنتهي بها مئاتُ
     * الآيات، وعشرُ كلماتٍ لا تنتهي بها إلّا آيةٌ أو اثنتان. ثمّ تُفحص جارتُها.
     *
     * @return array{0: QuranAyah, 1: QuranAyah}|null
     */
    private function pairAt(string $left, string $right, bool $fromFirst): ?array
    {
        $candidates = QuranAyah::query()
            ->where(function ($query) use ($left, $right, $fromFirst): void {
                $text = $fromFirst ? $left : $right;

                $query->where('text_normalized', $text)->orWhereRaw(
                    'text_normalized LIKE ?',
                    [$fromFirst ? '% '.self::escapeLike($text) : self::escapeLike($text).' %'],
                );
            })
            ->orderBy('surah')
            ->orderBy('ayah')
            ->limit(self::SPAN_CANDIDATES)
            ->get();

        foreach ($candidates as $candidate) {
            $neighbour = QuranAyah::query()
                ->where('surah', $candidate->surah)
                ->where('ayah', $candidate->ayah + ($fromFirst ? 1 : -1))
                ->first();

            if ($neighbour === null) {
                continue;
            }

            [$first, $second] = $fromFirst ? [$candidate, $neighbour] : [$neighbour, $candidate];

            if (self::endsWithWords($first->text_normalized, $left) && self::startsWithWords($second->text_normalized, $right)) {
                return [$first, $second];
            }
        }

        return null;
    }

    private function spanning(QuranAyah $first, QuranAyah $second): VerificationResult
    {
        return new VerificationResult(
            status: MatchStatus::Exact,
            matchedText: AyahText::decorate($first->text_uthmani.' '.$second->text_uthmani, [
                'ayah_number' => $first->ayah,
                'ayah_number_end' => $second->ayah,
                'ayah_break_at' => mb_strlen($first->text_uthmani),
            ]),
            sourceRef: sprintf(
                'سورة %s، الآيتان %s و%s',
                $first->surah_name_ar,
                Arabic::toArabicIndicDigits($first->ayah),
                Arabic::toArabicIndicDigits($second->ayah),
            ),
            sourceMeta: [
                'surah_number' => $first->surah,
                'ayah_number' => $first->ayah,
                'ayah_number_end' => $second->ayah,
                'surah_name_ar' => $first->surah_name_ar,
                'spans_multiple' => true,
                // موضعُ وصل الآيتين — به تُعلَّم الأولى فلا تُقرآن واحدة.
                'ayah_break_at' => mb_strlen($first->text_uthmani),
            ],
        );
    }

    private static function endsWithWords(string $text, string $words): bool
    {
        return $text === $words || str_ends_with($text, ' '.$words);
    }

    private static function startsWithWords(string $text, string $words): bool
    {
        return $text === $words || str_starts_with($text, $words.' ');
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
