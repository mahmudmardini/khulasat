<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Publish\GenerateShareCard;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generates the share cards of every published language of a summary — T-144.
 *
 * **وفريدٌ لكلّ مهمّة**: `PublishSummary` يُنادى مرّةً لكلّ لغة ثمّ مرّةً
 * لربطها (T-134)، فبلا تفرّدٍ يُشعَل المتصفّحُ أربعَ مرّاتٍ للبطاقات نفسها.
 * والمهمّةُ تجري بعد آخرِها فتجد اللغاتِ كلَّها منشورة.
 */
final class GenerateShareCards implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 120;

    public function __construct(public SummaryJob $summaryJob) {}

    public function uniqueId(): string
    {
        return (string) $this->summaryJob->id;
    }

    public function handle(GenerateShareCard $generate): void
    {
        $job = $this->summaryJob->fresh();

        if ($job === null) {
            return;
        }

        $locales = Output::query()
            ->where('summary_job_id', $job->id)
            ->where('type', OutputType::Page->value)
            ->whereNotNull('public_url')
            ->get()
            ->map(static fn (Output $output) => $output->locale)
            ->unique()
            ->all();

        foreach ($locales as $locale) {
            $generate->handle($job, $locale);
        }
    }
}
