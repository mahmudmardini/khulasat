<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\ReviewStatus;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;
use App\Support\Render\BodyBlocks;

/**
 * Stage 5 — writing the body — PROMPT-PACK المرحلة ٥، والمواصفة §6-4.
 *
 * **«لا يُستدعى النموذج قبل انتهاء التحقّق. الشواهد تدخل إليه مثبَّتة، فلا
 * يملك أن يغيّرها»** — §6-4. وهذا هو الحاجز الأخير قبل أن يُكتب نصٌّ يُنشر
 * باسم جهةٍ شرعية.
 *
 * ويُفرض هنا لا في آلة الحالات وحدها: حارسُ {@see TransitionJob} يمسك
 * `needs_review → writing` و`→ published`، **ولا يمسك `verifying → writing`**.
 * فمهمّةٌ لم تمرّ بالمراجعة أصلاً كانت تعبر بشاهدٍ معلّق. والمنع هنا عند
 * موضع الضرر: قبل النداء، لا بعده.
 *
 * وتدخل الشواهد **بلفظ مصدرها** (`matched_text`) لا بلفظ المحاضرة، والمحذوفة
 * لا تدخل أصلاً. فالنموذج يكتب حولها ولا يملك تغييرها.
 *
 * ★ **ويدخل التفريغ المنظَّف معها** — T-52. وكانت المادّة بنيةً وشواهدَ فقط،
 * فيكتب النموذج على هيكلٍ من بضعة أسطر (`axes[].summary` جملةٌ لكلّ محور)
 * **فيخرج المتن هزيلاً**: قيس على المهمّة ٢٨ فكان مخرَجُ الكتابة ~٢٬٨٨٠ توكناً
 * لا مصدرَ فيها لسياقٍ ولا قصّةٍ ولا صورة. والمرجع (`reference-summary.html`)
 * كُتب من التفريغ كاملاً، **ولا نموذجَ يُخرج غِنًى من فراغ**.
 *
 * **والقسمة بين المدخلين محفوظة في التعليمات:** التفريغُ مادّةُ تفصيل،
 * **والبنيةُ حاكمةٌ للتقسيم** — فلا يستخرج النموذجُ من التفريغ تقسيماً آخر.
 * وللصفحة إلى جانب المحاور أقسامٌ من حقول البنية نفسها — التشخيص والميزان
 * والمسار والتطبيق (T-71) — **فهي من البنية لا من التفريغ**، والقيدُ باقٍ.
 * وأخطرُ ما استُحدث أنّ النموذج صار يرى شواهدَ أسقطها التحقّق، فنُصَّ عليه:
 * «ولا تضف شاهداً من عندك **ولو رأيته في التفريغ**».
 */
final class WriteBody
{
    use RunsAStage;

    /**
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): string
    {
        $this->assertEvidenceSettled($job);

        $structure = $job->structure_json ?? [];

        if ($structure === []) {
            throw ModelCallFailed::permanent('structure_missing', 'لا بنية يُكتب عليها المتن.', Stage::Writing);
        }

        $transcript = trim((string) $job->transcript_text);

        if ($transcript === '') {
            // **ولا يُكتب المتنُ على البنية وحدها بعد اليوم.** كان يُكتب،
            // فكان يخرج هزيلاً — وسقوطٌ معلَنٌ خيرٌ من انحدارٍ صامت.
            throw ModelCallFailed::permanent('transcript_missing', 'لا تفريغ يُكتب منه المتن.', Stage::Writing);
        }

        $material = (string) json_encode([
            'transcript' => $transcript,
            'structure' => $structure,
            'evidence' => $this->settledEvidence($job),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /*
         * **الكتل تُحفظ والمتن يُرسم منها** — T-43. فالنموذج لم يعد يكتب
         * أصنافاً بيده، ولا يُرجى منه HTML سليم: يُخرج كتلاً يتحقّق المخطّط
         * منها، ويكتب {@see BodyBlocks} أصنافَ المهارة.
         */
        $blocks = $this->runStage(Stage::Writing, $material, $job)->decoded;

        if ($blocks === null) {
            throw ModelCallFailed::permanent('body_missing', 'لم تعد كتل المتن.', Stage::Writing);
        }

        // **الحاجزُ الأخير قبل الرسم** — T-73. تدخل الشواهدُ مثبَّتةً، ولا
        // شيء بعدها يضمن أنّ النموذج نقلها كما دخلته. فيُعيد هذا الحارسُ
        // لفظَ المصدر مكان لفظ النموذج، ويُسقط ما لا يقابل شاهداً مثبَّتاً.
        $blocks = app(GuardEvidenceText::class)->handle($job, $blocks);

        // ★ **وما خارج كتل الشاهد** — T-160. حديثٌ نقله النموذج داخل فقرةٍ
        // لم يمرّ بحارسٍ قبله، فتُحذف جملتُه إن لم يكن محقَّقاً.
        $blocks = app(GuardQuotedText::class)->handle($job, $blocks);

        $html = BodyBlocks::toHtml($blocks);

        if (trim($html) === '') {
            // كتلٌ كلُّها فارغة تعني متناً بلا نصّ. **والصفحة الفارغة تُنشر
            // ولا تُقرأ**، فتقف المهمّة هنا بدل أن تصل إلى النشر.
            throw ModelCallFailed::permanent('body_empty', 'كتل المتن كلّها فارغة.', Stage::Writing);
        }

        $job->forceFill(['body_json' => $blocks, 'body_html' => $html])->save();

        app(TransitionJob::class)->handle($job, JobState::Rendering);

        return $html;
    }

    /**
     * **لا نداء ونحن ننتظر إنساناً.**
     *
     * @throws ModelCallFailed
     */
    private function assertEvidenceSettled(SummaryJob $job): void
    {
        $pending = $job->pendingEvidenceCount();

        if ($pending > 0) {
            throw ModelCallFailed::permanent(
                'evidence_unsettled',
                "لا يُكتب المتن و{$pending} شاهداً لم يُحسم بعد.",
                Stage::Writing,
            );
        }
    }

    /**
     * الشواهد المعتمدة **بألفاظ مصادرها** — §6-4.
     *
     * والمحذوفة (`removed`) لا تدخل: حسمَ الإنسانُ بحذفها، وإدخالُها ليكتب
     * النموذج حولها يُعيدها من الباب الذي أُخرجت منه.
     *
     * ويُقدَّم `matched_text` على `raw_text` دائماً، فما يُنشر لفظُ المصدر
     * لا لفظُ المحاضرة.
     *
     * @return list<array<string, mixed>>
     */
    private function settledEvidence(SummaryJob $job): array
    {
        return $job->evidenceItems()
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->get()
            ->map(static fn (EvidenceItem $item): array => array_filter([
                'kind' => $item->kind,
                'text' => $item->matched_text ?? $item->raw_text,
                'source_ref' => $item->source_ref,
                'review_status' => $item->review_status?->value,

                /*
                 * **الدرجة والتخريج يُمرَّران، ومصدرهما المحقّق لا النموذج**
                 * — §7-5 وT-12. فسياسة البيان لا تقوم إلا بهما: منشورٌ
                 * ضعيفٌ بلا بيان درجته يُسقط البوّابة، وهي قاعدة حاجبة
                 * في `fixtures/evidence-fixtures.json`.
                 *
                 * وحزمة التعليمات تقول «بتخريجها المرفق» — فالمرفق هذا،
                 * ولا يُعدَّل نصُّها (CLAUDE.md §2 القاعدة الثانية).
                 */
                'takhrij' => $item->meta('takhrij'),
                'grade' => $item->meta('grade'),
            ], static fn (mixed $value): bool => $value !== null))
            ->values()
            ->all();
    }
}
