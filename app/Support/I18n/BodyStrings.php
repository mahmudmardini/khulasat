<?php

declare(strict_types=1);

namespace App\Support\I18n;

/**
 * جدولُ نصوصِ المتن — يُستخرَج للترجمة ويُعاد تركيبه، T-38.
 *
 * **والنموذج لا يُعيد بناء الشجرة.** يُعطى قائمةً مسطّحة من `{مفتاح: نصّ}`
 * ويردّ مثلَها، ويُركّب هذا الصانعُ الشجرةَ بنفسه. وعلّةُ ذلك هي علّةُ T-43
 * بعينها: نموذجٌ يُعيد بناء JSON متداخلٍ يُخطئ بنيتَه أحياناً، **فتضيع
 * كتلةٌ صامتةً**. وقائمةٌ مسطّحة لا بنيةَ فيها تُخطأ.
 *
 * ★★ **ولفظُ الشاهد لا يُترجَم ولا يُرسَل أصلاً** — سياسة T-38: «الشاهد يبقى
 * بلفظه العربي، ولا يُترجَم نصُّه ولا يُستبدل به». فـ`evidence.text` **ليس
 * في الجدول**، فلا يبلغ النموذجَ ولا يعود منه مبدَّلاً. وطبقةُ التحقّق تعمل
 * على العربيّ كما هي، بلا مساس.
 *
 * وما عداه يُترجَم: العناوين والفقرات والمحاور والمقارنة والمهامّ والختام.
 */
final class BodyStrings
{
    private function __construct() {}

    /**
     * مفاتيحُ لا تُترجَم أينما وردت.
     *
     * `type` و`kind` و`tone` **قيمُ تعداد** يقرؤها العارض، وترجمتُها تُسقط
     * الكتلة. و`value` رقمُ الإحصاء، والأرقام لا تُترجَم.
     */
    private const NEVER = ['type', 'kind', 'tone', 'value', 'ordered', 'muted'];

    /**
     * يستخرج ما يُترجَم من متن المرحلة ٥.
     *
     * والمفتاح مسارٌ نقطيّ (`sections.0.blocks.2.text`) — به يُعاد النصّ إلى
     * موضعه بلا لبس، ولو تكرّر النصّ نفسُه في موضعين.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, string>
     */
    public static function extract(?array $body): array
    {
        if ($body === null) {
            return [];
        }

        $out = [];
        self::walk($body, '', $out, false);

        return $out;
    }

    /**
     * يُعيد النصوص المترجَمة إلى مواضعها.
     *
     * **وما لم يعُد له ترجمةٌ يبقى بأصله العربي** — لا يُحذف ولا يُفرَّغ.
     * فنموذجٌ أسقط مفتاحاً يترك سطراً عربياً في صفحةٍ تركية، وذلك أهونُ من
     * سطرٍ فارغ: القارئ يرى أنّ شيئاً لم يُترجم، ولا يظنّ أنّ شيئاً نقص.
     *
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $translations
     * @return array<string, mixed>|null
     */
    /**
     * يضع معنى كلّ حديثٍ في كتلته — T-67.
     *
     * **والمفاتيح تنتهي بـ`.text`** كما تُخرجها {@see self::hadithTexts()}،
     * فيُبدَّل الطرفُ إلى `meaning`: اللفظُ يبقى مكانه والمعنى بجانبه.
     *
     * ★ **ولا يُكتب معنًى فارغ**: كتلةٌ بحقلٍ فارغ تُرسم موضعاً فارغاً،
     * وشاهدٌ بلا معنًى مترجَم يخرج عربياً وحده — وذلك أصحّ من وسمٍ لا شيء تحته.
     *
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $meanings
     * @return array<string, mixed>|null
     */
    public static function withMeanings(?array $body, array $meanings): ?array
    {
        if ($body === null || $meanings === []) {
            return $body;
        }

        $out = $body;

        foreach ($meanings as $path => $meaning) {
            if (trim($meaning) === '' || ! str_ends_with($path, '.text')) {
                continue;
            }

            $segments = explode('.', substr($path, 0, -strlen('.text')));
            $cursor = &$out;

            foreach ($segments as $segment) {
                if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
                    continue 2;
                }

                $cursor = &$cursor[$segment];
            }

            if (is_array($cursor)) {
                $cursor['meaning'] = trim($meaning);
            }

            unset($cursor);
        }

        return $out;
    }

    public static function apply(?array $body, array $translations): ?array
    {
        if ($body === null) {
            return null;
        }

        $out = $body;
        self::put($out, '', $translations, false);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $out
     * @param  bool  $inEvidence  داخل كتلة شاهد — فيها `text` لا يُترجَم
     */
    private static function walk(array $node, string $prefix, array &$out, bool $inEvidence): void
    {
        $inEvidence = $inEvidence || ($node['type'] ?? null) === 'evidence';

        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (in_array((string) $key, self::NEVER, true)) {
                continue;
            }

            // ★★ لفظُ الشاهد — لا يُرسَل ولا يُترجَم.
            if ($inEvidence && (string) $key === 'text') {
                continue;
            }

            if (is_array($value)) {
                self::walk($value, $path, $out, $inEvidence);

                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $out[$path] = $value;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $translations
     */
    private static function put(array &$node, string $prefix, array $translations, bool $inEvidence): void
    {
        $inEvidence = $inEvidence || ($node['type'] ?? null) === 'evidence';

        foreach ($node as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (in_array((string) $key, self::NEVER, true)) {
                continue;
            }

            if ($inEvidence && (string) $key === 'text') {
                continue;
            }

            if (is_array($value)) {
                self::put($node[$key], $path, $translations, $inEvidence);

                continue;
            }

            $translated = $translations[$path] ?? null;

            if (is_string($value) && is_string($translated) && trim($translated) !== '') {
                $node[$key] = $translated;
            }
        }
    }

    /**
     * شواهدُ الحديث في المتن — تحتاج **ترجمة معنًى** لا ترجمة لفظ.
     *
     * وهي مطلبٌ منفصل عن الجدول: الجدولُ يُترجم ما حول الشاهد، وهذه تُنتج
     * معنًى يُعرض **بجانب اللفظ العربي موسوماً** (معيار قبولٍ في T-38:
     * «من قرأ الترجمة وحدها ظنّها الحديث»).
     *
     * **والآياتُ ليست هنا**: ترجمتُها معتمدةٌ تُقرأ من `quran_translations`،
     * ولا يترجمها نموذج.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, string> المسار ← لفظ الحديث
     */
    public static function hadithTexts(?array $body): array
    {
        if ($body === null) {
            return [];
        }

        $out = [];
        self::collectHadith($body, '', $out);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, string>  $out
     */
    private static function collectHadith(array $node, string $prefix, array &$out): void
    {
        if (($node['type'] ?? null) === 'evidence' && ($node['kind'] ?? null) === 'hadith') {
            $text = trim((string) ($node['text'] ?? ''));

            if ($text !== '') {
                $out[$prefix.'.text'] = $text;
            }

            return;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                self::collectHadith($value, $prefix === '' ? (string) $key : $prefix.'.'.$key, $out);
            }
        }
    }
}
