<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InviteRequestKind;
use Illuminate\Database\Eloquent\Model;

/**
 * طلبُ دعوةٍ من صفحة التعريف — T-113.
 *
 * **ولا `Scopes\BelongsToTenant` عليه**: صاحبُه لا جهة له بعد، فحاجزُ
 * المستأجرين لا موضع له هنا. ويُقرأ من لوحة المشرف وحدها.
 */
class InviteRequest extends Model
{
    protected $fillable = ['kind', 'name', 'role', 'contact', 'link', 'message', 'ip'];

    protected function casts(): array
    {
        return ['kind' => InviteRequestKind::class, 'handled_at' => 'datetime'];
    }
}
