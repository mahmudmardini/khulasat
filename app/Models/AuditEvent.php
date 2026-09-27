<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * صفٌّ في أثر المشرف — T-21.
 *
 * **ولا `BelongsToTenant` عليه.** السجلّ يخصّ المشرف العامّ لا الجهات،
 * وتقييدُه بجهة السياق يُخفي عن المشرف نصفَ ما فعله.
 *
 * @property AuditAction $action
 * @property array<string, array{from: mixed, to: mixed}> $changes
 */
class AuditEvent extends Model
{
    protected $table = 'admin_audit_log';

    /**
     * **لا طوابع تلقائية.** `occurred_at` تُكتب صراحةً، ولا `updated_at`
     * أصلاً — الصفّ يُكتب مرّةً ولا يُعدَّل، وعمودُ تعديلٍ في سجلّ تدقيقٍ
     * وعدٌ بما لا ينبغي أن يقع.
     */
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
