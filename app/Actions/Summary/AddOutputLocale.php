<?php

declare(strict_types=1);

namespace App\Actions\Summary;

use App\Enums\Locale;
use App\Exceptions\LocaleNotAddable;
use App\Jobs\TranslateAddedLocale;
use App\Models\SummaryJob;
use App\Models\SummaryTranslation;
use App\Support\Publish\LocaleAdditions;
use Illuminate\Support\Facades\DB;

/**
 * يضيف لغةً إلى ملخّصٍ قائم — T-166، طلبُ مالك المنتج.
 *
 * ★★ **ولا يُعاد من الخطّ شيء.** فالترجمةُ مرحلةٌ بعد الخطّ تقرأ `body_json`
 * المحفوظ (T-38)، فإضافةُ لغةٍ **نداءُ ترجمةٍ واحد** — لا تفريغٌ ولا
 * استخراجٌ ولا تحقّقٌ ولا كتابة. وكان البديلُ ملخّصاً جديداً بمراحله الستّ،
 * يُدفع ثمنُه ثانيةً لعملٍ في الجدول.
 *
 * **ولا يُخصم من الحصّة** بقرار مالك المنتج (٢٧ أيلول ٢٠٢٦): الملخّصُ
 * واحد. وسقفُ الإنفاق وتعليقُ الاشتراك يُفحصان قبله في `quota:extra`
 * (CLAUDE.md §2 القاعدة الخامسة).
 */
final class AddOutputLocale
{
    /** @throws LocaleNotAddable */
    public function handle(SummaryJob $job, Locale $locale): void
    {
        $this->guard($job, $locale);

        DB::transaction(function () use ($job, $locale): void {
            $lecture = $job->lecture;

            /*
             * **تُلحق بلغات المحاضرة لا بلغات الجهة.** فمحاضرةٌ بلا اختيارٍ
             * تتبع افتراضَ الجهة، فيُثبَّت افتراضُها فيها مع الجديدة —
             * وإلّا أضافت محاضرةٌ واحدةٌ لغةً لكلّ ملخّصات الجهة.
             */
            $current = array_map(static fn (Locale $l): string => $l->value, $lecture->outputLocales());

            $lecture->forceFill(['locales' => Locale::normalizeSet([...$current, $locale->value])])->save();

            SummaryTranslation::query()->updateOrCreate(
                ['summary_job_id' => $job->id, 'locale' => $locale->value],
                ['tenant_id' => $job->tenant_id, 'requested_at' => now(), 'failed_at' => null],
            );
        });

        // بعد الالتزام: عاملٌ يسبق الالتزامَ يجد اللغةَ غيرَ مختارة.
        TranslateAddedLocale::dispatch((int) $job->id, $locale->value)->afterCommit();
    }

    private function guard(SummaryJob $job, Locale $locale): void
    {
        /*
         * **ما لا يُرسم لا يُترجَم.** ومتنٌ فيه شاهدٌ معلّق لا يُنشر (القاعدة
         * الرابعة)، فترجمتُه مالٌ يُصرف على ما قد يتبدّل بعد المراجعة.
         */
        if ($job->body_json === null || $job->body_html === null || $job->pendingEvidenceCount() > 0) {
            throw LocaleNotAddable::because('not_ready');
        }

        if ($job->lecture === null) {
            throw LocaleNotAddable::because('not_ready');
        }

        $state = LocaleAdditions::stateOf($job, $locale);

        if ($state === LocaleAdditions::TRANSLATING) {
            throw LocaleNotAddable::because('translating');
        }

        if ($state !== LocaleAdditions::AVAILABLE && $state !== LocaleAdditions::FAILED) {
            throw LocaleNotAddable::because('unavailable');
        }
    }
}
