<?php

declare(strict_types=1);

namespace App\Actions\Render;

use App\Contracts\Renderer;
use App\Enums\Locale;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Support\Render\BrandKit;
use App\Support\Render\ContentObject;
use App\Support\Render\RenderedOutput;
use RuntimeException;

/**
 * يرسم مخرَجاً ويقيّده في `outputs` — المواصفة §8-أ.
 *
 * **وإعادة العرض لا تُعيد تشغيل الخطّ ولا تُحتسب من حصّة إعادة التوليد**
 * (§8-أ). فالمحتوى المتحقَّق منه غالٍ ويُنتَج مرّة، والرسم منه رخيص.
 */
class RenderOutput
{
    /**
     * @param  Locale  $locale  لغةُ المخرَج — T-38، والافتراض لغةُ المصدر.
     */
    public function handle(SummaryJob $job, Renderer $renderer, Locale $locale = Locale::Ar): RenderedOutput
    {
        /*
         * **لا يُرسَم ما لم يُحسم.** الحدّ الرابع: `needs_review` تمنع النشر،
         * ولا إعداد يتجاوزها. ورسمُ صفحةٍ من شواهد غير محسومة يُنتج ملفّاً
         * جاهزاً للنشر، وهو أخطر من عدم رسمه.
         */
        $pending = $job->pendingEvidenceCount();

        if ($pending > 0) {
            throw new RuntimeException("لا يُرسَم مخرَج و{$pending} شاهداً لم يُحسم بعد.");
        }

        $tenant = $job->tenant;

        if ($tenant === null) {
            throw new RuntimeException('لا جهة لهذه المهمّة، ولا هوية تُرسم بها.');
        }

        $content = ContentObject::fromJob($job, $locale);

        /*
         * ★ **ولا يُقيَّد مخرَجٌ بلغةٍ غير التي طُلبت** — T-63.
         *
         * فـ{@see ContentObject::fromJob()} يسقط إلى الأصل حين لا ترجمة،
         * **وذلك في محلِّه عند العرض** — صفحةٌ بالأصل خيرٌ من فارغة. أمّا
         * هنا فالمقصود إنتاجُ مخرَجٍ **لهذه اللغة بعينها**، وتسجيلُ العربيّ
         * مكانها يُنتج صفّاً بلغةٍ لا يجدها الناشرُ فيرمي، **فيُلتقط
         * الاستثناء ويُسجَّل وتبدو المهمّةُ ناجحةً وقد ضاعت لغة**.
         *
         * فيُعلَن الإخفاقُ هنا صريحاً: يلتقطه `secondaryLocales` ويُسجّله،
         * وتبقى اللغةُ الأولى منشورةً كما كانت.
         */
        if ($content->locale !== $locale) {
            throw new RuntimeException(
                "لا ترجمة بـ«{$locale->value}» لهذه المهمّة، فلا يُرسم مخرَجٌ بها."
            );
        }

        $output = $renderer->render($content, BrandKit::forTenant($tenant, $job->lecture));

        Output::query()->updateOrCreate(
            // **اللغة جزءٌ من المفتاح** — T-38. وبدونها تدهس الترجمةُ الأصلَ:
            // القيدُ القديم `(job, type)` كان يجعل صفحتين بلغتين مستحيلتين.
            ['summary_job_id' => $job->id, 'type' => $output->type->value, 'locale' => $locale->value],
            [
                'tenant_id' => $job->tenant_id,
                'format' => $output->format->value,
                'meta' => [...$output->meta, 'bytes' => $output->bytes(), 'locale' => $locale->value],
                'rendered_at' => now(),
                'renderer_version' => $output->rendererVersion,
            ],
        );

        return $output;
    }
}
