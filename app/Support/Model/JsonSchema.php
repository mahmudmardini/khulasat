<?php

declare(strict_types=1);

namespace App\Support\Model;

/**
 * Validates a stage's JSON output against its shape — المواصفة §6 و§12.
 *
 * «**مخرَج كلّ مرحلة يُتحقّق من مخطّطه**، فأيّ خروج عن الشكل المتوقّع يُوقف
 * المهمّة» — §12. وهذا **الحاجز الأهمّ** أمام حقن التعليمات: نموذجٌ انصاع
 * لأمرٍ في التفريغ يُخرج شيئاً غير الشكل المطلوب، فيُمسَك هنا.
 *
 * **ولا يُصلَح JSON معطوب بالتحليل النصّي** — T-10 صراحةً. المعطوب يُرفض،
 * ويُعاد الاستدعاء مرّة، ثم تقف المهمّة. ومن رقّع القوس الناقص لا يدري ما
 * رقّع، وقد يكون رقّع لفظ حديث.
 *
 * **مجموعةٌ فرعية مقصودة من JSON Schema**، لا تنفيذٌ كامل له: تكفي أشكالَ
 * §6 كلَّها، وتُقرأ في مراجعةٍ في دقيقة. المدعوم:
 *
 *   - `type`: object · array · string · integer · number · boolean · null
 *   - `required`: أسماء مفاتيح لازمة
 *   - `properties`: مخطّط لكلّ مفتاح
 *   - `items`: مخطّط عناصر المصفوفة
 *   - `enum`: قيمٌ محصورة
 *   - `nullable`: يقبل `null` مع النوع
 *
 * وما لم يُذكر في المخطّط لا يُفحص، والمفاتيح الزائدة تُقبل: النماذج تضيف
 * حقولاً، ورفضُها يُوقف المهمّة بلا ضرر حقيقي.
 *
 * @see khulasah-build-spec.md §6
 */
final class JsonSchema
{
    private function __construct() {}

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string> قائمة المخالفات، وفراغُها يعني المطابقة.
     */
    public static function violations(mixed $value, array $schema, string $path = '$'): array
    {
        $problems = [];

        if (isset($schema['type']) && ! self::matchesType($value, (string) $schema['type'], (bool) ($schema['nullable'] ?? false))) {
            return [sprintf('%s: النوع %s لا %s.', $path, self::describe($value), $schema['type'])];
        }

        if ($value === null) {
            return [];
        }

        if (isset($schema['enum']) && is_array($schema['enum']) && ! in_array($value, $schema['enum'], strict: true)) {
            $problems[] = sprintf('%s: قيمة خارج المسموح.', $path);
        }

        if (is_array($value) && isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $key) {
                if (! array_key_exists($key, $value)) {
                    $problems[] = sprintf('%s.%s: مفتاح لازم غائب.', $path, $key);
                }
            }
        }

        if (is_array($value) && isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $key => $childSchema) {
                if (array_key_exists($key, $value) && is_array($childSchema)) {
                    $problems = [...$problems, ...self::violations($value[$key], $childSchema, $path.'.'.$key)];
                }
            }
        }

        if (is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach (array_values($value) as $index => $item) {
                $problems = [...$problems, ...self::violations($item, $schema['items'], $path.'['.$index.']')];
            }
        }

        return $problems;
    }

    /** @param  array<string, mixed>  $schema */
    public static function matches(mixed $value, array $schema): bool
    {
        return self::violations($value, $schema) === [];
    }

    private static function matchesType(mixed $value, string $type, bool $nullable): bool
    {
        if ($value === null) {
            return $nullable || $type === 'null';
        }

        return match ($type) {
            // في PHP المصفوفة والكائن شيء واحد، فيُميَّزان بالمفاتيح:
            // قائمةٌ مفاتيحُها 0..n مصفوفة، وما عداها كائن. والمصفوفة
            // الفارغة تصلح للاثنين، فتُقبل فيهما.
            'object' => is_array($value) && ! array_is_list($value) || $value === [],
            'array' => is_array($value) && (array_is_list($value) || $value === []),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
    }

    private static function describe(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_array($value) => array_is_list($value) ? 'array' : 'object',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'number',
            is_string($value) => 'string',
            default => get_debug_type($value),
        };
    }
}
