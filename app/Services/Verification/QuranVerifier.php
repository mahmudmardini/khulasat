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
    /** عدد الكلمات التي تُلتقط من أوّل النصّ للبحث عن آية مجاورة. */
    private const SPAN_PROBE_WORDS = 4;

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
     */
    private function matchAcrossTwoAyat(string $needle): ?VerificationResult
    {
        foreach ($this->probeCandidates($needle) as $first) {
            $second = QuranAyah::query()
                ->where('surah', $first->surah)
                ->where('ayah', $first->ayah + 1)
                ->first();

            if ($second === null) {
                continue;
            }

            $joined = $first->text_normalized.' '.$second->text_normalized;

            if (! str_contains($joined, $needle)) {
                continue;
            }

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

        return null;
    }

    /**
     * الآيات المرشّحة لتكون أوّل الزوج: ما احتوى صدرَ النصّ المطلوب.
     *
     * @return iterable<QuranAyah>
     */
    private function probeCandidates(string $needle): iterable
    {
        $words = explode(' ', $needle);

        if (count($words) <= self::SPAN_PROBE_WORDS) {
            return [];
        }

        $probe = implode(' ', array_slice($words, 0, self::SPAN_PROBE_WORDS));

        return QuranAyah::query()
            ->whereRaw('text_normalized LIKE ?', ['%'.$probe])
            ->orderBy('surah')
            ->orderBy('ayah')
            ->limit(20)
            ->get();
    }
}
