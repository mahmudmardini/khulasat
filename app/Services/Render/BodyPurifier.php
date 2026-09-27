<?php

declare(strict_types=1);

namespace App\Services\Render;

use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Facades\Log;

/**
 * تنقية `body_html` القادم من النموذج — المواصفة §8، والقسم 12.
 *
 * **والمخطر الأوّل في المشروع حقنُ التعليمات عبر التفريغ** (§12). ونصُّ
 * المحاضرة يمرّ على نموذجٍ يُخرج HTML، فما يعود ليس موثوقاً وإن كان مصدره
 * نموذجَنا: من كتب في وصف الفيديو وسماً خبيثاً بلغه النموذجُ فأعاده.
 *
 * فالحارس **قائمةُ سماحٍ لا قائمةَ منع**: يُسمح بأصناف المهارة وحدها
 * ووسومها، ويسقط كلُّ ما عداها. **لا `<script>`، ولا `style=`، ولا `on*=`.**
 */
class BodyPurifier
{
    /**
     * أصناف الكتل في `khulasah.skill` — `references/components.md` و
     * `assets/example-al-durus.html`.
     *
     * **وما ليس منها يسقط.** فالنموذج لا يخترع صنفاً، وإن اخترعه فلا أنماط
     * له في القالب أصلاً — فسقوطه أسلم من إدخاله.
     */
    private const ALLOWED_CLASSES = [
        'sec-head', 'mark', 'rule', 'lead', 'muted', 'num', 'tag', 'ico',
        'ayah-hero', 'ayah-no', 'src', 'sacred', 'hadith', 'imam',
        'axis-card', 'axis-q', 'compare', 'good', 'bad', 'warn', 'calm',
        'pair', 'side', 'trio', 'quad', 'node', 'step', 'arrow', 'path', 'paths',
        'pillar', 'pillar-title', 'checks', 'panic', 'final', 'q', 'text',
        'moon', 'night', 'dark', 'divider', 'dot', 'sep', 'box',
        // مواضعُ الزخارف — T-54. تُميّز أيَّ رسمٍ يُحقن بعد التنقية
        // ({@see Ornaments})، فلولاها خرجت المواضعُ كلُّها سواءً.
        'card', 'domain',
        /*
         * أسماءُ الأيقونات — T-61، مسبوقةً بـ`m-` فلا تختلط بصنفٍ آخر.
         * **وقائمةٌ مغلقة كسائر الأصناف**: اسمٌ يكتبه النموذج يُصفّى في
         * {@see BodyBlocks::icon()} قبل أن يُكتب، ويُصفّى هنا ثانيةً.
         */
        'm-treasure', 'm-book', 'm-pulse', 'm-scales', 'm-pillars', 'm-fork',
        'm-heart', 'm-shield', 'm-plant', 'm-target', 'm-lamp',
        /*
         * ★ **`note` و`closing` كانتا ساقطتين** — T-43، ووُجدتا بالحارس
         * الذي بُني في المهمّة نفسها. وتعليمات المرحلة ٥ تنصّ عليهما:
         * `p.note` في كتلة الليل، و`p.closing` للختام. **فجملةُ ختام كلّ
         * ملخّصٍ نُشر كانت تخرج فقرةً عاديّة** بلا تنسيقها.
         */
        'note', 'closing',
        // ترجمةُ الآية تحتها — T-89. بها يعرف العارضُ أنّ في الصفحة ترجمةً
        // تُنسب مرّةً في ذيلها، ونمطُها من `src` معها.
        'ayah-tr',
        /*
         * **مفردات T-46** — خمسُ كتلٍ جديدة. وتُضاف هنا **قبل** أن تُكتب
         * أنماطُها: صنفٌ خارج هذه القائمة يُحذف صامتاً، فيخرج المتن ناقصاً
         * بلا رسالةٍ ولا سجلّ. وقد وقع أربع مرّات في هذا المشروع.
         */
        'data', 'listing', 'bullets', 'numbered',
        'figures-wrap', 'figures', 'figure', 'fig-num', 'fig-label',
        'timeline', 'event', 'when', 'what',
        'tree', 'root', 'branches', 'branch', 'leaf',
        /*
         * **مفردات T-72** — هرمٌ وعجلةٌ، وتفريع `tree` أعمق بلا أصنافٍ
         * جديدة (فرعٌ داخل فرعٍ يعيد `branch`/`leaf` أنفسهما، انظر
         * {@see \App\Support\Render\BodyBlocks::branch}).
         */
        'pyramid', 'tiers', 'tier',
        'gauge', 'ring', 'reading', 'poles', 'pole',
        // إحدى عشرة درجةَ تعبئةٍ للعجلة — ٠ إلى ١٠٠ بخطوة عشرة. ولا صنفَ
        // يكتبه النموذج بنفسه: `BodyBlocks::gauge` يُقرِّب القيمة ويختاره.
        'fill-0', 'fill-10', 'fill-20', 'fill-30', 'fill-40', 'fill-50',
        'fill-60', 'fill-70', 'fill-80', 'fill-90', 'fill-100',
        /*
         * **مفردات T-76** — أربعٌ من مراجعة تصميمٍ خارجية: تعريف مصطلح،
         * وسؤال وجواب، وتنبيهٌ جانبي، وتصحيح شبهة.
         */
        'gloss', 'gloss-head', 'gloss-term', 'gloss-root', 'gloss-body',
        'gloss-label', 'gloss-text',
        'qa', 'qa-item', 'qa-q', 'qa-a',
        'cnote', 'cn-benefit', 'cn-warning', 'cn-subtle', 'cn-mark',
        'cn-title', 'cn-text',
        'mfix', 'mfix-claim', 'mfix-fix', 'mfix-tag', 'mfix-evidence',
    ];

    /**
     * وسوم البنية التي تحتاجها الكتل. **ولا `script` ولا `iframe` ولا `form`.**
     *
     * والقائمة **مغلقة**: ما ليس فيها يسقط وسمُه ويبقى نصُّه.
     */
    private const ALLOWED_HTML = [
        'div', 'p', 'span', 'h2', 'h3', 'h4', 'ul', 'ol', 'li',
        'b', 'strong', 'i', 'em', 'br', 'hr', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td', 'small', 'sup',
    ];

    /**
     * ما يزيد على `class` من الخصائص، لوسمٍ بعينه.
     *
     * **و`type` وحدها على `input`**: بها يصير مربّعاً، وبغيرها حقلَ نصّ.
     * ولا `name` ولا `value` ولا `checked` — فلا شيء يُرسَل ولا حالة تُزوَّر.
     */
    private const EXTRA_ATTRIBUTES = [
        'input' => ['type'],
    ];

    /**
     * سمتان تسريان على كلّ وسم — T-66.
     *
     * **العربيّ داخل صفحةٍ إنجليزية بلا اتّجاهٍ يُقرأ مشوّشاً**: `dir` كان
     * على `<html>` وحده، فتحلّ خوارزميةُ يونيكود ثنائيةُ الاتّجاه علاماتِ
     * الترقيم المحايدة بحسب اتّجاه الفقرة، فتقفز النقطةُ والقوسُ إلى الطرف
     * الخطأ في متن كلّ حديث. **ولفظُ الشاهد عربيٌّ في كلّ اللغات** (T-38)،
     * فاتّجاهُه لا يتبع لغةَ الصفحة بل يُثبَّت عليه.
     *
     * ★ **وهما آمنتان بحكم قيمتهما**: `dir` يحصرها المنقّي في `ltr|rtl`،
     * و`lang` في صيغة رمز لغة. **فلا سطحَ يُفتح** — بخلاف `style` و`src`
     * وما شابههما ممّا يبقى ممنوعاً.
     */
    private const DIRECTION_ATTRIBUTES = ['dir', 'lang'];

    /**
     * وسومٌ لا تحمل السمتين — `br` و`hr` فارغتان لا نصَّ فيهما، و`input`
     * لا يُترجَم محتواه. **ومنحُها إيّاهما يُطلق تحذيرَ المنقّي في كلّ نداء**،
     * فيمتلئ السجلّ بضجيجٍ لا يدلّ على عطب.
     */
    private const NO_DIRECTION = ['br', 'hr', 'input'];

    /**
     * وسوم HTML5 التي لا يعرفها HTMLPurifier افتراضاً — تعريفُه على XHTML 1.1.
     *
     * و`section` يستعملها القالب لكل قسم من أقسام الملخّص، فإسقاطها يُذهب
     * غلاف القسم وأنماطَه. فتُعرَّف هنا بدل أن تُترك فتسقط.
     *
     * ★ **و`article` كانت ساقطةً منذ البداية** — T-45. وتعليمات المرحلة ٥
     * تنصّ عليها في موضعين: `article.imam` للبطاقات، و`article.axis-card`
     * للمقارنة. فكان غلافُ كلّ بطاقةٍ في كلّ ملخّصٍ يسقط **صامتاً**، ويبقى
     * متنُها بلا تنسيق. وهذا وجهٌ من وجوه «الجودة الرديئة» لا يُصلحه تبديل
     * نموذج، لأنّ النموذج كان يُخرجها صحيحةً ونحن نحذفها.
     */
    private const HTML5_BLOCKS = ['section', 'article', 'figure', 'figcaption'];

    /**
     * **`a` كتلةً لا مضمَّناً** — T-54.
     *
     * ورقةُ المهارة تنسّق `.quad a` وحدها، فبطاقةُ المجال تحتاج الوسمَ
     * لتُنسَّق: كانت `div` فخرجت البطاقاتُ الأربع بلا إطارٍ ولا خلفيةٍ ولا
     * حشوةٍ ولا توسيط.
     *
     * ★ **والتعريفُ لازم**: `a` في مذهب المنقّي (HTML 4.01) **مضمَّنٌ لا
     * يحمل كتلة**، فيمزّق `<a><h3>…</h3></a>` إلى ثلاث قطع ويُخرج بطاقةً
     * مبعثرة — أسوأ ممّا كانت. وHTML5 يسمح للرابط أن يلفّ كتلةً، وهذا ما
     * يُعرَّف هنا كما عُرّفت `section` و`article`.
     *
     * **وبلا `href` ولا `target` ولا `rel`**: لا شيء له في
     * {@see self::EXTRA_ATTRIBUTES}، فيخرج بـ`class` وحدها — زينةٌ خاملة
     * لا وجهةَ لها، فلا سطحَ يُفتح.
     */
    private const BLOCK_ANCHOR = 'a';

    /**
     * وسومٌ ليست في تعريف HTMLPurifier أصلاً، فتُعرَّف بنموذج محتواها.
     *
     * ★ **مربّعات كتلة الليل** — T-43. القالب يُنسّق `.checks input`
     * و`.checks label`، والمهارة تستعملهما، **وكانا يسقطان كلَّيهما** فتختفي
     * المهامّ التي يعملها القارئ.
     *
     * وهما من **وحدة النماذج** التي لا تُحمَّل افتراضاً، فلا يكفي ذكرُهما في
     * قائمة السماح: يُرفضان بتحذير «غير مدعوم».
     *
     * و`input` **مقصورةٌ على مربّع التأشير** بـ`Enum#checkbox`: فحقلُ نصٍّ
     * أو زرُّ إرسالٍ في متنٍ يكتبه نموذج ليس مطلوباً بحال.
     *
     * @var array<string, array{string, string, array<string, string>}>
     */
    private const FORM_ELEMENTS = [
        'label' => ['Inline', 'Inline', []],
        'input' => ['Inline', 'Empty', ['type' => 'Enum#checkbox']],
    ];

    public function purify(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $clean = $this->purifier()->purify($html);

        $this->reportDropped($html, $clean);

        return $clean;
    }

    /**
     * ★ **ما يُسقطه الحارس يُقال** — T-45.
     *
     * كان الإسقاط صامتاً بالكلّية: صنفٌ خارج القائمة — أو وسمٌ ناقصٌ منها،
     * كما كانت `article` — يسقط بلا خطأٍ ولا تحذير، فتخرج الكتلة بلا تنسيق
     * وتبدو الصفحة رديئةً بلا سببٍ ظاهر. **ولا يُكتشف ذلك إلّا بمقارنة
     * منشورٍ بمنشور.**
     *
     * وهذا تحذيرٌ لا إخفاق: الصفحة تُنشر كما هي — فإسقاط صنفٍ أهونُ من
     * إيقاف ملخّصٍ اكتمل — لكنّه يُسجَّل ليُرى في السجلّ.
     */
    private function reportDropped(string $before, string $after): void
    {
        $lost = array_diff(self::classesIn($before), self::classesIn($after));

        if ($lost === []) {
            return;
        }

        Log::warning('أصنافٌ سقطت من متن الملخّص', [
            'classes' => array_values($lost),
        ]);
    }

    /**
     * أصناف كلّ وسمٍ في النصّ، مفكوكةً إلى كلماتها.
     *
     * @return list<string>
     */
    private static function classesIn(string $html): array
    {
        preg_match_all('/class="([^"]*)"/u', $html, $matches);

        $classes = [];

        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/u', trim($attribute)) ?: [] as $class) {
                if ($class !== '') {
                    $classes[$class] = true;
                }
            }
        }

        return array_keys($classes);
    }

    private function purifier(): HTMLPurifier
    {
        $config = HTMLPurifier_Config::createDefault();

        $allowed = [
            ...self::ALLOWED_HTML,
            ...self::HTML5_BLOCKS,
            self::BLOCK_ANCHOR,
            ...array_keys(self::FORM_ELEMENTS),
        ];

        /*
         * `class` وحدها، **مقيَّدةً بكلّ وسمٍ على حدة** لا بإعدادٍ عامّ.
         * فـ`HTML.AllowedAttributes` تسري على التعريف كلِّه، فتنزع `src`
         * المطلوبة من `img` — ووسمٌ خارج قائمتنا أصلاً يُسقط التنقية كلَّها
         * بتحذير. والتقييد بالوسم يمسّ وسومَنا وحدها.
         *
         * ولا `id`: المتن يُدرج في صفحةٍ لها معرّفاتها، ومعرّفٌ مكرّر يكسر
         * سكربت القالب.
         */
        $config->set('HTML.Allowed', implode(',', array_map(
            static fn (string $element): string => $element.'['
                .implode('|', [
                    'class',
                    ...(in_array($element, self::NO_DIRECTION, true) ? [] : self::DIRECTION_ATTRIBUTES),
                    ...(self::EXTRA_ATTRIBUTES[$element] ?? []),
                ]).']',
            $allowed,
        )));

        /*
         * **قائمة الأصناف مغلقة.** و`AllowedClasses` تُسقط ما ليس فيها
         * وتُبقي الوسم — فلا يضيع نصٌّ بصنفٍ غريب، ويضيع الصنف وحده.
         */
        $config->set('Attr.AllowedClasses', self::ALLOWED_CLASSES);

        // لا `style=` البتّة — §8. والأنماط كلّها من ورقة القالب.
        $config->set('CSS.AllowedProperties', []);

        $config->set('Core.Encoding', 'UTF-8');

        // العربية تُكتب كما هي، ولا تُحوَّل إلى كيانات فيتضخّم الملفّ ويصير
        // غيرَ مقروء في الفرق.
        $config->set('Core.EscapeNonASCIICharacters', false);

        $config->set('Cache.SerializerPath', $this->cachePath());

        $config->set('HTML.DefinitionID', 'khulasah.body');
        /*
         * ★ **تُرفع مع كلّ تبديلٍ في `HTML5_BLOCKS`** — وإلّا بقي التعريف
         * القديم في الكاش (`Cache.SerializerPath`) وسرى الإسقاط على بيئةٍ
         * قائمة بلا أن يظهر في التطوير حيث الكاش فارغ. ورُفعت إلى ٢ يوم
         * أُضيفت `article` — T-45.
         */
        $config->set('HTML.DefinitionRev', 4);

        $definition = $config->maybeGetRawHTMLDefinition();

        if ($definition !== null) {
            foreach (self::HTML5_BLOCKS as $element) {
                $definition->addElement($element, 'Block', 'Flow', 'Common');
            }

            // يُعاد تعريفُه لا يُضاف: الوسمُ معروفٌ للمنقّي، والمقصود تبديلُ
            // ما يحمله من مضمَّنٍ إلى كتلة.
            $definition->addElement(self::BLOCK_ANCHOR, 'Block', 'Flow', 'Common');

            foreach (self::FORM_ELEMENTS as $element => [$type, $contents, $attributes]) {
                $definition->addElement($element, $type, $contents, 'Common', $attributes);
            }
        }

        return new HTMLPurifier($config);
    }

    private function cachePath(): string
    {
        $path = storage_path('framework/cache/htmlpurifier');

        if (! is_dir($path)) {
            mkdir($path, 0o755, true);
        }

        return $path;
    }
}
