<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Verification\QuoteGuard;
use Illuminate\Support\Facades\Log;

/**
 * حارسُ الاقتباس في نصوص المتن بعد الكتابة — T-160.
 *
 * يكمل {@see GuardEvidenceText}: ذاك يحرس كتل `evidence`، وهذا يحرس **ما
 * سواها** — الفقرات والبطاقات والأركان والعناوين والخاتمة. والقاعدة في
 * {@see QuoteGuard}: جملةٌ فيها آيةٌ أو حديثٌ لم يُتحقَّق منه تُحذف وحدها.
 *
 * **وكتلةٌ لم يبقَ فيها نصٌّ تسقط**، ولا تتوقّف المهمّة لذلك: النشر يمضي
 * بلا تلك الجملة، ويُقيَّد ما حُذف ليراه من يراجع السجلّ.
 */
final class GuardQuotedText
{
    /** مفاتيحُ الشكل لا النصّ — لا تُفحص ولا تُعدّ نصّاً في الكتلة. */
    private const FORM_KEYS = ['type', 'icon', 'kind', 'tone'];

    /**
     * @param  array<string, mixed>  $blocks  ما خرج من {@see GuardEvidenceText}.
     * @return array<string, mixed>
     */
    public function handle(SummaryJob $job, array $blocks): array
    {
        $guard = QuoteGuard::for($this->settledTexts($job));

        $sections = is_array($blocks['sections'] ?? null) ? $blocks['sections'] : [];

        $blocks['sections'] = array_map(
            fn (mixed $section): mixed => is_array($section) ? $this->guardSection($section, $guard) : $section,
            $sections,
        );

        if (is_string($blocks['closing'] ?? null)) {
            $blocks['closing'] = $guard->clean($blocks['closing']);
        }

        if ($guard->dropped() !== []) {
            Log::warning('quoted_text_guard.dropped', [
                'summary_job_id' => $job->id,
                'sentences' => $guard->dropped(),
            ]);
        }

        return $blocks;
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    private function guardSection(array $section, QuoteGuard $guard): array
    {
        if (is_string($section['heading'] ?? null)) {
            $section['heading'] = $guard->clean($section['heading']);
        }

        $items = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];

        $section['blocks'] = array_values(array_filter(array_map(
            fn (mixed $block): mixed => is_array($block) ? $this->guardBlock($block, $guard) : $block,
            $items,
        ), static fn (mixed $block): bool => $block !== null));

        return $section;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>|null
     */
    private function guardBlock(array $block, QuoteGuard $guard): ?array
    {
        // لفظُ الشاهد حرسه T-73 وثبّته على المصدر، فلا يُمسّ هنا.
        if ((string) ($block['type'] ?? '') === 'evidence') {
            return $block;
        }

        $guarded = $this->walk($block, $guard);

        return $this->hasText($guarded) ? $guarded : null;
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return array<array-key, mixed>
     */
    private function walk(array $node, QuoteGuard $guard): array
    {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::FORM_KEYS, true)) {
                continue;
            }

            if (is_string($value)) {
                $node[$key] = $guard->clean($value);
            } elseif (is_array($value)) {
                $node[$key] = $this->walk($value, $guard);
            }
        }

        return $node;
    }

    /** @param  array<array-key, mixed>  $node */
    private function hasText(array $node): bool
    {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array($key, self::FORM_KEYS, true)) {
                continue;
            }

            if ((is_string($value) && trim($value) !== '') || (is_array($value) && $this->hasText($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * ألفاظُ الشواهد المثبَّتة غير المحذوفة — كما يقرؤها {@see GuardEvidenceText}.
     *
     * @return list<string>
     */
    private function settledTexts(SummaryJob $job): array
    {
        return $job->evidenceItems()
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->get()
            ->map(static fn (EvidenceItem $item): string => (string) ($item->matched_text ?? $item->raw_text))
            ->all();
    }
}
