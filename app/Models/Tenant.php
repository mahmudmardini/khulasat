<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Locale;
use App\Enums\UnverifiedPolicy;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * جهة مشتركة. **لا يحمل BelongsToTenant** — هو الجذر الذي تُنسب إليه البقية.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $guarded = [];

    /** حالتا الاشتراك — والعمود نصٌّ لا enum، كما في `outputs.type`. */
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    protected function casts(): array
    {
        return [
            'brand_kit' => 'array',
            'on_unverified' => UnverifiedPolicy::class,
            'monthly_quota' => 'integer',
            'daily_cap' => 'integer',
            'max_lecture_minutes' => 'integer',
            'transcription_minutes_quota' => 'integer',
            'regenerations_per_summary' => 'integer',
        ];
    }

    /**
     * أتملك هذه الجهة المخرجات الإضافية؟ — SCREENS.md §3-ب.
     *
     * الكاروسيل وحزمة الصور «من شريحة مؤسسة فما فوق». وكانت القائمة فارغة
     * حتى يوجد العارضان وتوجد الشرائح، **وقد وُجدا** (T-19 وT-23).
     */
    /**
     * لغاتُ المخرَج التي اختارتها الجهة — T-38.
     *
     * **والعربيةُ فيها دائماً** ولو لم تُختَر: هي لغةُ المصدر، وعليها يجري
     * التحقّق. و`normalizeSet` تضمّها وتُرتّب وتُسقط ما ليس لغة.
     *
     * @return list<Locale>
     */
    public function outputLocales(): array
    {
        $kit = (array) ($this->brand_kit ?? []);

        return array_map(
            static fn (string $value): Locale => Locale::from($value),
            Locale::normalizeSet($kit['locales'] ?? null),
        );
    }

    public function allowsRichOutputs(): bool
    {
        return in_array($this->plan, (array) config('khulasah.outputs.rich_plans', []), true);
    }

    /**
     * **أموقوفٌ إنتاجُها؟** — T-23، وقاعدة التعليق.
     *
     * والموقوف **الإنتاجُ وحده**: صفحاتها المنشورة تبقى تُخدَم من التخزين
     * كما هي، ولا يُمسّ منها شيء. «الجهة نشرت هذه الصفحات وشاركها الناس،
     * وإسقاطها لخلافٍ ماليّ يضرّ بمن لا ذنب له» — وسببُ القاعدة السمعةُ
     * لا الفوترة، فهي قائمةٌ وإن لم يكن ثمّة دفعٌ إلكتروني.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::SUSPENDED;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
