<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\JobState;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Models\SummaryJob;
use App\Services\Transcript\TranscriptResolver;
use App\Support\Transcript\TranscriptRequest;

/**
 * مرحلة `transcribing`: تُحضر نصّ الدرس — المواصفة §5-أ.
 *
 * المصادر وترتيبها في {@see TranscriptResolver} (T-08 وT-09)، **وهذه هي
 * التي تصله بالخطّ**: كان المحلّ مبنيّاً ومختبَراً ولا يناديه شيء، فتبقى
 * المهمّة في `queued` إلى الأبد.
 *
 * والنصّ الملصوق يُقدَّم مصدراً: من لصق تفريغه لا يُنتظر منه رابط، ولا
 * يُصرف على تفريغه شيء.
 */
final class ResolveTranscript
{
    public function __construct(
        private readonly TranscriptResolver $resolver,
        private readonly TransitionJob $transition,
    ) {}

    /**
     * @throws TranscriptFailed
     */
    public function handle(SummaryJob $job): string
    {
        $lecture = $job->lecture;

        if ($lecture === null) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::NoArabicSource,
                'لا محاضرة لهذه المهمّة، ولا مصدر يُفرَّغ منه.',
            );
        }

        $request = TranscriptRequest::for($lecture);

        $pasted = trim((string) $job->transcript_text);

        if ($pasted !== '') {
            $request = $request->withPastedText($pasted);
        }

        $result = $this->resolver->resolve($request);

        /*
         * **يُوقَف قبل صرف توكن واحد** — §5-أ-7: نصٌّ دون خمسمئة كلمة مؤشّرُ
         * ترجمةٍ ناقصة، ورفعُه إلى النماذج يشتري ملخّصاً من نصفِ درس.
         */
        if ($result->isTooShort()) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptTooShort,
                "التفريغ {$result->wordCount()} كلمة، وهو أقصر من أن يُلخَّص.",
            );
        }

        $job->forceFill([
            'transcript_text' => $result->text,
            'transcript_source' => $result->source,
            'transcript_word_count' => $result->wordCount(),
        ])->save();

        $this->transition->handle($job, JobState::Cleaning);

        return $result->text;
    }
}
