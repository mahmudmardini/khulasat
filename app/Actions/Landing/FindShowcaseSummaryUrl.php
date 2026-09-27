<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Enums\OutputType;
use App\Models\Output;

/**
 * رابطُ المثال المنشور الحقيقيّ في صفحة التعريف العامّة — T-119.
 *
 * صفحة التعريف تُريد إثبات أنّ المنتج يعمل فعلاً، لا نموذجاً وصفيّاً
 * فقط (T-113). فتربط زائرها بخلاصةٍ منشورةٍ حقيقية — لا عبر شاشة الإدارة
 * الداخلية (`/panel/jobs/{id}/preview/page`، تتطلّب دخولاً ولا تُنشر)،
 * بل عبر **مسار النشر العلنيّ نفسه** الذي يُخدَم منه القارئ العاديّ.
 *
 * **ولماذا `slug` لا `id`:** معرّف المهمّة رقمٌ يتغيّر بين قاعدة بيانات
 * وأخرى (تطوير، اختبار، إنتاج)، والمُعرِّفان الثابتان جهةً وملخّصاً هما
 * ما يبقى. راجع `config('khulasah.landing.showcase')`.
 *
 * **وغيابُ المثال لا يكسر الصفحة**: بيئةٌ جديدة بلا هذا الصفّ بعينه
 * تحصل على `null`، فتُخفي الصفحةُ الرابطَ بهدوء — لا رابطاً مكسوراً.
 */
final class FindShowcaseSummaryUrl
{
    public function __construct(
        private readonly FindShowcaseSummaryJob $findJob = new FindShowcaseSummaryJob,
    ) {}

    public function handle(): ?string
    {
        $job = $this->findJob->handle();

        if ($job === null) {
            return null;
        }

        // أقصرُ رابطٍ هو رابط الجذر بلا مقطع لغة — أيّاً كانت اللغة
        // الأولى (Paths::segment) — فلا حاجة لمعرفة اللغة الأساسية هنا.
        return Output::query()
            ->where('summary_job_id', $job->id)
            ->where('type', OutputType::Page)
            ->whereNotNull('public_url')
            ->get()
            ->sortBy(fn (Output $output): int => mb_strlen((string) $output->public_url))
            ->first()
            ?->public_url;
    }
}
