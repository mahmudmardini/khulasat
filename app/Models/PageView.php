<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\OutputType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مجموع فتحات صفحةٍ منشورة في يوم — SCREENS.md §7، والمهمّة T-31.
 *
 * **ولا `BelongsToTenant` هنا** — كما في {@see Complaint}، وللعلّة نفسها:
 * الصفّ يُكتب من طلبٍ عامّ **بلا جلسة ولا مستخدم**، فسياق الجهة فارغٌ ساعةَ
 * الكتابة. والعزل يُطبَّق في القراءة عبر `summary_job_id`، وهو مقصورٌ على
 * جهته أصلاً بحاجز {@see SummaryJob}.
 */
class PageView extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'output_type' => OutputType::class,
            /*
             * **وتقبل الفراغ** — T-140: صفوفُ ما قبل فصل اللغات مجموعُ
             * اللغات كلِّها، ونسبتُها إلى واحدةٍ تخمينٌ يُعرض رقماً. فتبقى
             * `null` وتُقرأ «غير مبيَّنة».
             */
            'locale' => Locale::class,
            'day' => 'date',
            'views' => 'integer',
        ];
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }
}
