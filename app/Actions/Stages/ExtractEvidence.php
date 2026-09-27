<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;

/**
 * Stage 3 — pulling the quoted evidence out — PROMPT-PACK المرحلة ٣.
 *
 * **أخطر مرحلةٍ في المنتج.** تعليماتها تمنع النموذج منعاً صريحاً أن يصحّح
 * اللفظ أو يكمله أو يستبدل به ما يعرفه: «انقل لفظ الشاهد كما ورد في
 * التفريغ حرفاً بحرف». والسبب أنّ التصحيح من حفظ النموذج يُنتج شاهداً
 * **لم يقله المتحدّث**، ثم يمرّ التحقّق لأنّ اللفظ صار مطابقاً للمصدر.
 *
 * والتصحيح وظيفة طبقة التحقّق وحدها — المواصفة §6-3 وCLAUDE.md §2.
 */
final class ExtractEvidence
{
    use RunsAStage;

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): array
    {
        $transcript = (string) $job->transcript_text;

        if (trim($transcript) === '') {
            throw ModelCallFailed::permanent('transcript_missing', 'لا نصّ لاستخراج شواهده.', Stage::ExtractingEvidence);
        }

        $decoded = $this->runStage(Stage::ExtractingEvidence, $transcript, $job)->decoded ?? [];

        /** @var list<array<string, mixed>> $evidence */
        $evidence = array_values($decoded['evidence'] ?? []);

        // **درسٌ بلا شاهد درسٌ صحيح**، فلا يُعدّ الفراغ إخفاقاً. والمخطّط
        // يفرض وجود المفتاح لا امتلاءه.
        $job->forceFill(['evidence_json' => $evidence])->save();

        app(TransitionJob::class)->handle($job, JobState::Verifying);

        return $evidence;
    }
}
