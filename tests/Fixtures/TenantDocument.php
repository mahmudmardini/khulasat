<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for any tenant-scoped record.
 *
 * الجداول التشغيلية (lectures, summary_jobs …) من عمل T-07، والحاجز يجب أن
 * يُختبر الآن لا بعدها. فيُختبر على نموذج بأقلّ ما يمكن، حتى يقيس الاختبار
 * الحاجز نفسه لا تفاصيل جدول بعينه.
 */
class TenantDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_documents';

    protected $guarded = [];
}
