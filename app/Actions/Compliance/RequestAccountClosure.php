<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Models\Tenant;

/**
 * طلب إغلاق حساب الجهة — T-24.
 *
 * **تصديرٌ إجباري، ثمّ حذفٌ بعد مهلةٍ معلنة، والتراجع متاحٌ خلالها.**
 *
 * والترتيب مقصود: التصدير **قبل** تسجيل الطلب لا بعده. فلو سُجّل الطلب
 * أوّلاً ثمّ أخفق التصدير، لبقي حسابٌ موسومٌ بالحذف وبلا أرشيف. ولا يُغلق
 * حسابٌ بلا نسخةٍ في يد صاحبه.
 */
class RequestAccountClosure
{
    /** المهلة المعلنة قبل الحذف. */
    public const GRACE_DAYS = 30;

    public function __construct(private readonly ExportTenantData $export) {}

    public function handle(Tenant $tenant): Tenant
    {
        $path = $this->export->handle($tenant);

        $tenant->forceFill([
            'closure_export_path' => $path,
            'closure_requested_at' => now(),

            /*
             * **الموعد يُخزَّن محسوباً لا مشتقّاً.** فتغييرُ المهلة في
             * الإعدادات بعد الطلب لا يقدّم حذفاً وُعد به في تاريخٍ بعينه.
             */
            'purge_after' => now()->addDays(self::GRACE_DAYS),

            // والإنتاج يقف فوراً، والمنشور يبقى — كقاعدة التعليق في T-23.
            'status' => 'closing',
        ])->save();

        return $tenant;
    }

    /** التراجع خلال المهلة. */
    public function cancel(Tenant $tenant): Tenant
    {
        $tenant->forceFill([
            'closure_requested_at' => null,
            'purge_after' => null,
            'status' => 'active',
        ])->save();

        return $tenant;
    }
}
