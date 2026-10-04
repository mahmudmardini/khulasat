<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Contracts\EvidenceVerifier;
use App\Enums\MatchStatus;
use App\Models\QuranAyah;
use App\Support\Arabic;
use App\Support\Quran\AyahText;
use App\Support\Quran\TolerantAlignment;
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

    /**
     * التسامحُ في اقتباسٍ من الحفظ — T-169، وقرار مالك المنتج في ٤ أكتوبر ٢٠٢٦.
     * أقصى ما يسقط من كلمات المصحف داخل الموضع، ثمّ يصير الاقتباسُ آيةً أخرى.
     */
    private const TOLERANCE_MAX_DROPPED = 3;

    /** وأقلُّ ما في الاقتباس من كلمات: ما دونها يوافق مواضعَ كثيرة بالتسامح. */
    private const TOLERANCE_MIN_WORDS = 4;

    /** ومرشّحو المحاذاة: الآياتُ التي فيها أطولُ كلمات الاقتباس. */
    private const TOLERANCE_CANDIDATES = 100;

    public function verify(EvidenceInput $input): VerificationResult
    {
        $needle = Arabic::normalize($input->rawText);

        if ($needle === '') {
            return VerificationResult::none();
        }

        return $this->matchSingleAyah($needle)
            ?? $this->matchAcrossTwoAyat($needle)
            ?? $this->matchTolerantly($needle)
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

    /**
     * اقتباسٌ من الحفظ — T-169: كلمةٌ ساقطة، أو همزةٌ منفصلة، أو كلمةٌ مكرّرة.
     *
     * ★ **ويُنشر لفظ المصحف لا لفظ المتكلّم**، كاملاً بآيته أو آيتيه، فلا يُنشر
     * نصٌّ قرآنيٌّ ناقصٌ أبداً. وما سقط أو صُحّح يُرفع في `source_meta.tolerance`
     * ليراه من يراجع. **وقرارُ مالك المنتج أن يُنشر تلقائياً** (`exact`).
     *
     * **ومطابقةٌ واحدةٌ فقط**: إن وافق الاقتباسُ بالتسامح موضعين في المصحف لم
     * يُختر أحدُهما — يعود `none`. فالتسامحُ لا يُحسم به ما يحتمل اثنين.
     */
    private function matchTolerantly(string $needle): ?VerificationResult
    {
        $quote = explode(' ', $needle);

        if (count($quote) < self::TOLERANCE_MIN_WORDS) {
            return null;
        }

        $found = [];

        foreach ($this->toleranceCandidates($quote) as $candidate) {
            foreach ($this->windowsAround($candidate) as $window) {
                $match = $this->alignOn($quote, $window);

                if ($match !== null) {
                    $found[$match['location']] ??= $match;
                }
            }
        }

        if (count($found) !== 1) {
            return null;
        }

        $match = array_values($found)[0];
        $tolerance = [
            'dropped' => $match['alignment']['dropped'],
            'joined' => $match['alignment']['joined'],
            'repeated' => $match['alignment']['repeated'],
        ];

        [$first, $last] = [$match['ayat'][0], $match['ayat'][count($match['ayat']) - 1]];

        if ($first->is($last)) {
            $meta = [
                'surah_number' => $first->surah,
                'ayah_number' => $first->ayah,
                'surah_name_ar' => $first->surah_name_ar,
                'is_fragment' => $match['fragment'],
                'tolerance' => $tolerance,
            ];

            return new VerificationResult(
                status: MatchStatus::Exact,
                matchedText: AyahText::decorate($first->text_uthmani, $meta),
                sourceRef: $first->reference(),
                sourceMeta: $meta,
            );
        }

        $spanning = $this->spanning($first, $last);

        return new VerificationResult(
            status: $spanning->status,
            matchedText: $spanning->matchedText,
            sourceRef: $spanning->sourceRef,
            sourceMeta: [...$spanning->sourceMeta, 'tolerance' => $tolerance],
        );
    }

    /**
     * الآياتُ التي فيها أطولُ كلمات الاقتباس — **بعد حذف المسافات**، فتوجد
     * الكلمةُ ولو كتبها المصحفُ كلمتين أو كتبها الاقتباسُ منفصلة.
     *
     * @param  list<string>  $quote
     * @return iterable<QuranAyah>
     */
    private function toleranceCandidates(array $quote): iterable
    {
        $anchors = $quote;
        usort($anchors, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $anchors = array_slice(array_values(array_unique($anchors)), 0, 2);

        $query = QuranAyah::query();

        foreach ($anchors as $anchor) {
            $query->whereRaw("replace(text_normalized, ' ', '') LIKE ?", ['%'.self::escapeLike($anchor).'%']);
        }

        $candidates = $query->orderBy('surah')->orderBy('ayah')->limit(self::TOLERANCE_CANDIDATES)->get();

        // كلمتا الوصل قد تقعان في آيتين — فتُجرَّب أطولُها وحدها إن لم تجتمعا في آية.
        if ($candidates->isEmpty() && count($anchors) > 1) {
            $candidates = QuranAyah::query()
                ->whereRaw("replace(text_normalized, ' ', '') LIKE ?", ['%'.self::escapeLike($anchors[0]).'%'])
                ->orderBy('surah')->orderBy('ayah')->limit(self::TOLERANCE_CANDIDATES)->get();
        }

        return $candidates;
    }

    /**
     * الآيةُ وحدها، ومع سابقتها، ومع لاحقتها — في السورة نفسها.
     *
     * @return list<list<QuranAyah>>
     */
    private function windowsAround(QuranAyah $ayah): array
    {
        $neighbours = QuranAyah::query()
            ->where('surah', $ayah->surah)
            ->whereIn('ayah', [$ayah->ayah - 1, $ayah->ayah + 1])
            ->get()
            ->keyBy('ayah');

        $windows = [[$ayah]];

        if ($neighbours->has($ayah->ayah - 1)) {
            $windows[] = [$neighbours[$ayah->ayah - 1], $ayah];
        }

        if ($neighbours->has($ayah->ayah + 1)) {
            $windows[] = [$ayah, $neighbours[$ayah->ayah + 1]];
        }

        return $windows;
    }

    /**
     * @param  list<string>  $quote
     * @param  list<QuranAyah>  $window
     * @return array{location: string, ayat: list<QuranAyah>, alignment: array<string, mixed>, fragment: bool}|null
     */
    private function alignOn(array $quote, array $window): ?array
    {
        $words = [];
        $owner = [];

        foreach ($window as $position => $ayah) {
            foreach (explode(' ', $ayah->text_normalized) as $word) {
                $words[] = $word;
                $owner[] = $position;
            }
        }

        $alignment = TolerantAlignment::align($quote, $words, self::TOLERANCE_MAX_DROPPED);

        if ($alignment === null) {
            return null;
        }

        // الآياتُ التي وقع فيها الاقتباسُ فعلاً — ونافذةُ الآيتين قد تحوي آيةً واحدة.
        $positions = array_values(array_unique(array_slice($owner, $alignment['start'], $alignment['end'] - $alignment['start'] + 1)));
        $ayat = array_map(static fn (int $position): QuranAyah => $window[$position], $positions);

        $first = $ayat[0];
        $last = $ayat[count($ayat) - 1];

        return [
            'location' => $first->surah.':'.$first->ayah.'-'.$last->ayah,
            'ayat' => $ayat,
            'alignment' => $alignment,
            'fragment' => $alignment['start'] > 0 || $alignment['end'] < count($words) - 1,
        ];
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
