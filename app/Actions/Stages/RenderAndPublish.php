<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Actions\Publish\PublishSummary;
use App\Actions\Publish\RelinkLocales;
use App\Actions\Quiz\GenerateQuiz;
use App\Actions\Render\RenderCarousel;
use App\Actions\Render\RenderOutput;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\SummaryJob;
use App\Services\Render\PageRenderer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مرحلة `rendering`: ترسم الصفحة وترفعها ثمّ تنتقل إلى `published` — §8 و§9.
 *
 * الرسمُ والنشر فعلان قائمان (T-14 وT-15)، وهذه تصلهما بالخطّ في خطوة
 * واحدة كسائر المراحل، فيبقى المشغّل موزّعاً على الحالات لا حاملاً لمنطق.
 *
 * **والصفحة أوّلاً وقطعاً.** والكاروسيل يُبنى بعدها إن طلبته الجهة، ولا
 * يُنشر (T-204)، وحزمةُ الصور تُنشأ من شاشتها — §8-أ.
 */
final class RenderAndPublish
{
    /** بصمةُ البنية في `output_meta_json` — T-167. والعارضُ يقرأ مفاتيحَه بأسمائها فلا يراها. */
    private const META_HASH = 'source_hash';

    public function __construct(
        private readonly RenderOutput $render,
        private readonly PublishSummary $publish,
        private readonly PageRenderer $page,
        private readonly RenderCarousel $carousel,
        private readonly TranslateSummary $translate,
        private readonly BuildOutputMeta $meta,
        private readonly RelinkLocales $relink,
        private readonly GenerateQuiz $quiz,
    ) {}

    /**
     * @return array<string, string> الروابط العامّة بمفتاح نوع المخرَج.
     */
    public function handle(SummaryJob $job): array
    {
        // **بيانات الصفحة قبل رسمها** — وسومُ الوصف والمشاركة جزءٌ من
        // الصفحة، فبناؤها بعد الرسم يعني رسماً بلا وسوم. **وقبل الترجمة
        // كذلك**: الوصفُ يُشتقّ من البنية العربية، فلا يتبدّل بتبدّل اللغة.
        $this->buildMeta($job);

        $locales = $this->locales($job);

        // **الأولى أوّلاً وقطعاً**: هي التي تُنشر على المسار الجذر، وبها
        // تنتقل المهمّةُ إلى `published`. فلو سقطت لم يكن ثَمّ ما يُنشر.
        $primary = Locale::primaryOf($locales);

        if (! $primary->isSource()) {
            $this->translate->handle($job, $primary);
        }

        // **الاختبارُ قبل الصفحة** — T-195: زرُّه جزءٌ منها، فبناؤه بعد الرسم
        // يعني صفحةً بلا زرٍّ حتى «حدّث المنشور».
        $this->buildQuiz($job);

        // `RenderOutput` يحرس الشواهد المعلّقة ويقيّد المخرَج في `outputs`.
        $output = $this->render->handle($job, $this->page, $primary);

        /*
         * ★ **ما بعد اللغة الأولى «في الطريق» قبل أن تصير المهمّةُ منشورة** — T-228.
         *
         * فالنشرُ التالي ينقلها إلى `published`، والشاشةُ تفتح المعاينةَ عندها،
         * واللغاتُ التالية والشرائحُ لم تُبنَ بعد. وبلا علامةٍ قبله تُري
         * الإنجليزيةَ «تعذّرت» والشرائحَ «لم تُنشأ»، ولا تتحدّث من نفسها.
         */
        $job->forceFill(['finishing_at' => now()])->save();

        try {
            // و`PublishSummary` هو من ينقل إلى `published` بعد أن يرفع الملفّ.
            $urls = $this->publish->handle($job, [
                $output->type->value.':'.$primary->value => $output->contents,
            ]);

            $urls = [...$urls, ...$this->secondaryLocales($job, $locales, $primary)];

            // شريطُ اللغات يحتاج الجميعَ منشوراً قبل أن يُرسَم — T-134.
            $this->relink->handle($job);

            $this->carousel($job);
        } finally {
            // انتهى أو سقط — فما بقي ناقصاً بعدها ساقطٌ يُعاد، لا منتظَر.
            $job->forceFill(['finishing_at' => null])->save();
        }

        return $urls;
    }

    /**
     * بيانات صفحة الملخّص — المرحلة ٦، T-57.
     *
     * **وكانت مبنيّةً لا تُنادى**: لا فرعَ لها في {@see RunSummaryPipeline}
     * ولا استدعاءَ هنا، فغابت عن `cost_breakdown` في المهامّ كلِّها. وأثرُها
     * أنّ الصفحة تخرج بـ`<title>` وحده — **بلا وصفٍ ولا وسمِ مشاركة** — في
     * منتجٍ غايتُه صفحةٌ تُشارَك.
     *
     * ★ **وسقوطُها لا يُسقط النشر** — نظير الكاروسيل والترجمة. فصفحةٌ بلا
     * وصفٍ خيرٌ من ملخّصٍ تمّ عملُه كلُّه ثمّ وقف على وسمٍ في رأسه. ولذلك
     * `on_exhausted: degrade` في صفّها كذلك.
     *
     * ★★ **ولا نداءَ لبنيةٍ بُنيت بياناتُها** — T-167، طلبُ مالك المنتج.
     *
     * فزرُّ «حدّث المنشور» يمرّ من هنا في كلّ ضغطة، وكان ينادي المرحلةَ
     * السادسة كلَّ مرّة — **نداءٌ مدفوعٌ على فعلٍ تقول واجهتُه إنّه مجّاني**
     * (§8-أ). والبياناتُ تُشتقّ من `structure_json` وحده، وهو لا يُكتب إلّا
     * في الاستخراج؛ فالنداءُ يُعيد حسابَ ما لم يتبدّل. نظيرُ T-141 للترجمة.
     */
    private function buildMeta(SummaryJob $job): void
    {
        $stored = (array) ($job->output_meta_json ?? []);
        $hash = self::structureHash($job);

        if ($stored !== [] && ($stored[self::META_HASH] ?? null) === $hash) {
            return;
        }

        /*
         * **وبياناتٌ بلا بصمة (قبل T-167) تُتبنّى ولا تُعاد.** فالبنيةُ لا
         * تُكتب بعد الاستخراج، والبياناتُ بُنيت منها بعده — فهي بياناتُ
         * هذه البنية نفسِها. وإعادتُها «مرّةً أخيرة» مالٌ على يقين.
         */
        if ($stored !== [] && ! array_key_exists(self::META_HASH, $stored)) {
            $job->forceFill(['output_meta_json' => [...$stored, self::META_HASH => $hash]])->save();

            return;
        }

        try {
            $meta = $this->meta->handle($job);
        } catch (Throwable $failure) {
            Log::warning('output_meta_failed', [
                'summary_job_id' => $job->id,
                'reason' => $failure->getMessage(),
            ]);

            return;
        }

        if ($meta !== []) {
            $job->forceFill(['output_meta_json' => [...$meta, self::META_HASH => $hash]])->save();
        }
    }

    /**
     * بصمةُ البنية التي تُبنى منها البيانات — والمفاتيحُ مرتّبة، فلا يوجب
     * ترتيبُها وحده نداءً.
     */
    private static function structureHash(SummaryJob $job): string
    {
        $structure = (array) ($job->structure_json ?? []);
        ksort($structure);

        return hash('sha256', (string) json_encode($structure, JSON_UNESCAPED_UNICODE));
    }

    /**
     * لغاتُ النشر لهذا الملخّص — اختيارُ المحاضرة، وإلّا فافتراضُ الجهة.
     *
     * @return list<Locale>
     */
    private function locales(SummaryJob $job): array
    {
        return $job->lecture?->outputLocales()
            ?? $job->tenant?->outputLocales()
            ?? [Locale::source()];
    }

    /**
     * الصفحاتُ باللغات التالية للأولى — T-38.
     *
     * ★ **وإخفاقُها لا يُسقط الملخّص** — نظير الكاروسيل تماماً. فاللغةُ
     * الأولى نُشرت وبلغت المهمّةُ `published`، ونداءُ الترجمة قد يخفق أو
     * يُبطئ. ورميُ الاستثناء هنا يُوقف مهمّةً تمّ عملُها كلُّه **من أجل
     * مخرَجٍ ثانٍ**، ويُري الجهةَ ملخّصاً «متوقّفاً» وهو منشورٌ يُخدَم.
     *
     * **والأولى ليست كذلك**: سقوطُها يعني أنّ لا شيء يُنشر، فتسقط المهمّة
     * كما ينبغي.
     *
     * @param  list<Locale>  $locales
     * @return array<string, string>
     */
    private function secondaryLocales(SummaryJob $job, array $locales, Locale $primary): array
    {
        $urls = [];

        foreach ($locales as $locale) {
            if ($locale === $primary) {
                continue;
            }

            try {
                if (! $locale->isSource()) {
                    $this->translate->handle($job, $locale);
                }

                $output = $this->render->handle($job, $this->page, $locale);

                $urls = [...$urls, ...$this->publish->handle($job, [
                    $output->type->value.':'.$locale->value => $output->contents,
                ])];
            } catch (Throwable $e) {
                Log::warning('تعذّرت لغةٌ من لغات النشر، واللغة الأولى منشورة.', [
                    'summary_job_id' => $job->id,
                    'locale' => $locale->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $urls;
    }

    /**
     * اختبارُ الفهم إن طُلب — T-195.
     *
     * ★ **مرّةً واحدة**: اختبارٌ قائمٌ — جاهزاً أو متعذّراً — لا يُبنى ثانيةً
     * هنا. فزرُّ «حدّث المنشور» يمرّ من هذا الطريق في كلّ ضغطة، ونداءٌ مدفوعٌ
     * على فعلٍ تقول واجهتُه إنّه مجّاني خطأُ T-167 نفسُه. وإعادةُ البناء زرٌّ
     * في صفحة الاختبار.
     *
     * ★★ **وإخفاقُه لا يُسقط الملخّص** — {@see GenerateQuiz} يحفظ السبب ويمضي.
     */
    private function buildQuiz(SummaryJob $job): void
    {
        if ($job->lecture?->want_quiz !== true || $job->quiz()->exists()) {
            return;
        }

        try {
            $this->quiz->handle($job);
        } catch (Throwable $failure) {
            Log::warning('quiz.render_failed', [
                'summary_job_id' => $job->id,
                'reason' => $failure->getMessage(),
            ]);
        }
    }

    /**
     * الكاروسيل إن طلبته الجهة — SCREENS.md §3-ب، وT-19.
     *
     * ★ **وإخفاقُه لا يُسقط الملخّص.** فالصفحة نُشرت وبلغت `published`، ونداءُ
     * التكثيف (المرحلة ٧) قد يخفق أو يخالف عقدَه. ورميُ الاستثناء هنا يُوقف
     * مهمّةً تمّ عملُها كلُّه من أجل **مخرَجٍ ثانٍ**، ويُري الجهةَ ملخّصاً
     * «متوقّفاً» وهو منشورٌ يُخدَم.
     *
     * فيُقيَّد الإخفاق ويُمضى، وتُبنى الشرائح بعدها من شاشتها بضغطة.
     */
    private function carousel(SummaryJob $job): void
    {
        /*
         * ★ **ويُبنى مرّةً لا عند كلّ نشر** — T-204.
         *
         * كان يُعاد رسمُه مع كلّ «حدّثْ المنشور» (T-30) ليبقى ملفُّه المنشور
         * حيّاً. **ولا ملفَّ منشوراً له اليوم**، وإعادةُ رسمه تجعل صورَه أقدمَ
         * من شرائحها فتُعرض قديمةً وما تغيّر فيها شيء.
         */
        if ($job->lecture?->want_carousel !== true
            || ! $job->tenant?->allowsRichOutputs()
            || $job->outputs()->where('type', OutputType::Carousel->value)->exists()) {
            return;
        }

        try {
            $this->carousel->handle($job);
        } catch (Throwable $failure) {
            Log::warning('carousel.render_failed', [
                'summary_job_id' => $job->id,
                'reason' => $failure->getMessage(),
            ]);
        }
    }
}
