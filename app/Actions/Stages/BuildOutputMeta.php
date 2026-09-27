<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;

/**
 * Stage 6 — the page's metadata — PROMPT-PACK المرحلة ٦.
 *
 * تُشتقّ من مادّةٍ جاهزة لا من التفريغ: «تُعدّ بيانات صفحة الملخّص من مادة
 * جاهزة». وتجري داخل `rendering` فلا حالةَ لها في آلة الحالات (§5)،
 * **ولا تنقل المهمّة** — والعارضات (T-14) هي التي تستهلك مخرَجها.
 */
final class BuildOutputMeta
{
    use RunsAStage;

    /**
     * @return array<string, mixed>
     *
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): array
    {
        $structure = $job->structure_json ?? [];

        if ($structure === []) {
            throw ModelCallFailed::permanent(
                'structure_missing',
                'لا بنية تُشتقّ منها بيانات الإخراج.',
                Stage::OutputMetadata,
            );
        }

        $material = (string) json_encode($structure, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /** @var array<string, mixed> $meta */
        $meta = $this->runStage(Stage::OutputMetadata, $material, $job)->decoded ?? [];

        return $meta;
    }
}
