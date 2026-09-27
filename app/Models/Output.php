<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\OutputFormat;
use App\Enums\OutputType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مخرَجٌ واحد رسمه عارض — المواصفة §4 و§8-أ.
 *
 * **والمحتوى لا يُخزَّن هنا**: الصفحة تُرفع إلى التخزين وتُخدَم من CDN (§9)،
 * وهذا الصفّ يحمل مسارها ونسختها ومتى رُسمت. فقاعدةُ البيانات تحمل الحالة،
 * لا الملفّات.
 */
class Output extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => OutputType::class,
            'locale' => Locale::class,
            'format' => OutputFormat::class,
            'meta' => 'array',
            'rendered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }
}
