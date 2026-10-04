<?php

declare(strict_types=1);

namespace App\Actions\Analytics;

use App\Enums\Locale;
use App\Enums\OutputType;
use App\Models\SummaryJob;
use Illuminate\Support\Facades\DB;

/**
 * يزيد عدّاد يومٍ واحد — SCREENS.md §7، والمهمّة T-31.
 *
 * ★ **الزيادة في جملة واحدة، لا قراءةً ثمّ كتابة.**
 *
 * فـ`$row->increment()` تقرأ ثمّ تكتب، وطلبان متزامنان يقرآن العدد نفسه
 * فيكتبان الزيادة نفسها — **فتضيع فتحةٌ في كلّ تزامن**. وصفحةٌ تُفتح ألفاً
 * في دقيقة هي بالضبط الحالة التي يُراد العدّ فيها. و`on conflict … views + 1`
 * تجري في المحرّك، فلا سباق أصلاً.
 */
final class RecordPageView
{
    /**
     * @param  Locale|null  $locale  لسانُ الصفحة المقروءة — و`null` من
     *                               ملفٍّ منشورٍ قبل T-140، فيقع في «غير
     *                               مبيَّنة» ولا يُنسَب إلى لسانٍ مخمَّن.
     */
    public function handle(SummaryJob $job, OutputType $type, ?Locale $locale = null): void
    {
        /*
         * **ولا يُعدّ إلّا المنشور.** فالمسار عامٌّ بلا مصادقة، ومفتاحُه رقم
         * المهمّة — ومن أراد نفخ عدّادٍ استطاع. وحصرُه في المنشور يُبقي
         * النفخ على صفحةٍ يراها الناس أصلاً، ويمنع أن يُصنع تاريخُ زياراتٍ
         * لملخّصٍ لم يُنشر قطّ.
         */
        if ($job->published_at === null || $job->unpublished_at !== null) {
            return;
        }

        // **ولا يُعدّ ما لا يُنشر** — T-204: الشرائح لم تعد صفحةً، وشاهدتُها
        // في نسخةٍ قديمةٍ محفوظة لا تُنشئ زياراتٍ لما لا يُزار.
        if (! $type->isPublished()) {
            return;
        }

        /*
         * ★ **و`coalesce` في الطرفين — T-140.** فالمفتاحُ الفريد مبنيٌّ
         * عليها، و`on conflict` تستدلّ على الفهرس بمطابقة تعبيره حرفاً.
         * وعلّتُها أنّ Postgres يعدّ `null` مميَّزاً عن `null`، فبلا هذا
         * تصير كلُّ فتحةٍ من ملفٍّ منشورٍ قبل T-140 **صفّاً جديداً**.
         */
        DB::statement(
            <<<'SQL'
                insert into page_views (tenant_id, summary_job_id, output_type, locale, day, views)
                values (?, ?, ?, ?, ?, 1)
                on conflict (summary_job_id, output_type, (coalesce(locale, '')), day)
                do update set views = page_views.views + 1
                SQL,
            [$job->tenant_id, $job->id, $type->value, $locale?->value, now()->toDateString()],
        );
    }
}
