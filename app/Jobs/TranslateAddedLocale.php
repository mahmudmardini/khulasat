<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\RelinkLocales;
use App\Actions\Render\RenderOutput;
use App\Actions\Stages\TranslateSummary;
use App\Actions\Summary\AddOutputLocale;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Services\Render\PageRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Translates one language added to an existing summary, and publishes it — T-166.
 *
 * يضعه {@see AddOutputLocale}. **ولا يمرّ بالخطّ ولا بآلة الحالات**: الملخّصُ
 * منتهٍ، وهذه تزيده لغةً — فلا حالةَ تنتقل ولا مرحلةَ تُعاد.
 *
 * **وفريدٌ لكلّ ملخّصٍ ولغة**: ضغطتان متتاليتان لا تُنتجان نداءين مدفوعين.
 */
final class TranslateAddedLocale implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** مرّتان: نداءُ النموذج قد يسقط سقوطاً عابراً، والثالثةُ مالٌ على عطبٍ قائم. */
    public int $tries = 2;

    public int $backoff = 30;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $summaryJobId,
        public readonly string $locale,
    ) {}

    public function uniqueId(): string
    {
        return $this->summaryJobId.':'.$this->locale;
    }

    public function handle(
        TranslateSummary $translate,
        RenderOutput $render,
        PageRenderer $page,
        PublishSummary $publish,
        RelinkLocales $relink,
    ): void {
        $job = SummaryJob::query()->withoutGlobalScopes()->find($this->summaryJobId);
        $locale = Locale::tryFrom($this->locale);

        if ($job === null || $locale === null) {
            return;
        }

        // البصمةُ تُغني عن النداء إن كانت الترجمةُ محفوظةً لمتنٍ لم يتبدّل (T-141).
        $translate->handle($job, $locale);

        $this->row($job)?->forceFill(['requested_at' => null, 'failed_at' => null])->save();

        /*
         * ★ **والمنشورُ تُنشر لغتُه الجديدة** — قرارُ مالك المنتج، ٢٧ أيلول.
         * وما لم يُنشر، أو أُزيل نشرُه، تبقى لغتُه للمعاينة: نشرُها هنا
         * يُعيد صفحةً أزالها صاحبُها بيده.
         *
         * **وإخفاقُ النشر لا يُسقط الترجمة**: دُفع ثمنُها وحُفظت، وزرُّ
         * «حدّث المنشور» ينشرها بلا نداء.
         */
        if ($job->state !== JobState::Published || $job->unpublished_at !== null) {
            return;
        }

        try {
            $output = $render->handle($job, $page, $locale);

            $publish->handle($job, [$output->type->value.':'.$locale->value => $output->contents]);

            // أخواتُها المنشورة لا تعرفها في شريط اللغات حتى تُعاد — T-134.
            $relink->handle($job);
        } catch (Throwable $failure) {
            Log::warning('added_locale_publish_failed', [
                'summary_job_id' => $job->id,
                'locale' => $locale->value,
                'reason' => $failure->getMessage(),
            ]);
        }
    }

    /** بعد آخر محاولة — فتُري الشاشةُ «تعذّرت» وزرَّ الإعادة، لا «جارٍ» أبداً. */
    public function failed(?Throwable $failure): void
    {
        $job = SummaryJob::query()->withoutGlobalScopes()->find($this->summaryJobId);

        if ($job !== null) {
            $this->row($job)?->forceFill(['requested_at' => null, 'failed_at' => now()])->save();
        }

        Log::warning('added_locale_translation_failed', [
            'summary_job_id' => $this->summaryJobId,
            'locale' => $this->locale,
            'reason' => $failure?->getMessage(),
        ]);
    }

    private function row(SummaryJob $job): ?SummaryTranslation
    {
        return SummaryTranslation::query()
            ->withoutGlobalScopes()
            ->where('summary_job_id', $job->id)
            ->where('locale', $this->locale)
            ->first();
    }
}
