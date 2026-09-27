<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * لغات المخرَج الأربع — T-38، وقرار مالك المنتج في 8 أيلول 2026.
 *
 * **المخرَج وحده لا الواجهة.** لوحةُ الجهة تبقى عربية RTL (CLAUDE.md §1)،
 * والمترجَم هو **الملخّص المنشور** الذي يقرأه الناس. وتعدّدُ لغات الواجهة
 * خارج النطاق حتى يُطلب.
 *
 * **والعربية ليست لغةً كالثلاث.** هي **لغةُ المصدر**: عليها يجري التحقّق
 * مرّةً واحدة، ومنها تُترجَم البقية. فلا تُترجَم ولا يُنادى لها نموذجُ
 * ترجمة، ولا يُنشر ملخّصٌ بلا نسختها.
 *
 * ★ **ومعرّفاتُ ترجمات القرآن هنا لأنّها قرارٌ محسوم** (T-38، 8 أيلول):
 * ترجماتٌ معتمدةٌ منشورة في المصدر الذي نبذر منه أصلاً. **ولا يترجم نموذجٌ
 * آيةً** — «نموذجٌ يترجم آيةً وثمّ ترجمةٌ معتمدة منشورة، مخاطرةٌ بلا مقابل».
 */
enum Locale: string
{
    /** العربية — لغةُ المصدر والتحقّق. لا تُترجَم. */
    case Ar = 'ar';

    case En = 'en';

    case Tr = 'tr';

    case Ru = 'ru';

    public static function source(): self
    {
        return self::Ar;
    }

    /** ما وصل من إعدادات الجهة قد يكون قديماً أو محرَّفاً، فيسقط إلى المصدر. */
    public static function parse(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::source() : self::source();
    }

    public function isSource(): bool
    {
        return $this === self::source();
    }

    /**
     * اتّجاه الصفحة — العربية وحدها RTL.
     *
     * وهو **سمةٌ على `<html>`** لا قاعدةٌ في ورقة الأنماط: القالب مضبوطٌ
     * للعربية (§2 القاعدة الأولى)، والاتّجاهُ يُقلب بـ`dir` فتتبعه
     * الخصائصُ المنطقية (`padding-inline`, `border-inline-start`) وحدَها.
     */
    public function direction(): string
    {
        return $this === self::Ar ? 'rtl' : 'ltr';
    }

    /** اسمُ اللغة بلسانها — يُعرض للقارئ في الصفحة المنشورة. */
    public function nativeName(): string
    {
        return match ($this) {
            self::Ar => 'العربية',
            self::En => 'English',
            self::Tr => 'Türkçe',
            self::Ru => 'Русский',
        };
    }

    /**
     * وسمُ ترجمة المعنى — يُعرض تحت لفظ الحديث في الصفحة، T-67.
     *
     * ★ **ويُوسَم ولا يُترك عارياً**: لفظُ الحديث يبقى عربياً (T-38)، وما
     * تحته **معنًى لا لفظ**. وترجمةٌ بلا وسمٍ تُقرأ حديثاً بلغةٍ أخرى، وذلك
     * نسبةُ لفظٍ إلى النبيّ ﷺ لم يقله.
     */
    public function meaningLabel(): string
    {
        return match ($this) {
            self::Ar => 'ترجمة معنى',
            self::En => 'Meaning',
            self::Tr => 'Anlamı',
            self::Ru => 'Значение',
        };
    }

    /** اسمُها بالعربية — يُعرض في لوحة الجهة، وهي عربيةٌ كلُّها. */
    public function label(): string
    {
        return match ($this) {
            self::Ar => 'العربية',
            self::En => 'الإنجليزية',
            self::Tr => 'التركية',
            self::Ru => 'الروسية',
        };
    }

    /**
     * معرّف الترجمة المعتمدة في `api.quran.com/api/v4` — T-38.
     *
     * و`null` للعربية: لا تُترجَم، ونصُّها المبذور هو الأصل.
     */
    public function quranTranslationId(): ?int
    {
        return match ($this) {
            self::Ar => null,
            self::En => 20,  // Saheeh International
            self::Ru => 45,  // Elmir Kuliev
            self::Tr => 77,  // Diyanet İşleri
        };
    }

    /** اسمُ الترجمة ومترجمُها — يُنسب في حاشية الصفحة، ولا يُنشر مجهولاً. */
    public function quranTranslationName(): ?string
    {
        return match ($this) {
            self::Ar => null,
            self::En => 'Saheeh International',
            self::Ru => 'Эльмир Кулиев',
            self::Tr => 'Diyanet İşleri',
        };
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }

    /**
     * ما يُترجَم — الثلاثُ دون لغة المصدر.
     *
     * @return list<self>
     */
    public static function translatable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $l): bool => ! $l->isSource()));
    }

    /**
     * تطبيعُ لغات **النشر** التي اختارها المستخدم — T-38، ونُقض شرطُها في
     * T-51.
     *
     * ★★ **ولغةُ المصدر ليست لغةَ نشرٍ مفروضة.** كان هذا يضمّ العربية دائماً
     * ولو لم تُختَر، خلطاً بين أمرين:
     *
     *   - **لغةُ المصدر** — داخلية: عليها يجري التنظيف والاستخراج والتحقّق،
     *     وهي عربيةٌ دائماً ولا تُختار. {@see self::source()}
     *   - **لغاتُ النشر** — اختيارُ المستخدم: ما يُنشر ويُقرأ.
     *
     * فمن أراد ملخّصاً إنجليزياً وحده كان يُجبَر على نشر العربيّ معه. والتحقّق
     * يجري على العربيّ في الحالين، **ونشرُه قرارُ صاحب المحتوى** لا قرارُنا.
     *
     * **ولا تعود فارغة**: من لم يختر شيئاً يُنشر له بلغة المصدر — فملخّصٌ
     * بلا لغةٍ ليس مخرَجاً.
     *
     * @param  mixed  $value  ما وصل من الإعدادات أو من الطلب
     * @return list<string>
     */
    public static function normalizeSet(mixed $value): array
    {
        $chosen = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                $locale = is_string($item) ? self::tryFrom($item) : null;

                if ($locale !== null && ! in_array($locale->value, $chosen, true)) {
                    $chosen[] = $locale->value;
                }
            }
        }

        if ($chosen === []) {
            return [self::source()->value];
        }

        // ترتيبٌ ثابت كترتيب الحالات، فلا يتبدّل المخرَج بترتيب الإدخال.
        return array_values(array_filter(
            array_map(static fn (self $l): string => $l->value, self::cases()),
            static fn (string $v): bool => in_array($v, $chosen, true),
        ));
    }

    /**
     * اللغةُ الأولى في المجموعة — وهي التي تُنشر على المسار الجذر.
     *
     * **ولكلّ ملخّصٍ منشورٍ رابطٌ جذر** لا مقطعَ لغةٍ فيه: هو ما يُشارَك
     * ويُفهرَس. فلو نُشرت اللغاتُ كلُّها في مقاطع لبقي الجذرُ فارغاً، ولانكسر
     * كلُّ رابطٍ منشورٍ قبل تعدّد اللغات.
     *
     * وترتيبُ الحالات يجعل العربية أولى متى اختيرت — **فما نُشر قبل اليوم
     * يبقى حيث هو**.
     *
     * @param  list<Locale>  $locales
     */
    public static function primaryOf(array $locales): self
    {
        return $locales[0] ?? self::source();
    }
}
