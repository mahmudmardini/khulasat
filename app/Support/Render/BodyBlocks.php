<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\Locale;
use App\Support\Arabic;
use App\Support\I18n\PageStrings;
use App\Support\Quran\AyahText;

/**
 * Blocks to the skill's own markup — T-43.
 *
 * **الأصناف تُكتب هنا لا في النموذج.** كان النموذج يُخرج HTML بأربعين اسم
 * صنفٍ حرفيّ، فما أخطأه منها أسقطه `BodyPurifier` صامتاً وخرجت الكتلة بلا
 * تنسيق. فصار يُخرج كتلاً مسمّاة، ويُترجمها هذا الصانع إلى الأصناف نفسها
 * **بايتاً ببايت** كما في `khulasah.skill`.
 *
 * **ولا يُصلح هذا الصانعُ ما نقص.** كتلةٌ بلا نصّ تُحذف، وكتلةٌ ناقصة حقلٍ
 * تُرسم بما فيها — «قسمٌ فارغ أسوأ من قسمٍ محذوف» (PROMPT-PACK). ولا يخترع
 * عنواناً ولا يُكمل جملة: ما لم يقله النموذج لا يُقال عنه.
 *
 * ويبقى `BodyPurifier` بعده حارساً أخيراً: مخرَجُ هذا الصانع نظيفٌ بحكم
 * بنائه، **والحارس لمن يأتي من طريقٍ آخر** — والنصوص نفسها تُهرَّب هنا.
 */
final class BodyBlocks
{
    /**
     * لغةُ المخرَج الجاري رسمُه — T-67.
     *
     * **ولا تدخل في الكتل**: هي وسمُ عرضٍ لا محتوًى، ولو حُفظت في `body_json`
     * لتجمّد كلُّ ملخّصٍ على لغةٍ رُسم بها مرّة.
     */
    private static Locale $locale = Locale::Ar;

    private function __construct() {}

    /**
     * @param  array<string, mixed>|null  $body  ما تحقّق من مخطّط المرحلة ٥.
     */
    public static function toHtml(?array $body, ?Locale $locale = null): string
    {
        // لغةُ المخرَج — بها يُوسَم معنى الحديث (T-67). والافتراض لغةُ المصدر.
        self::$locale = $locale ?? Locale::source();

        if ($body === null) {
            return '';
        }

        $html = '';

        /** @var list<array<string, mixed>> $sections */
        $sections = is_array($body['sections'] ?? null) ? $body['sections'] : [];

        foreach ($sections as $section) {
            $html .= self::section(is_array($section) ? $section : []);
        }

        $closing = trim((string) ($body['closing'] ?? ''));

        if ($closing !== '') {
            $html .= '<p class="closing">'.self::text($closing).'</p>';
        }

        return $html;
    }

    /** @param array<string, mixed> $section */
    private static function section(array $section): string
    {
        $blocks = '';

        /** @var list<array<string, mixed>> $items */
        $items = is_array($section['blocks'] ?? null) ? $section['blocks'] : [];

        foreach ($items as $block) {
            $blocks .= is_array($block) ? self::block($block) : '';
        }

        // **قسمٌ بلا كتلةٍ لا يُرسم** ولو كان له عنوان: عنوانٌ فوق فراغٍ
        // يقول للقارئ إنّ شيئاً ضاع.
        if (trim($blocks) === '') {
            return '';
        }

        $heading = trim((string) ($section['heading'] ?? ''));

        $head = $heading === '' ? '' : '<div class="sec-head">'.self::icon($section, 'mark').'<h2>'
            .self::text($heading).'</h2><span class="rule"></span></div>';

        return '<section>'.$head.$blocks.'</section>';
    }

    /** @param array<string, mixed> $block */
    private static function block(array $block): string
    {
        return match ((string) ($block['type'] ?? '')) {
            'lead' => self::paragraph($block, 'lead'),
            'paragraph' => self::paragraph($block, ($block['muted'] ?? false) === true ? 'muted' : null),
            'evidence' => self::evidence($block),
            'cards' => self::cards($block),
            'comparison' => self::comparison($block),
            'domains' => self::domains($block),
            'pillar' => self::pillar($block),
            'paths' => self::paths($block),
            'night' => self::night($block),
            'table' => self::table($block),
            'list' => self::list($block),
            'stats' => self::stats($block),
            'timeline' => self::timeline($block),
            'tree' => self::tree($block),
            'pyramid' => self::pyramid($block),
            'gauge' => self::gauge($block),
            'term_gloss' => self::termGloss($block),
            'qa_pair' => self::qaPair($block),
            'callout_note' => self::calloutNote($block),
            'misconception_fix' => self::misconceptionFix($block),
            // نوعٌ خارج القائمة لا يصل هنا — المخطّط يردّه. وإن وصل فيُحذف
            // ولا يُرسم بصنفٍ مخترَع.
            default => '',
        };
    }

    /**
     * موضعُ الأيقونة — بالاسم إن اختاره النموذج، وإلّا بزخرفة نوعه.
     *
     * ★ **الاسمُ من قائمةٍ مغلقة** ({@see Ornaments::names()})، ويُصفّى هنا
     * قبل أن يُكتب: **اسمٌ يكتبه النموذج لا يدخل صنفاً في HTML بلا فحص**.
     * وما خرج عنها يسقط إلى زخرفة النوع، فلا فجوةَ ولا صنفٌ مخترَع.
     *
     * @param  array<string, mixed>  $node
     * @param  string  $slot  `mark` لصدر القسم، و`ico` لما سواه
     * @param  string  $fallback  زخرفةُ النوع حين لا اسم
     */
    private static function icon(array $node, string $slot, string $fallback = ''): string
    {
        $name = trim((string) ($node['icon'] ?? ''));

        if ($name !== '' && in_array($name, Ornaments::names(), true)) {
            return '<span class="'.$slot.' m-'.$name.'"></span>';
        }

        return '<span class="'.$slot.($fallback === '' ? '' : ' '.$fallback).'"></span>';
    }

    /** @param array<string, mixed> $block */
    private static function paragraph(array $block, ?string $class): string
    {
        $text = trim((string) ($block['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $attribute = $class === null ? '' : ' class="'.$class.'"';

        return '<p'.$attribute.'>'.self::text($text).'</p>';
    }

    /**
     * الشاهد — ونبرتُه من `tone` لا من اجتهاد العارض.
     *
     * **و`warn` هي نبرة الضعيف المبيَّن** (§7-5 سياسة البيان)، فلا تُستعمل
     * لغيره: لونٌ ينذر فوق حديثٍ صحيح يكذب على القارئ.
     *
     * @param  array<string, mixed>  $block
     */
    private static function evidence(array $block): string
    {
        $text = trim((string) ($block['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $tone = match ((string) ($block['tone'] ?? '')) {
            'warn' => ' warn',
            'dark' => ' dark',
            default => '',
        };

        // `text.hadith` مقاسٌ أصغر: متن الحديث أطول من الآية غالباً.
        $kind = (string) ($block['kind'] ?? '') === 'hadith' ? ' hadith' : '';

        $source = trim((string) ($block['source'] ?? ''));

        /*
         * **الآيةُ تُعرض بعلامتها** — T-56. و`AyahText::html` تُهرّب النصّ
         * ثمّ تُلبس «۝٩٧» صنفَ `.ayah-no` من ورقة المهارة، وهو صنفٌ كان
         * معرَّفاً لا يستعمله أحد. وما سوى الآية يُهرَّب كسائر النصّ.
         */
        $rendered = (string) ($block['kind'] ?? '') === 'ayah'
            ? AyahText::html($text)
            : self::text($text);

        /*
         * ★ **الشاهدُ عربيٌّ في كلّ اللغات** — T-38: «تبقى الشواهد بالعربية
         * ويُترجَم ما حولها». فاتّجاهُه **لا يتبع لغةَ الصفحة** بل يُثبَّت
         * عليه — T-66. ولولاه لحلّت خوارزميةُ يونيكود علاماتِ الترقيم بحسب
         * اتّجاه الصفحة الإنجليزيّ، فتقفز النقطةُ والقوسُ إلى الطرف الخطأ.
         *
         * **ولا أثرَ له على الصفحة العربية**: يطابق اتّجاهَها.
         */
        return '<div class="sacred'.$tone.'"><p class="text'.$kind.'" lang="ar" dir="rtl">'.$rendered.'</p>'
            .self::meaning($block)
            .self::ayahTranslation($block)
            .($source === '' ? '' : '<span class="src">'.self::text($source).'</span>')
            .'</div>';
    }

    /**
     * معنى الحديث بلغة المخرَج — T-67.
     *
     * ★★ **ويُوسَم دائماً.** لفظُ الحديث يبقى عربياً في كلّ اللغات (T-38)،
     * وما تحته **معنًى لا لفظ**. وترجمةٌ بلا وسمٍ تُقرأ حديثاً بلغةٍ أخرى،
     * **وذلك نسبةُ لفظٍ إلى النبيّ ﷺ لم يقله** — وهو أخطر ما في المنتج.
     *
     * ★ **ولا يظهر في الصفحة العربية**: الأصلُ بلسانه، ولا معنى يُترجَم إليه.
     * وشاهدٌ بلا معنًى مترجَم يخرج عربياً وحده بلا موضعٍ فارغ.
     *
     * @param  array<string, mixed>  $block
     */
    private static function meaning(array $block): string
    {
        $meaning = trim((string) ($block['meaning'] ?? ''));

        if ($meaning === '' || self::$locale->isSource()) {
            return '';
        }

        return '<p class="src" lang="'.self::$locale->value.'" dir="'.self::$locale->direction().'">'
            .'<em>'.self::text(self::$locale->meaningLabel()).':</em> '
            .self::text($meaning).'</p>';
    }

    /**
     * ترجمةُ الآية المعتمدة بلغة المخرَج — T-80، وتضعها `AyahTranslations`.
     *
     * ★ **بلا وسمٍ فوق كلّ آية** — T-89، طلبُ مالك المنتج: «Translation of
     * the meaning (Saheeh International)» خمسَ عشرةَ مرّةً في صفحةٍ واحدة.
     * فالنسبةُ إلى المترجم **سطرٌ واحد في ذيل الصفحة** (`PageRenderer`)،
     * والفرقُ بين الآية وترجمتها ظاهرٌ بلا وسم: لفظٌ عربيٌّ فوق، ولسانُ
     * الصفحة تحته.
     *
     * **ويبقى وسمٌ قصير حين يعرض المتنُ بعضَ الآية**: الترجمةُ للآية كلّها،
     * فلا يُظنّ ما زاد فيها معنى ما عُرض.
     *
     * @param  array<string, mixed>  $block
     */
    private static function ayahTranslation(array $block): string
    {
        $translation = trim((string) ($block['translation'] ?? ''));

        if ($translation === '' || self::$locale->quranTranslationName() === null
            || (string) ($block['kind'] ?? '') !== 'ayah') {
            return '';
        }

        $whole = ($block['partial'] ?? false) === true
            ? '<em>'.self::text(PageStrings::of('ayah_translation_whole', self::$locale)).':</em> '
            : '';

        return '<p class="src ayah-tr" lang="'.self::$locale->value.'" dir="'.self::$locale->direction().'">'
            .$whole.self::text($translation).'</p>';
    }

    /** @param array<string, mixed> $block */
    private static function cards(array $block): string
    {
        $cards = '';

        foreach (self::items($block) as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));

            if ($title === '' && $body === '') {
                continue;
            }

            $cards .= '<article class="imam">'.self::icon($item, 'ico', 'card')
                .($title === '' ? '' : '<h3>'.self::text($title).'</h3>')
                .($body === '' ? '' : '<p>'.self::text($body).'</p>')
                .'</article>';
        }

        return $cards === '' ? '' : '<div class="trio">'.$cards.'</div>';
    }

    /** @param array<string, mixed> $block */
    private static function comparison(array $block): string
    {
        $cards = '';

        foreach (self::items($block) as $item) {
            $question = trim((string) ($item['question'] ?? ''));

            $sides = self::side($item['calm'] ?? null, 'calm').self::side($item['panic'] ?? null, 'panic');

            if ($sides === '') {
                continue;
            }

            $cards .= '<article class="axis-card">'
                .($question === '' ? '' : '<h4 class="axis-q">'.self::text($question).'</h4>')
                .'<div class="pair">'.$sides.'</div></article>';
        }

        return $cards === '' ? '' : '<div class="compare">'.$cards.'</div>';
    }

    private static function side(mixed $side, string $class): string
    {
        if (! is_array($side)) {
            return '';
        }

        $text = trim((string) ($side['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $tag = trim((string) ($side['tag'] ?? ''));

        return '<div class="side '.$class.'">'
            .($tag === '' ? '' : '<span class="tag">'.self::text($tag).'</span>')
            .'<p>'.self::text($text).'</p></div>';
    }

    /** @param array<string, mixed> $block */
    private static function domains(array $block): string
    {
        $cells = '';

        foreach (self::items($block) as $item) {
            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $note = trim((string) ($item['note'] ?? ''));

            /*
             * **`<a>` لا `<div>`** — T-54. وورقةُ المهارة تنسّق `.quad a`
             * وحدها، فكانت البطاقاتُ الأربع تخرج بلا إطارٍ ولا خلفيةٍ ولا
             * حشوةٍ ولا توسيط: كتلَ نصٍّ عاريةً في شبكة.
             *
             * **وبلا `href`**: ليست رابطاً يُتَّبع بل بطاقةٌ تُنسَّق، ووسمٌ
             * بلا `href` خاملٌ لا يُنقل ولا يُركَّز عليه. ولذلك يقبله
             * `BodyPurifier` بلا خاصّيّةٍ واحدة.
             */
            $cells .= '<a>'.self::icon($item, 'ico', 'domain').'<h3>'.self::text($title).'</h3>'
                .($note === '' ? '' : '<span>'.self::text($note).'</span>').'</a>';
        }

        return $cells === '' ? '' : '<div class="quad">'.$cells.'</div>';
    }

    /**
     * الأركان — والخطوات مرقّمة بالعربية الهندية كما في القالب.
     *
     * @param  array<string, mixed>  $block
     */
    private static function pillar(array $block): string
    {
        $steps = '';
        $number = 0;

        foreach (self::items($block) as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));

            if ($title === '' && $body === '') {
                continue;
            }

            $number++;

            $steps .= '<div class="step"><div class="num">'.Arabic::toArabicIndicDigits($number).'</div><div>'
                .($title === '' ? '' : '<h4>'.self::text($title).'</h4>')
                .($body === '' ? '' : '<p>'.self::text($body).'</p>')
                .'</div></div>';
        }

        if ($steps === '') {
            return '';
        }

        $title = trim((string) ($block['title'] ?? ''));

        $head = $title === '' ? '' : '<div class="pillar-title">'.self::icon($block, 'ico', 'pillar').'<h3>'
            .self::text($title).'</h3></div>';

        return '<div class="pillar">'.$head.$steps.'</div>';
    }

    /** @param array<string, mixed> $block */
    private static function paths(array $block): string
    {
        $good = self::path($block['good'] ?? null, 'good');
        $bad = self::path($block['bad'] ?? null, 'bad');

        return $good === '' && $bad === '' ? '' : '<div class="paths">'.$good.$bad.'</div>';
    }

    private static function path(mixed $path, string $class): string
    {
        if (! is_array($path)) {
            return '';
        }

        // **سهمٌ بين كلّ عقدتين** — T-54. والعُقد كانت متتابعةً بلا فاصل،
        // فيُقرأ المسارُ قائمةً لا سيراً. والسهمُ زينةٌ يضعها الراسم، لا
        // مدخلَ للنموذج فيه.
        $arrow = '<span class="arrow '.$class.'"></span>';
        $nodes = '';

        foreach (is_array($path['nodes'] ?? null) ? $path['nodes'] : [] as $node) {
            $text = trim((string) $node);

            if ($text !== '') {
                $nodes .= ($nodes === '' ? '' : $arrow).'<div class="node">'.self::text($text).'</div>';
            }
        }

        $final = trim((string) ($path['final'] ?? ''));

        if ($final !== '') {
            $nodes .= ($nodes === '' ? '' : $arrow).'<div class="node final">'.self::text($final).'</div>';
        }

        if ($nodes === '') {
            return '';
        }

        $title = trim((string) ($path['title'] ?? ''));

        return '<div class="path '.$class.'">'
            .($title === '' ? '' : '<h4>'.self::text($title).'</h4>').$nodes.'</div>';
    }

    /** @param array<string, mixed> $block */
    private static function night(array $block): string
    {
        $quote = trim((string) ($block['text'] ?? ''));
        $checks = '';

        foreach (is_array($block['checks'] ?? null) ? $block['checks'] : [] as $check) {
            $label = trim((string) $check);

            if ($label !== '') {
                // **مغلقةٌ على صيغة XHTML** كما يُطبّعها `BodyPurifier`، فيمرّ
                // مخرَجُ هذا الصانع عليه بلا تبديل حرف — ويحرسه اختبار.
                $checks .= '<label><input type="checkbox" />'.self::text($label).'</label>';
            }
        }

        if ($quote === '' && $checks === '') {
            return '';
        }

        $title = trim((string) ($block['title'] ?? ''));
        $note = trim((string) ($block['note'] ?? ''));

        return '<section class="night">'
            .($title === '' ? '' : '<h2>'.self::text($title).'</h2>')
            .($quote === '' ? '' : '<p class="q">'.self::text($quote).'</p>')
            .($note === '' ? '' : '<p class="note">'.self::text($note).'</p>')
            .($checks === '' ? '' : '<div class="checks">'.$checks.'</div>')
            .'</section>';
    }

    /**
     * جدولُ بياناتٍ — T-46.
     *
     * **ووسومُ الجدول كانت مسموحةً في المنقّي من أوّل يوم** ولا كتلةَ تُخرجها
     * ولا قالبَ يُنسّقها: نصفُ ميزةٍ لا تعمل. وهذه تُتمّها.
     *
     * والرؤوسُ اختيارية: جدولٌ بلا رأسٍ يبقى جدولاً، وصفوفُه هي المقصود.
     *
     * @param  array<string, mixed>  $block
     */
    private static function table(array $block): string
    {
        $head = '';

        foreach (self::strings($block['columns'] ?? null) as $column) {
            $head .= '<th>'.self::text($column).'</th>';
        }

        $body = '';
        $width = 0;

        foreach (is_array($block['rows'] ?? null) ? $block['rows'] : [] as $row) {
            $cells = '';

            foreach (self::strings($row) as $cell) {
                $cells .= '<td>'.self::text($cell).'</td>';
                $width++;
            }

            if ($cells !== '') {
                $body .= '<tr>'.$cells.'</tr>';
            }
        }

        // رؤوسٌ بلا صفوفٍ ليست جدولاً، فتسقط الكتلة كلُّها لا نصفُها.
        if ($width === 0) {
            return '';
        }

        return '<div class="data">'
            .self::caption($block)
            .'<table>'
            .($head === '' ? '' : '<thead><tr>'.$head.'</tr></thead>')
            .'<tbody>'.$body.'</tbody></table>'
            .self::footnote($block)
            .'</div>';
    }

    /**
     * قائمةٌ مرقّمة أو منقّطة — T-46.
     *
     * **و`ul` و`ol` مسموحتان ومنسَّقتان في القوالب الثلاثة**، ولم تكن كتلةٌ
     * تُخرجهما. فأرخصُ فجوةٍ تُسدّ في هذه المهمّة.
     *
     * @param  array<string, mixed>  $block
     */
    private static function list(array $block): string
    {
        $items = '';

        foreach (self::strings($block['items'] ?? null) as $item) {
            $items .= '<li>'.self::text($item).'</li>';
        }

        if ($items === '') {
            return '';
        }

        // `ordered` يقول ترتيباً لا زينة: خطواتٌ مرتّبة أم عناصر لا ترتيب لها.
        $ordered = ($block['ordered'] ?? false) === true;
        $tag = $ordered ? 'ol' : 'ul';
        $class = $ordered ? 'numbered' : 'bullets';

        return '<div class="listing">'
            .self::caption($block)
            .'<'.$tag.' class="'.$class.'">'.$items.'</'.$tag.'>'
            .self::footnote($block)
            .'</div>';
    }

    /**
     * أرقامٌ بارزة — الإنفوغرافيك، T-46.
     *
     * **والأرقام تُحوّل إلى العربية الهندية** كما في سائر القالب، فالنموذج
     * قد يكتبها لاتينيةً ولا يُترك المخرَجُ مختلطاً.
     *
     * @param  array<string, mixed>  $block
     */
    private static function stats(array $block): string
    {
        $figures = '';

        foreach (self::items($block) as $item) {
            $value = trim((string) ($item['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));

            $figures .= '<div class="figure">'
                .'<span class="fig-num">'.self::text(Arabic::toArabicIndicDigits($value)).'</span>'
                .($label === '' ? '' : '<span class="fig-label">'.self::text($label).'</span>')
                .'</div>';
        }

        if ($figures === '') {
            return '';
        }

        return '<div class="figures-wrap">'
            .self::caption($block)
            .'<div class="figures">'.$figures.'</div>'
            .self::footnote($block)
            .'</div>';
    }

    /**
     * خطُّ زمنٍ رأسيّ — T-46.
     *
     * **رأسيٌّ لا أفقيّ** عمداً: الأفقيّ يفيض عن عرض الهاتف، وأكثرُ القرّاء
     * عليه. والرأسيُّ يُطبع على الورق بلا قصّ.
     *
     * @param  array<string, mixed>  $block
     */
    private static function timeline(array $block): string
    {
        $events = '';

        foreach (self::items($block) as $item) {
            $when = trim((string) ($item['label'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));

            if ($when === '' && $title === '' && $body === '') {
                continue;
            }

            $events .= '<div class="event">'
                .'<span class="when">'.self::text(Arabic::toArabicIndicDigits($when)).'</span>'
                .'<div class="what">'
                .($title === '' ? '' : '<h4>'.self::text($title).'</h4>')
                .($body === '' ? '' : '<p>'.self::text($body).'</p>')
                .'</div></div>';
        }

        if ($events === '') {
            return '';
        }

        return '<div class="timeline">'.self::caption($block).$events.self::footnote($block).'</div>';
    }

    /**
     * تفريعٌ من أصلٍ إلى فروع — بديلُ الخريطة الذهنية، T-46. وُسِّع T-72
     * ليقبل فروعاً تتفرّع هي نفسها، لا مستويين لا ثالث لهما فقط.
     *
     * **ومنضبطٌ عمداً، لا حرَّ الاتجاه.** والخريطةُ الحرّة مؤجَّلةٌ بقرارٍ
     * قائم: «تخطيطٌ تلقائيّ عربي RTL مشكلةٌ صعبة». وهذه تُنفّذ الشقّ الذي
     * يُبنى بشبكة CSS وحدها — **بلا SVG ولا مكتبةَ رسمٍ ولا `script`**،
     * فالمخرَج ملفٌّ واحدٌ قائمٌ بذاته يُطبع ويعمل بلا شبكة (§8).
     *
     * @param  array<string, mixed>  $block
     */
    private static function tree(array $block): string
    {
        $branches = '';

        foreach (self::items($block) as $item) {
            $branches .= self::branch($item, 1);
        }

        if ($branches === '') {
            return '';
        }

        $root = trim((string) ($block['root'] ?? ''));

        return '<div class="tree">'
            .self::caption($block)
            .($root === '' ? '' : '<div class="root">'.self::text($root).'</div>')
            .'<div class="branches">'.$branches.'</div>'
            .self::footnote($block)
            .'</div>';
    }

    /**
     * فرعٌ واحد، وقد يتفرّع هو نفسه إلى فروعٍ فرعية — T-72.
     *
     * **العمقُ محدودٌ هنا بحاجزٍ برمجيّ صريح** ({@see self::TREE_MAX_DEPTH})
     * لا تعليماتيٍّ وحده — كما كان الحدّ قبل T-72 حدَّ مستويين لا ثالث لهما
     * بنيةً لا نصيحة. وعنصرُ الفرع الفرعيّ يُعرَف بحمله مفتاح `title`، وما
     * سواه — نصّاً أو `{"text": "…"}` — ورقةٌ.
     *
     * @param  array<string, mixed>  $item
     */
    private static function branch(array $item, int $depth): string
    {
        $title = trim((string) ($item['title'] ?? ''));
        $children = is_array($item['items'] ?? null) ? $item['items'] : [];
        $inner = '';

        foreach ($children as $child) {
            if ($depth < self::TREE_MAX_DEPTH && is_array($child) && array_key_exists('title', $child)) {
                $inner .= self::branch($child, $depth + 1);

                continue;
            }

            $leaf = match (true) {
                is_array($child) => trim((string) ($child['text'] ?? '')),
                is_scalar($child) => trim((string) $child),
                default => '',
            };

            if ($leaf !== '') {
                $inner .= '<div class="leaf">'.self::text($leaf).'</div>';
            }
        }

        if ($title === '' && $inner === '') {
            return '';
        }

        return '<div class="branch">'
            .($title === '' ? '' : '<h4>'.self::text($title).'</h4>')
            .$inner.'</div>';
    }

    /**
     * هرمٌ بطبقات — T-72، لما يُرتَّب تفاضلاً من قمّةٍ ضيّقة إلى قاعدةٍ
     * عريضة: مراتب الإيمان، درجات الناس. **والعرضُ يتدرّج بترتيب العنصر في
     * القائمة لا بصنفٍ لكلّ طبقة** — `:nth-child` في ورقة الأنماط وحدها،
     * كما تدرّج عرضُ الأعمدة في `.branches` بلا صنفٍ يحمل رقماً.
     *
     * والترتيبُ من القمّة إلى القاعدة: أوّل عنصرٍ أعلى الهرم وأضيقُه.
     *
     * @param  array<string, mixed>  $block
     */
    private static function pyramid(array $block): string
    {
        $tiers = '';

        foreach (self::items($block) as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $body = trim((string) ($item['body'] ?? ''));

            if ($title === '' && $body === '') {
                continue;
            }

            $tiers .= '<div class="tier">'
                .($title === '' ? '' : '<h4>'.self::text($title).'</h4>')
                .($body === '' ? '' : '<p>'.self::text($body).'</p>')
                .'</div>';
        }

        if ($tiers === '') {
            return '';
        }

        return '<div class="pyramid">'.self::caption($block).'<div class="tiers">'.$tiers.'</div>'
            .self::footnote($block).'</div>';
    }

    /**
     * عجلةُ مقياسٍ بين قطبين — T-72. لما يُقاس درجةً على تدرّجٍ متّصل لا
     * فئاتٍ منفصلة: شدّة غفلةٍ إلى يقظة، ضعفٍ إلى قوّة.
     *
     * **ولا `style=` قطّ** (§8) — فالحلقة لا تُلوَّن بنسبةٍ صريحة، بل
     * تُقرَّب القراءةُ إلى أقرب عشرة وتُختار صنفاً `fill-N` من قائمةٍ
     * مغلقةٍ إحدى عشرة قيمة (٠ إلى ١٠٠)، ترسم كلٌّ منها تدرّجاً مخروطياً
     * ثابتاً في ورقة الأنماط — تماماً كما يُختار اسمُ الأيقونة لا يُكتب
     * رسمُها.
     *
     * وقطباها `bad` (الأدنى) و`good` (الأعلى) — بنية `paths` نفسها، فلا
     * مفردة جديدة تُضاف للمخطّط سوى `value`.
     *
     * @param  array<string, mixed>  $block
     */
    private static function gauge(array $block): string
    {
        $raw = $block['value'] ?? null;

        if (! is_int($raw) && ! is_float($raw)) {
            return '';
        }

        $value = (int) (round(max(0.0, min(100.0, (float) $raw)) / 10) * 10);

        $poles = self::pole($block['bad'] ?? null).self::pole($block['good'] ?? null);

        return '<div class="gauge">'.self::caption($block)
            .'<div class="ring fill-'.$value.'"><span class="reading">'
            .self::text(Arabic::toArabicIndicDigits((string) $value)).'</span></div>'
            .($poles === '' ? '' : '<div class="poles">'.$poles.'</div>')
            .self::footnote($block).'</div>';
    }

    /** قطبٌ واحد من قطبَي العجلة — بنيةُ `path` نفسها، عنوانٌ فقط. */
    private static function pole(mixed $pole): string
    {
        if (! is_array($pole)) {
            return '';
        }

        $label = trim((string) ($pole['title'] ?? ''));

        return $label === '' ? '' : '<div class="pole">'.self::text($label).'</div>';
    }

    /**
     * تعريف مصطلحٍ لغةً واصطلاحاً — T-76، من مراجعة تصميمٍ خارجية.
     *
     * **أحد المعنيين لازمٌ على الأقلّ.** مصطلحٌ بلا تعريفٍ ليس تعريفاً،
     * وجذرٌ بلا مصطلح لا معنى له، فالحاجز على `title` معاً مع أحد الحقلين.
     *
     * @param  array<string, mixed>  $block
     */
    private static function termGloss(array $block): string
    {
        $term = trim((string) ($block['title'] ?? ''));
        $linguistic = trim((string) ($block['linguistic'] ?? ''));
        $technical = trim((string) ($block['technical'] ?? ''));

        if ($term === '' || ($linguistic === '' && $technical === '')) {
            return '';
        }

        $root = trim((string) ($block['root'] ?? ''));

        $rows = '';

        if ($linguistic !== '') {
            // وسمٌ بلسان المخرَج — T-87، وكان «لغةً» في كلّ لغة.
            $rows .= '<span class="gloss-label">'.self::text(PageStrings::of('gloss_linguistic', self::$locale)).'</span>'
                .'<p class="gloss-text">'.self::text($linguistic).'</p>';
        }

        if ($technical !== '') {
            $rows .= '<span class="gloss-label">'.self::text(PageStrings::of('gloss_technical', self::$locale)).'</span>'
                .'<p class="gloss-text">'.self::text($technical).'</p>';
        }

        return '<div class="gloss"><div class="gloss-head"><span class="gloss-term">'.self::text($term).'</span>'
            .($root === '' ? '' : '<span class="gloss-root">'.self::text($root).'</span>').'</div>'
            .'<div class="gloss-body">'.$rows.'</div>'
            .self::footnote($block).'</div>';
    }

    /**
     * سؤالٌ وجوابه — T-76. **بلا عنوانٍ عمداً**: تصفّه المراجعة الخارجية
     * "كي لا يتكرّر عنوان القسم" — قسمها غالباً عنوانه هو السؤال العامّ.
     *
     * @param  array<string, mixed>  $block
     */
    private static function qaPair(array $block): string
    {
        $items = '';

        foreach (self::items($block) as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));

            if ($q === '' && $a === '') {
                continue;
            }

            $items .= '<div class="qa-item">'
                .($q === '' ? '' : '<div class="qa-q">'.self::text($q).'</div>')
                .($a === '' ? '' : '<div class="qa-a">'.self::text($a).'</div>')
                .'</div>';
        }

        return $items === '' ? '' : '<div class="qa">'.$items.'</div>';
    }

    /**
     * فائدة أو تنبيه أو استطراد جانبي — T-76. و`tone` يحمل درجته، بنفس
     * الحقل الذي تحمل به `evidence` نبرتها — حقلٌ عامّ لا مخطّطٌ فرعيّ.
     *
     * @param  array<string, mixed>  $block
     */
    private static function calloutNote(array $block): string
    {
        $text = trim((string) ($block['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $kind = match ((string) ($block['tone'] ?? '')) {
            'benefit' => 'cn-benefit',
            'warning' => 'cn-warning',
            default => 'cn-subtle',
        };

        $title = trim((string) ($block['title'] ?? ''));

        return '<div class="cnote '.$kind.'"><span class="cn-mark"></span><div>'
            .($title === '' ? '' : '<h4 class="cn-title">'.self::text($title).'</h4>')
            .'<p class="cn-text">'.self::text($text).'</p></div></div>';
    }

    /**
     * ما يُظنّ خطأً مقابل تصحيحه — T-76. **كلاهما لازمان معاً**: تصحيحٌ
     * بلا بيان الخطأ المقصود غامض، وخطأٌ بلا تصحيح كتلةٌ ناقصة الغرض.
     *
     * @param  array<string, mixed>  $block
     */
    private static function misconceptionFix(array $block): string
    {
        $claim = trim((string) ($block['claim'] ?? ''));
        $correction = trim((string) ($block['correction'] ?? ''));

        if ($claim === '' || $correction === '') {
            return '';
        }

        $evidence = trim((string) ($block['note'] ?? ''));

        return '<div class="mfix">'
            // «يُظنّ» و«والصواب» بلسان المخرَج — T-87، بلاغُ مالك المنتج.
            .'<div class="mfix-claim"><span class="mfix-tag">'.self::text(PageStrings::of('mfix_claim', self::$locale)).'</span>'
            .'<p>'.self::text($claim).'</p></div>'
            .'<div class="mfix-fix"><span class="mfix-tag">'.self::text(PageStrings::of('mfix_fix', self::$locale)).'</span>'
            .'<p>'.self::text($correction).'</p></div>'
            .($evidence === '' ? '' : '<p class="mfix-evidence">'.self::text($evidence).'</p>')
            .'</div>';
    }

    /**
     * أقصى عمقِ تفريعٍ تقبله `tree` — T-72. وتعليماتُ المرحلة ٥ تحصره
     * بثلاثة أيضاً؛ هذا حاجزُها البرمجيّ لا تعويضُها.
     */
    private const TREE_MAX_DEPTH = 3;

    /** عنوانُ الكتلة — واحدٌ لخمسِ كتلِ T-46، فلا يتفرّق اصطلاحُه بينها. */
    private static function caption(array $block): string
    {
        $title = trim((string) ($block['title'] ?? ''));

        return $title === '' ? '' : '<h3>'.self::text($title).'</h3>';
    }

    /** ملاحظةٌ تحت الكتلة — `p.note`، وهي في قائمة السماح منذ T-43. */
    private static function footnote(array $block): string
    {
        $note = trim((string) ($block['note'] ?? ''));

        return $note === '' ? '' : '<p class="note">'.self::text($note).'</p>';
    }

    /**
     * قائمةُ نصوصٍ من مدخلٍ لا يُوثق به — T-46.
     *
     * **والنموذج قد يُخرج كائناً حيث انتُظر نصّ**، فتُقبل `{"text": "…"}`
     * ويُسقط ما سواها. وهذا أهونُ من إسقاط الكتلة كلِّها لخليّةٍ واحدة.
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['text'] ?? '';
            }

            if (is_scalar($item) && trim((string) $item) !== '') {
                $out[] = trim((string) $item);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array<string, mixed>>
     */
    private static function items(array $block): array
    {
        $items = is_array($block['items'] ?? null) ? $block['items'] : [];

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * **كلّ نصٍّ من النموذج يُهرَّب** — §12.
     *
     * فالمتن يمرّ على نصّ محاضرةٍ قد يحمل حقناً، ووسمٌ يكتبه النموذج داخل
     * حقلٍ نصّيّ يجب أن يظهر حرفاً لا أن يُنفَّذ.
     */
    private static function text(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
