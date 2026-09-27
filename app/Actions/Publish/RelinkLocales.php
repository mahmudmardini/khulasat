<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Actions\Render\RenderOutput;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Services\Render\PageRenderer;
use App\Support\Publish\PublishedLocales;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * إعادةُ رسمِ الصفحات المنشورة بعد تبدّل لغاتها — T-134، واستُخرج في T-166.
 *
 * ★ **وعلّتُها ترتيبُ النشر نفسُه.** فالأولى تُنشر قبل أن تُرسم الأخريات
 * («الأولى أوّلاً وقطعاً»)، فلا تعرف عند رسمها أيَّ أخواتها سينجح —
 * وشريطُ اللغات يُبنى على ما نُشر فعلاً لا على ما طُلب
 * ({@see PublishedLocales}). فتخرج الأولى بلا شريط، والثانيةُ برابطٍ
 * واحد، والثالثةُ باثنين — **وكلٌّ تجهل من بعدها**.
 *
 * فتُعاد الصفحاتُ كلُّها مرّةً واحدة بعد أن يستقرّ المنشور. **ولغةٌ أُضيفت
 * بعد النشر (T-166) كذلك**: صفحاتُ أخواتها لا تعرفها حتى تُعاد.
 *
 * **ولا نداءَ نموذجٍ فيها**: `RenderOutput` ترسم من `body_json`
 * و`summary_translations` المحفوظتين. وكلفةُ إعادة الرسم صفر — §8-أ.
 *
 * **وتُتجاوَز إن كانت لغةً واحدة**: لا شريطَ لها، فلا شيء يتبدّل.
 * وإخفاقُها لا يُسقط شيئاً: الصفحاتُ منشورةٌ تُخدَم، وغايةُ هذه
 * إضافةُ روابطَ بينها.
 */
final class RelinkLocales
{
    public function __construct(
        private readonly RenderOutput $render,
        private readonly PublishSummary $publish,
        private readonly PageRenderer $page,
    ) {}

    public function handle(SummaryJob $job): void
    {
        $published = $job->outputs()
            ->where('type', OutputType::Page->value)
            ->whereNotNull('storage_path')
            ->get()
            ->map(static fn (Output $output): Locale => $output->locale)
            ->all();

        if (count($published) < 2) {
            return;
        }

        foreach ($published as $locale) {
            try {
                $output = $this->render->handle($job, $this->page, $locale);

                $this->publish->handle($job, [
                    $output->type->value.':'.$locale->value => $output->contents,
                ]);
            } catch (Throwable $failure) {
                Log::warning('locale_relink_failed', [
                    'summary_job_id' => $job->id,
                    'locale' => $locale->value,
                    'reason' => $failure->getMessage(),
                ]);
            }
        }
    }
}
