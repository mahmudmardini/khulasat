<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Arabic;

/**
 * حارسُ نصّ الشاهد بعد الكتابة — T-73، الحدّ الثالث في CLAUDE.md §2.
 *
 * **مرحلة ٤ تتحقّق من الشاهد المستخرَج في مرحلة ٣، لا من المتن الذي تكتبه
 * مرحلة ٥.** فكانت `BodyBlocks::evidence()` ترسم `text` كما كتبه نموذجُ
 * الكتابة بلا مقارنةٍ بـ`matched_text` المثبَّت — ونموذجٌ أمينٌ اليوم قد
 * يُبدّل كلمةً في حديثٍ غداً، فيُنشر باسم جهةٍ شرعية.
 *
 * **حتميٌّ بلا نموذج** (الحدّ الثالث، CLAUDE.md §2 القاعدة الثالثة):
 * يقارن كلَّ كتلة `evidence` بالشواهد المثبَّتة غير المحذوفة لهذه المهمّة،
 * مطابقةً بعد `Arabic::normalize()`. وإذ يكتب النموذج نصّاً لصيقاً بلفظ
 * المصدر غالباً (خُولف حرفٌ أو كلمة)، فالمطابقة تقيس أقرب شاهدٍ لا تساويه
 * فقط — وإلا سقط كلُّ تبديلٍ يسيرٍ من المطابقة الحرفية فسقطت الكتلة معه.
 *
 * **قرارٌ صريحٌ من مالك المنتج، 10 أيلول 2026:** ما طابق — ولو بتبديل
 * كلمة — يُستبدل نصُّه بالمثبَّت حرفاً، فينشر لفظ المصدر لا لفظ النموذج.
 * وما لم يطابق شيئاً أصلاً — شاهدٌ لم يصل الكاتبَ في مادّته — تسقط كتلتُه:
 * لا نصّ مثبَّتٌ يُستبدل به نصٌّ لا مصدر له، **ولا يُنشر ما لم يُتحقّق منه.**
 * ولا تتوقّف المهمّة لذلك: النشر يمضي بلا هذه الكتلة وحدها.
 *
 * **والالتباس بين شاهدين مثبَّتين متقاربَي اللفظ يُعامَل كعدم المطابقة**
 * — لا يخمّن الحارس. فلو استشهدت المحاضرةُ بروايتين متقاربتين لحديثٍ
 * واحد، وكانت كتلةُ الشاهد أقرب إلى الأولى بفارقٍ ضئيل عن الثانية،
 * فاستبدالها بأيّهما نسبةٌ لفظٍ لمصدرٍ لم يُتحقَّق أنّه المقصود فعلاً —
 * وذلك أخطر من إسقاط الكتلة. ولا يُطبَّق هذا حين يطابق النصّ شاهداً
 * مطابقةً شبه حرفية (فوق {@see self::NEAR_EXACT_THRESHOLD}): تطابقٌ بهذا
 * القرب دليلٌ حاسمٌ على الشاهد المقصود مهما قارَبه شاهدٌ آخر.
 */
final class GuardEvidenceText
{
    /**
     * أدنى نسبة تشابهٍ (`similar_text`) تُقبل بها الكتلة مطابقةً لشاهد.
     *
     * قيسَت على شواهد حقيقية: تبديلُ كلمةٍ واحدة في حديثٍ متوسّط الطول
     * يبقى فوق ٩٣٪، وشاهدٌ لا صلة له بالمادّة يقع دون ٤٠٪ غالباً.
     */
    private const MATCH_THRESHOLD = 65.0;

    /** فوقها التطابقُ حاسمٌ فلا حاجةَ لفارق عن ثاني أقرب شاهد. */
    private const NEAR_EXACT_THRESHOLD = 98.0;

    /**
     * أدنى فارقٍ بين أقرب شاهدين مثبَّتين ليُقبل الأقرب دون التباس.
     *
     * دون هذا الفارق لا يُميَّز الحارسُ أيّهما المقصود، فتسقط الكتلة —
     * أسلمُ من نسبة لفظٍ لمصدرٍ لم يثبت أنّه هو.
     */
    private const AMBIGUITY_MARGIN = 10.0;

    /**
     * @param  array<string, mixed>  $blocks  ما تحقّق من مخطّط المرحلة ٥.
     * @return array<string, mixed>
     */
    public function handle(SummaryJob $job, array $blocks): array
    {
        $settled = $this->settledCandidates($job);

        $sections = is_array($blocks['sections'] ?? null) ? $blocks['sections'] : [];

        $blocks['sections'] = array_map(
            fn (mixed $section): mixed => is_array($section) ? $this->guardSection($section, $settled) : $section,
            $sections,
        );

        return $blocks;
    }

    /** @param  list<array{text: string, normalized: string}>  $settled */
    private function guardSection(array $section, array $settled): array
    {
        $items = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];

        $section['blocks'] = array_values(array_filter(array_map(
            fn (mixed $block): mixed => is_array($block) ? $this->guardBlock($block, $settled) : $block,
            $items,
        ), static fn (mixed $block): bool => $block !== null));

        return $section;
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  list<array{text: string, normalized: string}>  $settled
     * @return array<string, mixed>|null الكتلة نفسها لغير الشواهد، أو مصحَّحةً، أو null لتسقط.
     */
    private function guardBlock(array $block, array $settled): ?array
    {
        if ((string) ($block['type'] ?? '') !== 'evidence') {
            return $block;
        }

        $text = trim((string) ($block['text'] ?? ''));

        if ($text === '') {
            // `BodyBlocks::evidence()` تُسقطها فارغةً على كلّ حال.
            return $block;
        }

        $match = $this->bestMatch($text, $settled);

        if ($match === null) {
            return null;
        }

        $block['text'] = $match['text'];

        return $block;
    }

    /**
     * @param  list<array{text: string, normalized: string}>  $settled
     * @return array{text: string}|null
     */
    private function bestMatch(string $text, array $settled): ?array
    {
        $normalized = Arabic::normalize($text);

        /** @var array<string, array{text: string, percent: float}> $scored */
        $scored = [];

        foreach ($settled as $candidate) {
            similar_text($normalized, $candidate['normalized'], $percent);

            // شاهدان مثبَّتان بلفظٍ مطبَّعٍ واحد ليسا مرشَّحين متنافسين، بل
            // شاهداً واحداً كُرِّر — فلا يُحسب فارقٌ صفريٌّ بينهما التباساً.
            $existing = $scored[$candidate['normalized']] ?? null;

            if ($existing === null || $percent > $existing['percent']) {
                $scored[$candidate['normalized']] = ['text' => $candidate['text'], 'percent' => $percent];
            }
        }

        if ($scored === []) {
            return null;
        }

        $scored = array_values($scored);
        usort($scored, static fn (array $a, array $b): int => $b['percent'] <=> $a['percent']);

        $best = $scored[0];

        if ($best['percent'] < self::MATCH_THRESHOLD) {
            return null;
        }

        if ($best['percent'] >= self::NEAR_EXACT_THRESHOLD) {
            return $best;
        }

        $runnerUpPercent = $scored[1]['percent'] ?? 0.0;

        return $best['percent'] - $runnerUpPercent >= self::AMBIGUITY_MARGIN ? $best : null;
    }

    /**
     * الشواهد المثبَّتة غير المحذوفة لهذه المهمّة، بلفظ مصدرها — كما تدخل
     * الكاتبَ في {@see WriteBody::settledEvidence()} تماماً.
     *
     * @return list<array{text: string, normalized: string}>
     */
    private function settledCandidates(SummaryJob $job): array
    {
        return $job->evidenceItems()
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->get()
            ->map(static function (EvidenceItem $item): array {
                $text = $item->matched_text ?? $item->raw_text;

                return ['text' => $text, 'normalized' => Arabic::normalize($text)];
            })
            ->all();
    }
}
