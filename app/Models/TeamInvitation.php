<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Team\AcceptInvitation;
use App\Enums\Role;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دعوةٌ إلى فريق جهة — SCREENS.md §10، والمهمّة T-33.
 *
 * **وتحمل `BelongsToTenant`**: تُنشأ وتُقرأ من داخل جلسة المالك، فالسياق
 * قائم. **وقبولُها وحده يجري بلا جلسة**، ويُقرأ فيه الصفُّ بـ`acrossTenants`
 * صراحةً — انظر {@see AcceptInvitation}.
 */
class TeamInvitation extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** أما زالت تنتظر؟ */
    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }
}
