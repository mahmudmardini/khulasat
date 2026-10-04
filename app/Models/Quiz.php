<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * اختبارُ فهمٍ لملخّصٍ واحد، برابطٍ يُشارَك — T-195.
 *
 * **والمحاولاتُ تُقفل بنيتَه**: بعد أوّل محاولةٍ لا يُحذف سؤالٌ ولا يُعاد
 * التوليد، فالإحصاءاتُ (T-201) تُبنى على أسئلةٍ ثابتة المعرّف.
 *
 * @property string $token
 * @property string $state
 * @property string $status
 * @property string $feedback
 */
class Quiz extends Model
{
    use BelongsToTenant;

    public const STATE_READY = 'ready';

    public const STATE_FAILED = 'failed';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const FEEDBACK_END = 'end';

    public const FEEDBACK_IMMEDIATE = 'immediate';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opens_count' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /** رمزُ الرابط العامّ — اثنا عشر حرفاً عشوائياً، فلا يُعدّ ولا يُخمَّن. */
    public static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(12));
        } while (self::query()->withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }

    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isReady(): bool
    {
        return $this->state === self::STATE_READY;
    }

    public function isOpen(): bool
    {
        return $this->isReady() && $this->status === self::STATUS_OPEN;
    }

    public function revealsImmediately(): bool
    {
        return $this->feedback === self::FEEDBACK_IMMEDIATE;
    }

    /** أبدأ أحدٌ هذا الاختبار؟ — فتُقفل بنيتُه. */
    public function hasAttempts(): bool
    {
        return $this->attempts()->exists();
    }

    /** الرابطُ العامّ — مطلقٌ، فيُنسخ ويُشارَك ويُرسم في صفحةٍ تُخدَم من نطاقٍ آخر. */
    public function publicUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/q/'.$this->token;
    }
}
