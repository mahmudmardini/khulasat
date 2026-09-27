<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\SummaryTemplate;
use App\Enums\VenueMode;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Render\Palette;
use Database\Factories\LectureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One lecture, and the metadata its summary header is drawn from — المواصفة §4.
 */
class Lecture extends Model
{
    /** @use HasFactory<LectureFactory> */
    use BelongsToTenant, HasFactory;

    /** المواصفة §4 تذكر created_at وحده لهذا الجدول. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'locales' => 'array',
            'venue_mode' => VenueMode::class,
            'gregorian_date' => 'date',
            'duration_seconds' => 'integer',
            'want_carousel' => 'boolean',
            'show_logo' => 'boolean',
        ];
    }

    /**
     * أيُرسم شعارُ الجهة في ملخّص هذه المحاضرة — T-99.
     *
     * محاضراتُ ما قبل T-99 بلا شعار ولو رُفع بعدها (كتبها الترحيلُ `false`)،
     * وما بعدها بشعار الجهة إن كان لها شعار. ويُقرأ من السمات مباشرةً كما في
     * {@see self::palette()}، **والغائبُ افتراضُ العمود**: محاضرةٌ أُنشئت للتوّ
     * لا تحمل العمودَ في سماتها حتى تُقرأ من القاعدة.
     */
    public function showsLogo(): bool
    {
        $raw = $this->attributes['show_logo'] ?? null;

        return $raw === null ? true : (bool) $raw;
    }

    /** @return HasMany<SummaryJob, $this> */
    public function summaryJobs(): HasMany
    {
        return $this->hasMany(SummaryJob::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** المواصفة §11: يُقاس الطول قبل البدء ويُرفض الزائد قبل صرف أيّ توكن. */
    public function exceedsTenantLimit(): bool
    {
        if ($this->duration_seconds === null) {
            return false;
        }

        return $this->duration_seconds > $this->tenant->max_lecture_minutes * 60;
    }

    /**
     * لوحةُ ألوان هذه المحاضرة — أو افتراضُ الجهة — T-60.
     *
     * **و`null` تعني «كما في إعدادات الجهة» لا قيمةً منسوخة** — نظيرُ
     * {@see self::template()} بحرفها.
     */
    public function palette(): Palette
    {
        /*
         * ★ **`$this->attributes` مباشرةً لا `$this->palette`** — عطبٌ كامنٌ
         * كُشف بالاختبار: اسمُ هذه الدالّة يطابق اسم العمود، فإذا غاب العمودُ
         * عن حزمة السمات المحمَّلة (كما يقع بعد `create()` بلا تمريره صراحةً)
         * يظنّ Eloquent الدالّةَ علاقةً ويرمي استثناءً بدل إرجاع `null`.
         * والقراءةُ من المصفوفة مباشرةً تتجاوز ذلك الاصطلاح كلَّه.
         */
        $raw = $this->attributes['palette'] ?? null;

        if (is_string($raw) && $raw !== '') {
            return Palette::find($raw);
        }

        $kit = (array) ($this->tenant?->brand_kit ?? []);

        return Palette::find($kit['palette'] ?? null);
    }

    /**
     * قالبُ مخرَج هذه المحاضرة — أو افتراضُ الجهة.
     *
     * **و`null` تعني «كما في إعدادات الجهة»** لا قيمةً منسوخة: من بدّل
     * افتراضَ الجهة تبدّل معه كلُّ محاضرةٍ لم تختر لنفسها.
     */
    public function template(): SummaryTemplate
    {
        // نظيرُ العلّة في {@see self::palette()} بحرفها.
        $raw = $this->attributes['template'] ?? null;

        if (is_string($raw) && $raw !== '') {
            return SummaryTemplate::parse($raw);
        }

        $kit = (array) ($this->tenant?->brand_kit ?? []);

        return SummaryTemplate::parse($kit['template'] ?? null);
    }

    /**
     * لغاتُ مخرَج هذه المحاضرة — أو افتراضُ الجهة.
     *
     * **والعربيةُ مضمومةٌ في الحالين**: هي لغةُ المصدر وعليها يجري التحقّق.
     *
     * @return list<Locale>
     */
    public function outputLocales(): array
    {
        $chosen = is_array($this->locales) && $this->locales !== []
            ? Locale::normalizeSet($this->locales)
            : null;

        if ($chosen === null) {
            return $this->tenant?->outputLocales() ?? [Locale::Ar];
        }

        return array_map(static fn (string $v): Locale => Locale::from($v), $chosen);
    }
}
