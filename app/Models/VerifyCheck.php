<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerifyCheckStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * طلبٌ في أداة «تحقّق» — T-181.
 *
 * **بلا جهة**، فلا `BelongsToTenant`: الأداة عامّة، ومن يلصق نصّاً لا حساب
 * له. ورابطُ تقريره معرّفٌ عشوائيّ يُشارَك، ويسقط بسقوط النصّ بعد سبعة أيام.
 *
 * @property string $id
 * @property VerifyCheckStatus $status
 * @property string|null $text
 * @property int $char_count
 * @property array<string, mixed>|null $report
 * @property int|null $evidence_count
 * @property string|null $error_code
 * @property string $cost_usd
 * @property string $ip_hash
 * @property Carbon $created_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $purged_at
 */
class VerifyCheck extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => VerifyCheckStatus::class,
            'report' => 'array',
            'cost_usd' => 'decimal:4',
            'completed_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    /** @return HasMany<ModelCall, $this> */
    public function modelCalls(): HasMany
    {
        return $this->hasMany(ModelCall::class);
    }

    /** نصٌّ وتقريرٌ حُذفا بانقضاء مدّتهما — فالرابط يقول ذلك ولا يقول «غير موجود». */
    public function isPurged(): bool
    {
        return $this->purged_at !== null;
    }

    /** بصمةُ العنوان — يُحدّ بها المعدّل ولا يُحفظ العنوان نفسه. */
    public static function fingerprint(string $ip): string
    {
        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
