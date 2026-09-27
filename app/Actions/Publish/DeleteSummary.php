<?php

declare(strict_types=1);

namespace App\Actions\Publish;

use App\Models\Lecture;
use App\Models\SummaryJob;
use App\Support\Publish\ShareCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * الحذف النهائي — SCREENS.md §7، والمواصفة §9.
 *
 * ★ **والشاهدة تُكتب قبل الحذف لا بعده.** فالمواصفة §9 تقول «الحذف: إزالة
 * من التخزين + صفحة 410 محفوظة، **لا 404**». ولو حُذف الصفّ أوّلاً لضاع
 * `slug` الجهة والملخّص، **ولا يُعرف بعده أين تُكتب الشاهدة** — فيردّ
 * الرابطُ الذي شاركه الناس 404، أي «لم يكن هنا شيء»، وهي كذبةٌ على قارئٍ
 * يعلم أنّه رآه بعينه.
 *
 * **والفرق عن إلغاء النشر**: ذاك يُبقي المتن والشواهد عندنا فيُعاد النشر
 * بضغطة، وهذا يمحوها. والشاهدة واحدة في الحالين.
 *
 * ولا يُمَسّ `usage_ledger`: مفتاحُه `nullOnDelete`، **فالحصّة المصروفة
 * تبقى مصروفة**. ومن حذف ملخّصاً لا يسترجع حصّته، وإلّا صار الحذف باباً
 * إلى توليدٍ بلا حدّ.
 */
final class DeleteSummary
{
    public function __construct(private readonly UnpublishSummary $unpublish) {}

    public function handle(SummaryJob $job): void
    {
        // ترفع الملفّات وتكتب الشاهدة مكان الصفحة، والصفُّ ما زال قائماً.
        $this->unpublish->handle($job);

        // بطاقاتُ المشاركة — T-144. تُخدم ما دامت الخلاصةُ منشورة، فالرابطُ
        // يُجيب بـ٤٠٤ بعد الحذف؛ والملفُّ نفسُه لا يبقى على القرص بلا صاحب.
        Storage::disk((string) config('khulasah.share_card.disk'))
            ->deleteDirectory(ShareCard::directory((int) $job->id));

        $lectureId = (int) $job->lecture_id;

        DB::transaction(function () use ($job, $lectureId): void {
            // الشواهد والانتقالات والمخرجات تسقط بـ`cascadeOnDelete`.
            $job->delete();

            /*
             * **والدرس يُحذف إن لم يبقَ له ملخّص.** فـ«أعد المحاولة» تُنشئ
             * مهمّةً ثانيةً للدرس نفسه (T-16)، وحذفُ الدرس مع أولاهما يمحو
             * الثانيةَ القائمة معه.
             */
            $orphan = ! SummaryJob::query()->where('lecture_id', $lectureId)->exists();

            if ($orphan) {
                Lecture::query()->whereKey($lectureId)->delete();
            }
        });
    }
}
