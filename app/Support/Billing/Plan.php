<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Actions\Admin\RecordAudit;
use App\Models\Tenant;
use App\Support\Render\Palette;

/**
 * شريحة اشتراك — T-23، والمادّة 11 من دراسة المشروع.
 *
 * **قالبُ ملءٍ لا محرّكُ حصص.** فالحدود الخمسة **أعمدةٌ في `tenants`**
 * و`QuotaGuard` يقرأ منها مباشرة (T-13)، وهذه تملؤها مرّةً عند التطبيق ثمّ
 * لا شأن لها بها. ولو صارت الشريحةُ مصدرَ الحساب لبطل قولُ المهمّة «يبقى
 * كلّ رقمٍ بعده قابلاً للتعديل وحده»، ولاحتاج كلُّ استثناءٍ شريحةً جديدة.
 *
 * وتُقرأ من `config/khulasah.php` كما تُقرأ اللوحات من {@see Palette}:
 * ثوابتُ إعدادٍ تُراجَع في المستودع، لا صفوفٌ تُحرَّر في الإنتاج.
 */
final readonly class Plan
{
    /** الأعمدة الخمسة التي تملؤها الشريحة — المواصفة §11. */
    public const LIMITS = [
        'monthly_quota',
        'daily_cap',
        'max_lecture_minutes',
        'transcription_minutes_quota',
        'regenerations_per_summary',
    ];

    /** @param  array<string, int>  $limits */
    private function __construct(
        public string $key,
        public string $nameAr,
        public array $limits,
        /** ما تدفعه الجهة شهرياً — `null` ما لم يُحسم رقمٌ بعد (T-22). */
        public ?float $priceUsd = null,
    ) {}

    /** @return array<string, self> */
    public static function all(): array
    {
        $plans = [];

        /** @var array<string, array{name: string, limits: array<string, int>, price_usd?: float|null}> $configured */
        $configured = (array) config('khulasah.plans', []);

        foreach ($configured as $key => $definition) {
            $limits = [];

            // **الأعمدة الخمسة كلّها أو لا شيء.** وشريحةٌ ناقصةُ حدٍّ تترك
            // عمود الجهة على ما كان، فتخرج الجهةُ بخليطٍ من شريحتين.
            foreach (self::LIMITS as $column) {
                $limits[$column] = (int) ($definition['limits'][$column] ?? 0);
            }

            $price = $definition['price_usd'] ?? null;

            $plans[$key] = new self(
                $key,
                (string) ($definition['name'] ?? $key),
                $limits,
                $price === null ? null : (float) $price,
            );
        }

        return $plans;
    }

    public static function find(?string $key): ?self
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    /**
     * اسم شريحة الجهة كما يُعرض، ولو لم تعد الشريحة معرَّفة.
     *
     * **والمفتاح الخام أصدق من فراغ**: جهةٌ على شريحةٍ حُذفت من الإعداد
     * تبقى حدودُها في أعمدتها تعمل، فشاشةٌ تقول «—» تُوهم أنّ لا اشتراك لها.
     */
    public static function label(?string $key): string
    {
        return self::find($key)?->nameAr ?? (string) $key;
    }

    /** أتُتاح لهذه الشريحة المخرجات الإضافية؟ — SCREENS.md §3-ب. */
    public function allowsRichOutputs(): bool
    {
        return in_array($this->key, (array) config('khulasah.outputs.rich_plans', []), true);
    }

    /**
     * يملأ أعمدة الجهة الخمسة ويكتب اسم الشريحة في `plan`.
     *
     * **ولا يحفظ.** والحفظ عند المنادي، لأنّ الفرق بين ما كان وما صار
     * يُقرأ من النموذج **قبل** الحفظ ({@see RecordAudit::diff()})
     * — فحفظٌ هنا يُفقد سجلَّ التدقيق ما كان.
     */
    public function applyTo(Tenant $tenant): void
    {
        $tenant->fill([...$this->limits, 'plan' => $this->key]);
    }

    /**
     * أتُطابق أعمدةُ هذه الجهة الشريحةَ كما هي؟
     *
     * **ويُعرض الفرق ولا يُخفى**: جهةٌ على «مسجد» بحصّةٍ مرفوعة استثناءً
     * حالةٌ مقصودة، وشاشةٌ تقول «مسجد» وحدها تُخفي أنّ حدودها ليست حدودها.
     */
    public function matches(Tenant $tenant): bool
    {
        foreach ($this->limits as $column => $value) {
            if ((int) $tenant->getAttribute($column) !== $value) {
                return false;
            }
        }

        return true;
    }
}
