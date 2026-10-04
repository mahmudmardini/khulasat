<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\SlideKind;
use App\Models\Tenant;
use InvalidArgumentException;

/**
 * مواصفةُ تصميم الكاروسيل — T-173.
 *
 * **القالب هيكلٌ ثابت ومواصفة.** الهيكل Blade وCSS في `resources/views/carousel/`،
 * وهذه تختار له شكله: أدوارَ الألوان من لوحة الجهة، والخلفية، والإطار، والزخرفة،
 * وخطَّ العنوان، وتخطيطاً لكلّ نوع شريحة.
 *
 * ★ **وكلُّ قيمةٍ فيها من كتالوجٍ مغلق.** لا لونٌ حرّ ولا CSS ولا نصّ، فلا تقدر
 * مواصفةٌ — ولو ولّدها نموذج — أن تضيف نصّاً إلى شريحة، أو تُخفي لفظ شاهد، أو
 * تطلب ملفّاً من الشبكة. ومواصفةٌ بقيمةٍ خارجه تُرفض كلُّها، لا تُصلَح.
 *
 * **والمواصفةُ الافتراضية هي القالب كما كان قبلها**: أوّلُ قيمةٍ في كلّ حقل هي
 * ما كان يُرسم، وأصنافُها لا قاعدةَ لها في الأنماط، فلا يتبدّل كاروسيلٌ قائم.
 */
final readonly class CarouselDesign
{
    public const DEFAULT_ID = 'default';

    /**
     * الحقول وقيمُها المقبولة. **والأولى في كلّ حقلٍ افتراضيُّه.**
     *
     * @var array<string, list<string>>
     */
    public const CATALOG = [
        // خلفيةُ شرائح المتن، من لوحة الجهة.
        'surface' => ['paper', 'paper-2', 'night'],
        // خلفيةُ الأولى والأخيرة.
        'bookends' => ['deep', 'paper'],
        // نقشٌ حول الإطار، أو تدرّجٌ خفيف على الشريحة كلّها.
        'background' => ['plain', 'gradient', 'dots', 'lines', 'lattice'],
        'frame' => ['thin', 'double', 'corners', 'none'],
        // زخرفةُ التاج في الأولى والأخيرة.
        'ornament' => ['crest', 'star', 'rosette', 'none'],
        'heading_font' => ['amiri', 'reem-kufi', 'aref-ruqaa', 'plex'],
        // لونُ التمييز: سطرُ المصدر، والرقم، وما فوق العنوان.
        'accent' => ['gold', 'clay', 'emerald'],
        'number' => ['circle', 'plain', 'bar'],
    ];

    /**
     * التخطيطات لكلّ نوع شريحة. **والأوّل افتراضيُّه**، وثلاثةٌ على الأقلّ لكلّ
     * نوع (معيار قبول).
     *
     * @var array<string, list<string>>
     */
    public const LAYOUTS = [
        'cover' => ['classic', 'band', 'poster'],
        'ayah' => ['classic', 'medallion', 'band'],
        'concept' => ['classic', 'band', 'side'],
        'diagnosis' => ['classic', 'band', 'side'],
        'axis' => ['classic', 'band', 'side'],
        'comparison' => ['classic', 'band', 'side'],
        'evidence' => ['classic', 'medallion', 'side'],
        'closing' => ['classic', 'band', 'poster'],
    ];

    /** خطوطٌ تُطلب فوق خطوط القالب الثلاثة، باسمها في Google Fonts. */
    private const EXTRA_FONTS = [
        'reem-kufi' => 'Reem+Kufi:wght@500;700',
        'aref-ruqaa' => 'Aref+Ruqaa:wght@400;700',
    ];

    /**
     * @param  array<string, string>  $values  حقلٌ ← قيمة، لكلّ حقلٍ في الكتالوج.
     * @param  array<string, string>  $layouts  نوعُ شريحة ← تخطيط، لكلّ نوع.
     */
    private function __construct(
        public string $id,
        public ?string $name,
        public array $values,
        public array $layouts,
    ) {}

    public static function default(): self
    {
        return new self(
            self::DEFAULT_ID,
            null,
            array_map(static fn (array $options): string => $options[0], self::CATALOG),
            array_map(static fn (array $options): string => $options[0], self::LAYOUTS),
        );
    }

    /**
     * مواصفةٌ من مصفوفة — محفوظة، أو ولّدها نموذج.
     *
     * **صارمةٌ لا متسامحة**: كلُّ حقلٍ وكلُّ نوع شريحة مطلوب، وأيُّ قيمةٍ خارج
     * الكتالوج أو مفتاحٍ زائد يُسقطها كلَّها. فمواصفةٌ نصفُها صحيح تُرسم شكلاً
     * لم يعتمده أحد.
     *
     * @throws InvalidArgumentException
     */
    public static function from(mixed $data): self
    {
        if (! is_array($data)) {
            throw new InvalidArgumentException('المواصفة ليست كائناً.');
        }

        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;

        if (! is_string($id) || preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $id) !== 1) {
            throw new InvalidArgumentException('معرّف المواصفة غير صالح.');
        }

        if ($name !== null && (! is_string($name) || trim($name) === '' || mb_strlen($name) > 40)) {
            throw new InvalidArgumentException('اسم المواصفة غير صالح.');
        }

        $known = [...array_keys(self::CATALOG), 'id', 'name', 'layouts'];

        if (($extra = array_diff(array_keys($data), $known)) !== []) {
            throw new InvalidArgumentException('حقلٌ لا يعرفه الكتالوج: '.implode('، ', $extra));
        }

        return new self(
            $id,
            $name === null ? null : trim($name),
            self::pick($data, self::CATALOG),
            self::pick(is_array($data['layouts'] ?? null) ? $data['layouts'] : [], self::LAYOUTS, 'layouts.'),
        );
    }

    /** كـ`from()`، وتعود `null` بدل الاستثناء. */
    public static function tryFrom(mixed $data): ?self
    {
        try {
            return self::from($data);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * مواصفةُ الجهة: المعتمدةُ بمعرّفها إن طُلب، وإلّا أوّلُ ما اعتمدت، وإلّا
     * الافتراضية.
     *
     * **ومحفوظةٌ لا تصحّ تُتخطّى ولا تُسقط الرسم**: الكتالوج قد يضيق يوماً،
     * وكاروسيلٌ يُبنى بالافتراضي خيرٌ من كاروسيلٍ لا يُبنى.
     */
    public static function forTenant(?Tenant $tenant, ?string $id = null): self
    {
        $stored = (array) (((array) ($tenant?->brand_kit ?? []))['carousel_designs'] ?? []);

        if ($id === self::DEFAULT_ID) {
            return self::default();
        }

        $designs = array_values(array_filter(array_map(self::tryFrom(...), $stored)));

        foreach ($designs as $design) {
            if ($design->id === $id) {
                return $design;
            }
        }

        return $designs[0] ?? self::default();
    }

    public function isDefault(): bool
    {
        return $this->id === self::DEFAULT_ID;
    }

    public function value(string $field): string
    {
        return $this->values[$field];
    }

    public function layoutFor(SlideKind $kind): string
    {
        return $this->layouts[$kind->value];
    }

    /**
     * أصنافُ الشرائح كلِّها، على `.deck`.
     *
     * **وكلُّ حقلٍ يُكتب ولو كان افتراضيّاً**: الأنماط لا تعرّف للافتراضي قاعدة،
     * فلا يتغيّر به شيء، ويبقى الصنف دليلاً لمن يقرأ الملفّ.
     */
    public function deckClasses(): string
    {
        $classes = [];

        foreach ($this->values as $field => $value) {
            $classes[] = str_replace('_', '-', $field).'-'.$value;
        }

        return implode(' ', $classes);
    }

    /** ما يُضاف إلى طلب Google Fonts فوق خطوط القالب. @return list<string> */
    public function extraFonts(): array
    {
        $font = $this->values['heading_font'];

        return isset(self::EXTRA_FONTS[$font]) ? [self::EXTRA_FONTS[$font]] : [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            ...$this->values,
            'layouts' => $this->layouts,
        ];
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<string, list<string>>  $catalog
     * @return array<string, string>
     */
    private static function pick(array $data, array $catalog, string $prefix = ''): array
    {
        if ($prefix !== '' && ($extra = array_diff(array_keys($data), array_keys($catalog))) !== []) {
            throw new InvalidArgumentException('نوعُ شريحةٍ لا يعرفه الكتالوج: '.implode('، ', $extra));
        }

        $picked = [];

        foreach ($catalog as $field => $options) {
            $value = $data[$field] ?? null;

            if (! is_string($value) || ! in_array($value, $options, true)) {
                throw new InvalidArgumentException("قيمةٌ خارج الكتالوج في «{$prefix}{$field}».");
            }

            $picked[$field] = $value;
        }

        return $picked;
    }
}
