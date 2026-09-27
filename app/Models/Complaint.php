<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ComplaintKind;
use App\Enums\ComplaintStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * اعتراضٌ من الخارج — دراسة المشروع، المادة 15.
 *
 * **ولا `BelongsToTenant` هنا.** الشكوى تُقدَّم بلا تسجيل دخول ومن خارج
 * الجهة، ونطاقُ المستأجر يُخفيها عن المشرف الذي يجب أن يراها. والعزل
 * يُطبَّق في الاستعلام حين تُعرض للجهة، لا في النموذج.
 */
class Complaint extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => ComplaintKind::class,
            'status' => ComplaintStatus::class,
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }

    /** الموعد المعلن لمعالجتها — **وهو ما يجعل الوعد قابلاً للقياس**. */
    public function dueAt(): Carbon
    {
        return $this->received_at->addHours($this->kind->slaHours());
    }

    /** أتأخّرت عن مهلتها المعلنة؟ */
    public function isOverdue(): bool
    {
        if ($this->resolved_at !== null) {
            return $this->resolved_at->greaterThan($this->dueAt());
        }

        return now()->greaterThan($this->dueAt());
    }

    /**
     * الساعات المتبقّية من المهلة — **موجبةٌ إن بقي وقت، وسالبةٌ إن مضى**.
     *
     * وتُحسب في الخادم لا في المتصفّح: ساعةُ من يقرأ قد تكون مضبوطةً على
     * غير الحقيقة، **ووعدٌ يُقاس بساعة القارئ ليس مقيساً**.
     */
    public function hoursLeft(): float
    {
        if ($this->status->isSettled()) {
            return 0.0;
        }

        $due = $this->dueAt();

        return $due->isPast()
            ? -$due->diffInHours(now(), absolute: true)
            : $due->diffInHours(now(), absolute: true);
    }

    /** الساعات التي استغرقتها المعالجة — للتقرير. */
    public function hoursToResolve(): ?float
    {
        return $this->resolved_at?->diffInHours($this->received_at, absolute: true);
    }
}
