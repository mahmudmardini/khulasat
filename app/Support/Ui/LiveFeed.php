<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Models\SummaryJob;

/**
 * What the follow screen shows beside the steps — T-83، ونُقّح في T-92.
 *
 * **ما يُعرض هنا حقيقيٌّ كلُّه.** ملامحُ من `structure_json` كما استُخرجت،
 * وحصيلةٌ بأرقامها (كلمات التفريغ · الشواهد · لغات الإخراج). **ولا نداءَ
 * نموذج** — CLAUDE.md §2: الانتظار لا يُصرف عليه.
 *
 * ★ **ولا نصَّ آيةٍ ولا حديثٍ قبل التحقّق.** `key_ayah` والشواهد كتبها
 * النموذج ولم تُطابَق بعدُ بمصادرها، وعرضُها في شاشة المنتج — ولو للحظة —
 * عرضٌ لما قد يكون محرَّفاً قبل أن تمرّ عليه طبقةُ التحقّق.
 *
 * **و«من نصّ الدرس» أُزيلت** — T-92، بملاحظة مالك المنتج: ترجماتُ يوتيوب
 * بلا ترقيمٍ ولا همزات تُقرأ ضجيجاً، والملامحُ تقول ما يُفهم منه.
 */
final class LiveFeed
{
    private const MAX_HIGHLIGHTS = 8;

    /** وفوق أربعين كلمة لا يُقرأ ملمحٌ في بطاقةٍ تتبدّل، فيُقطع ويُعلَّم قطعُه. */
    private const MAX_HIGHLIGHT_WORDS = 40;

    private function __construct() {}

    /**
     * @return array{
     *     highlights: list<array{kind: string, text: string, detail: string|null}>,
     *     word_count: int|null,
     *     evidence_count: int|null,
     *     locales: list<string>,
     * }
     */
    public static function of(SummaryJob $job): array
    {
        /*
         * **الملامحُ للانتظار وحده.** فبعد أن يقف الخطّ تعرض الشاشة الخلاصة،
         * ونصٌّ يُرسل ولا يُعرض ثقلٌ بلا غرض. و`needs_review` منها: الوقوفُ
         * عند المستخدم لا عندنا. **والحصيلةُ تبقى** — هي ما يُقرأ بعد الانتهاء.
         */
        $running = ! $job->state->isTerminal() && $job->state !== JobState::NeedsReview;

        return [
            'highlights' => $running ? self::highlights((array) ($job->structure_json ?? [])) : [],
            'word_count' => $job->transcript_text === null ? null : $job->transcript_word_count,
            'evidence_count' => self::evidenceCount($job),
            'locales' => array_values(array_map(
                static fn (Locale $locale): string => $locale->label(),
                $job->lecture?->outputLocales() ?? [Locale::source()],
            )),
        ];
    }

    /**
     * ملامحُ الدرس من بنيته: الفكرةُ الجامعة والتشخيصُ والمحاور.
     *
     * و`closing_line` عمداً غائبة: سقالةٌ لصياغة المرحلة الخامسة لا نصٌّ
     * للعرض (كما في {@see \App\Support\Render\ContentObject}). و`key_ayah`
     * غائبةٌ لعلّةٍ أشدّ — رأسُ هذا الصنف.
     *
     * @param  array<string, mixed>  $structure
     * @return list<array{kind: string, text: string, detail: string|null}>
     */
    private static function highlights(array $structure): array
    {
        $items = [];

        foreach (['core_concept' => 'concept', 'diagnosis' => 'diagnosis'] as $field => $kind) {
            $text = self::text($structure[$field] ?? null);

            if ($text !== null) {
                $items[] = ['kind' => $kind, 'text' => $text, 'detail' => null];
            }
        }

        foreach ((array) ($structure['axes'] ?? []) as $axis) {
            $name = is_array($axis) ? self::text($axis['name'] ?? null) : null;

            if ($name !== null) {
                $items[] = ['kind' => 'axis', 'text' => $name, 'detail' => self::text($axis['summary'] ?? null)];
            }
        }

        return array_slice($items, 0, self::MAX_HIGHLIGHTS);
    }

    /**
     * كم شاهداً وُجد — **ولا عددَ قبل الاستخراج**، فالصفر يُقرأ «لا شواهد».
     *
     * و`evidence_json` يُكتب ساعةَ الاستخراج، وصفوفُ الشواهد تُنشأ بعده في
     * التحقّق. فتُعدّ الصفوف إن وُجدت، وإلّا فما استُخرج — وإلّا قالت الشاشة
     * «لم تُستخرج شواهد» والتحقّقُ يجري على شاهدين.
     */
    private static function evidenceCount(SummaryJob $job): ?int
    {
        if ($job->evidence_json === null) {
            return null;
        }

        $rows = $job->evidenceItems()->count();

        return $rows > 0 ? $rows : count((array) $job->evidence_json);
    }

    /** نصٌّ قصير يصلح للعرض، أو `null`. */
    private static function text(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return count($words) > self::MAX_HIGHLIGHT_WORDS
            ? implode(' ', array_slice($words, 0, self::MAX_HIGHLIGHT_WORDS)).' …'
            : implode(' ', $words);
    }
}
