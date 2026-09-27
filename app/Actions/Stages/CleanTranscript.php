<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;

/**
 * Stage 1 — cleaning the raw transcript — PROMPT-PACK المرحلة ١.
 *
 * **يكتب فوق `transcript_text`**، والأصلُ الخام محفوظ في التخزين للتتبّع
 * (§5-أ-3). ومهمّتها إخراجُ النصّ نفسه منظَّفاً: «لا تلخّص، ولا تشرح، ولا
 * تحذف معنى، ولا تضف كلمة من عندك».
 *
 * وأهمّ قيدٍ فيها أنّها **لا تصحّح لفظ آية أو حديث** — التصحيح وظيفة طبقة
 * التحقّق، ونموذجٌ يُصحّح من حفظه يُنتج شاهداً لم يقله المتحدّث.
 */
final class CleanTranscript
{
    use RunsAStage;

    /**
     * @throws ModelCallFailed
     */
    public function handle(SummaryJob $job): string
    {
        $raw = (string) $job->transcript_text;

        if (trim($raw) === '') {
            throw ModelCallFailed::permanent(
                'transcript_missing',
                'لا نصّ تفريغ لتنظيفه.',
                Stage::Cleaning,
            );
        }

        $cleaned = trim($this->runStage(Stage::Cleaning, $raw, $job)->content);

        $job->forceFill([
            'transcript_text' => $cleaned,
            'transcript_word_count' => self::wordCount($cleaned),
        ])->save();

        // الكلفة قُيّدت في البوّابة، فتُمرَّر صفراً — وإلّا ضوعفت الفاتورة.
        app(TransitionJob::class)->handle($job, JobState::ExtractingStructure);

        return $cleaned;
    }

    private static function wordCount(string $text): int
    {
        return count(array_filter(
            preg_split('/\s+/u', trim($text)) ?: [],
            static fn (string $word): bool => $word !== '',
        ));
    }
}
