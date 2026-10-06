<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Summary\TransitionJob;
use App\Domain\Summary\InvalidTransition;
use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Enums\TranscriptSource;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SummaryJobFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One run of the generation pipeline over one lecture — المواصفة §4 و§5.
 *
 * **الحالة لا تُكتب من هنا.** كلّ تغيير حالةٍ يمرّ بـ
 * {@see TransitionJob}، وما عداه يرمي
 * {@see InvalidTransition}. والحارس أدناه هو ما يجعل هذا قاعدةً لا عُرفاً:
 * `$job->update(['state' => 'published'])` يتخطّى الخريطة كلّها لولاه.
 */
class SummaryJob extends Model
{
    /** @use HasFactory<SummaryJobFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    /** يُرفع داخل {@see self::writeTransition()} وحده. */
    private bool $transitioning = false;

    protected function casts(): array
    {
        return [
            'state' => JobState::class,
            'transcript_source' => TranscriptSource::class,
            'attempt' => 'integer',
            'regeneration_count' => 'integer',
            'transcript_word_count' => 'integer',
            'structure_json' => 'array',
            'body_json' => 'array',
            // بيانات صفحة الملخّص — مخرَج المرحلة ٦، T-57.
            'output_meta_json' => 'array',
            'evidence_json' => 'array',
            'cost_breakdown' => 'array',
            'total_cost_usd' => 'decimal:4',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (SummaryJob $job): void {
            if ($job->isDirty('state') && ! $job->transitioning) {
                throw InvalidTransition::outsideStateMachine(
                    (string) $job->getRawOriginal('state'),
                    $job->state->value,
                );
            }
        });
    }

    /** @return BelongsTo<Lecture, $this> */
    public function lecture(): BelongsTo
    {
        return $this->belongsTo(Lecture::class);
    }

    /** @return HasMany<EvidenceItem, $this> */
    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class);
    }

    /** @return HasMany<Output, $this> */
    public function outputs(): HasMany
    {
        return $this->hasMany(Output::class);
    }

    /** اختبارُ الفهم — T-195. واحدٌ لكلّ ملخّص. */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    /**
     * ترجماتُ هذا الملخّص — T-38.
     *
     * @return HasMany<SummaryTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SummaryTranslation::class);
    }

    /** @return HasMany<SummaryJobTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(SummaryJobTransition::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** @return HasMany<UsageRecord, $this> */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * فتحاتُ الصفحة المنشورة، مجموعةً باليوم — T-31، وتُقرأ في T-136.
     *
     * **وتُقرأ بـ`withSum` لا بتحميلٍ مسبق**: الصفوفُ يومٌ لكلّ مخرَجٍ لكلّ
     * مهمّة، فمهمّةٌ عمرُها سنةٌ بمخرَجين ٧٣٠ صفّاً — والمطلوبُ عددٌ في
     * خليّة. فجمعُها في القاعدة استعلامٌ واحد، وتحميلُها لجمعها في PHP
     * يسحب الجدولَ كلَّه إلى الذاكرة ليُطرَح.
     *
     * @return HasMany<PageView, $this>
     */
    public function pageViews(): HasMany
    {
        return $this->hasMany(PageView::class);
    }

    /**
     * Apply a state write. **مقصور على {@see TransitionJob}.**
     *
     * @param  callable(self): void  $mutation
     *
     * @internal
     */
    public function writeTransition(callable $mutation): void
    {
        $this->transitioning = true;

        try {
            $mutation($this);
            $this->save();
        } finally {
            $this->transitioning = false;
        }
    }

    /**
     * لغاتُ النشر: اختيارُ الدرس، وإلّا افتراضُ الجهة، وإلّا المصدر — T-84.
     *
     * @return list<Locale>
     */
    public function outputLocales(): array
    {
        return $this->lecture?->outputLocales() ?? $this->tenant?->outputLocales() ?? [Locale::source()];
    }

    /** اللغةُ الأولى: تُنشر على الجذر، ورابطُها هو الذي يُشارَك — T-64. */
    public function primaryLocale(): Locale
    {
        return Locale::primaryOf($this->outputLocales());
    }

    /**
     * صفحةُ الملخّص **باللغة الأولى** — T-216، والمصدرُ الوحيد لها.
     *
     * ★ **واللغةُ تُثبَّت ولا يُكتفى بأوّل صفّ.** لملخّصٍ منشورٍ بعدّة لغاتٍ صفُّ
     * صفحةٍ لكلّ لغة، وPostgres يُعيدها بلا ترتيبٍ مضمون. فكان `RenderImageSet`
     * يكتب في الحزمة رابطَ `/en` أو `/tr` أحياناً، والمعاينةُ تقارنه برابط اللغة
     * الأولى، فتُعدّ الصورُ أقدمَ من شرائحها وتُخفى بعد إنشائها مباشرة. وتحمل
     * الشريحةُ الأخيرة من الكاروسيل العربيّ رابطَ الصفحة الإنجليزية.
     *
     * فمن كتب الرابطَ ومن قارنه يقرآنه من هنا، فلا يختلفان. والأولى، وإلّا لغةُ
     * المصدر، وإلّا أقدمُ صفحةٍ — ورابطٌ قائمٌ خيرٌ من فراغ.
     */
    public function primaryPage(): ?Output
    {
        $pages = $this->outputs()->where('type', OutputType::Page->value)->orderBy('id')->get();

        return $pages->firstWhere('locale', $this->primaryLocale())
            ?? $pages->firstWhere('locale', Locale::source())
            ?? $pages->first();
    }

    /** كم شاهداً ما زال ينتظر قرار إنسان — المواصفة §5. */
    public function pendingEvidenceCount(): int
    {
        return $this->evidenceItems()
            ->where('review_status', ReviewStatus::Pending->value)
            ->count();
    }

    public function hasUnresolvedEvidence(): bool
    {
        return $this->pendingEvidenceCount() > 0;
    }

    /** هل بقيت محاولة آلية في هذه الحالة — المواصفة §5. */
    public function hasAutomaticAttemptsLeft(): bool
    {
        return $this->state->allowsAutomaticRetry()
            && $this->attempt < JobState::MAX_AUTOMATIC_ATTEMPTS;
    }
}
