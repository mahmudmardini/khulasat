<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;

/**
 * Stage 2 — the summary's skeleton — PROMPT-PACK المرحلة ٢، والمواصفة §6-2.
 *
 * الفئة العليا، و`on_exhausted = fail`: البنية هيكلُ الملخّص كلّه، **ولا
 * تحتمل جودة أدنى** (§4). ومخرَجُها في `structure_json`.
 */
final class ExtractStructure
{
    use RunsAStage;

    /**
     * @return array<string, mixed>
     *
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): array
    {
        $transcript = (string) $job->transcript_text;

        if (trim($transcript) === '') {
            throw ModelCallFailed::permanent('transcript_missing', 'لا نصّ لاستخراج بنيته.', Stage::ExtractingStructure);
        }

        /** @var array<string, mixed> $structure */
        $structure = $this->runStage(Stage::ExtractingStructure, $transcript, $job)->decoded ?? [];

        $job->forceFill(['structure_json' => $structure])->save();

        app(TransitionJob::class)->handle($job, JobState::ExtractingEvidence);

        return $structure;
    }
}
