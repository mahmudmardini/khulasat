<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Publish\PublishSummary;
use App\Actions\Render\RenderOutput;
use App\Actions\Stages\RenderAndPublish;
use App\Domain\Summary\JobState;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Services\Render\PageRenderer;
use Illuminate\Console\Command;
use Throwable;

/**
 * إعادةُ رسم الصفحات المنشورة ورفعِها — T-116.
 *
 * **والصفحةُ المنشورة ملفٌّ مرفوع لا استعلام**: إصلاحُ المستخرِج وإعادةُ
 * اشتقاق الشواهد تُصلح ما في قاعدتنا، ويبقى ما رفعناه إلى التخزين على حاله
 * حتى يُرسم ثانيةً. **والأثرُ يمتدّ إلى كلّ شيء — قرارُ مالك المنتج، ١٢
 * أيلول ٢٠٢٦.**
 *
 * ★ **ولا نموذجَ يُنادى هنا ولا فلسٌ يُصرف.** فهي ترسم من `structure_json`
 * والشواهدِ والترجماتِ المحفوظة، ولا تمرّ بـ{@see RenderAndPublish}
 * الذي يترجم ويبني بيانات الصفحة ويكثّف الشرائح — وكلُّها مراحلُ نماذج.
 *
 * **والصفحةُ وحدها تُعاد**: الكاروسيل مخرَجُ مرحلةٍ سابعة تصرف مالاً، ولا
 * شأن لبلاغ هذه المهمّة به.
 *
 * **والرابطُ لا يتبدّل**: `slug` يثبت، والرفعُ يكتب فوق الملفّ نفسه — فما
 * شاركه الناس يبقى يعمل ويُخدَم منه المصحَّح.
 */
final class RefreshPages extends Command
{
    protected $signature = 'khulasah:refresh-pages
        {--job=* : أرقامُ مهامّ بعينها، وإلّا فالمنشورةُ كلُّها}
        {--dry-run : يُحصي ولا يرفع}';

    protected $description = 'إعادةُ رسم صفحات الملخّصات المنشورة ورفعِها بلا نداء نموذج';

    public function handle(RenderOutput $render, PageRenderer $page, PublishSummary $publish): int
    {
        $jobs = SummaryJob::query()
            ->where('state', JobState::Published->value)
            ->when($this->option('job') !== [], fn ($query) => $query->whereIn('id', $this->option('job')))
            ->orderBy('id')
            ->get();

        if ($jobs->isEmpty()) {
            $this->warn('لا مهمّةَ منشورةً تُعاد.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $done = 0;
        $failed = 0;

        foreach ($jobs as $job) {
            $locales = $job->outputs()
                ->where('type', OutputType::Page->value)
                ->get()
                ->map(static fn (Output $output) => $output->locale)
                ->all();

            if ($locales === []) {
                continue;
            }

            $names = implode('، ', array_map(static fn ($locale) => $locale->value, $locales));

            if ($dry) {
                $this->line("المهمّة {$job->id}: {$names}");
                $done++;

                continue;
            }

            try {
                $rendered = [];

                foreach ($locales as $locale) {
                    $output = $render->handle($job, $page, $locale);
                    $rendered[OutputType::Page->value.':'.$locale->value] = $output->contents;
                }

                $urls = $publish->handle($job, $rendered);

                $this->info("المهمّة {$job->id}: {$names} — ".implode(' · ', $urls));
                $done++;
            } catch (Throwable $failure) {
                // **وسقوطُ واحدةٍ لا يُوقف البقيّة**: كلُّ مهمّةٍ قائمةٌ بنفسها.
                $this->error("المهمّة {$job->id}: {$failure->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line($dry ? "ستُعاد $done مهمّة." : "أُعيدت $done مهمّة، وأخفقت $failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
