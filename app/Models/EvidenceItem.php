<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MatchStatus;
use App\Enums\ReviewStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Verification\DomainPolicy;
use App\Support\Verification\EvidenceInput;
use Database\Factories\EvidenceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One quoted evidence and the verdict of the verification layer — المواصفة §4 و§4-أ.
 *
 * الأعمدة الصريحة **عامّة لكلّ مجال**، وما يخصّ المجال في `source_meta`.
 * ولذلك لا تجد هنا `surah_number` ولا `narrator` خاصيّةً مباشرة: قراءتها
 * تمرّ بـ {@see self::meta()} حتى يبقى الجدول قابلاً لمجال ثانٍ بلا ترحيل.
 */
class EvidenceItem extends Model
{
    /** @use HasFactory<EvidenceItemFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source_meta' => 'array',
            'match_status' => MatchStatus::class,
            'review_status' => ReviewStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * **المجال يُنسخ من الجهة عند الإنشاء** — المواصفة §4-أ وT-02ب.
     *
     * ولا يُقرأ من الجهة وقت التحقّق: الجهة قد يتبدّل مجالها، فتُقرأ شواهدُ
     * قديمة بسياسةٍ لم تُصنَّف بها، **ويتغيّر حكمٌ صدر ونُشر**. والمنسوخ في
     * الصفّ يبقى مقروءاً على وجهه بعد كلّ تبديل.
     */
    protected static function booted(): void
    {
        static::creating(function (EvidenceItem $item): void {
            $item->domain ??= $item->tenant?->domain ?? DomainPolicy::DEFAULT;
        });
    }

    /** سياسة الحسم التي يُقاس بها هذا الشاهد — §7-5. */
    public function domainPolicy(): DomainPolicy
    {
        return DomainPolicy::for($this->domain);
    }

    /** @return BelongsTo<SummaryJob, $this> */
    public function summaryJob(): BelongsTo
    {
        return $this->belongsTo(SummaryJob::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** حقلٌ خاصّ بالمجال من `source_meta` — المواصفة §4-أ. */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->source_meta, $key, $default);
    }

    /**
     * Project this row onto the verifier's input — المواصفة §7-4.
     *
     * المحقّق دالّةٌ خالصة لا يعرف التخزين، فيُسقَط إليه الصفّ ولا يُمرَّر
     * النموذج نفسه. وهذا ما يجعل طبقة التحقّق قابلةً للاختبار وحدها.
     */
    public function toVerificationInput(): EvidenceInput
    {
        return new EvidenceInput(
            kind: $this->kind,
            rawText: $this->raw_text,
            claimedSource: $this->meta('book') ?? $this->meta('surah_name_ar'),
            claimedNarrator: $this->meta('narrator'),
            claimedTakhrij: $this->meta('takhrij'),
        );
    }

    /** المواصفة §5: الشاهد غير المحسوم يوقف المهمّة عند `needs_review`. */
    public function isSettled(): bool
    {
        return $this->review_status->isSettled();
    }
}
