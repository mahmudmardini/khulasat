<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Render\BodyPurifier;

/**
 * The look of the published page — قوالب المخرَج، T-45.
 *
 * **ورقةُ أنماطٍ لا بنيةٌ ثانية.** القوالب الثلاثة تتشارك
 * `summary/layout.blade.php` نفسه وجزئيّاتِه، ولا يتبدّل بينها إلّا ملفّ
 * CSS. وعلّةُ ذلك أنّ أصناف المتن مقصورةٌ على قائمة سماحِ
 * {@see BodyPurifier}، **فقالبٌ ببنيةٍ خاصّة يعني
 * كتلةً لا تُرسم** يوم يُخرج النموذج صنفاً لم يُنسّقه ذلك القالب. وبنيةٌ
 * واحدة تعني أنّ أيّ قالبٍ جديد يدعم الكتل كلَّها بالضرورة.
 *
 * **ولا يُمسّ `classic`** — هو قالب `khulasah.skill` حرفاً بحرف، وكلّ
 * تفصيلٍ فيه حُلّت به مشكلةٌ فعلية (CLAUDE.md §2 القاعدة الأولى). والجديدان
 * يُضافان إلى جانبه لا فوقه.
 *
 * والألوان في الثلاثة من متغيّرات `Palette` نفسها، فلوحةُ الجهة تعمل في
 * أيّها اختارت بلا إعدادٍ ثانٍ.
 */
enum SummaryTemplate: string
{
    /** قالب المهارة: ورقٌ مزخرف وذهبٌ وخطّ أميري — للمجالس الشرعية. */
    case Classic = 'classic';

    /** أبيض ومسافاتٌ واسعة وبطاقاتٌ خفيفة — للدروس التعليمية والعامّة. */
    case Modern = 'modern';

    /** تحريريّ: خطٌّ مشبَك وخطوطٌ رفيعة وأقسامٌ مرقّمة — للطويل والمطبوع. */
    case Journal = 'journal';

    /*
     * ★ **ثلاثةٌ ببنيةٍ خاصّة — T-49.** وما قبلها ثلاثةٌ تتشارك
     * `summary/layout.blade.php` نفسه ولا يتبدّل بينها إلّا CSS، فالسرلوح
     * المذهّب بنجمته يظهر في «العصري» كما هو ولا يليق به. وهذه تملك صفحتها:
     * سرلوحَها وإيقاعَ أقسامها وتخطيطَها وكثافتَها.
     *
     * **والمحتوى كامل في كلٍّ منها** — قرار مالك المنتج، ٩ أيلول ٢٠٢٦.
     * القالبُ يعرض ولا يحذف: قالبٌ يُسقط كتلةً أنتجها الخطّ يتّخذ قرارَ
     * محتوًى لا قرارَ عرض، وذلك لصاحب المحتوى.
     */

    /** صندوق أهدافٍ وأقسامٌ مرقّمة ومهامُّ مبرَزة — للدورة والدرس المنهجيّ. */
    case Lesson = 'lesson';

    /** الأرقام والنقاط أوّلاً، نثرٌ قليل وبلا زخرفة — تُقرأ في دقيقة وتُشارَك. */
    case Brief = 'brief';

    /** الشواهد في الصدر وهوامشُ جانبية وجداولُ أولاً — للتوثيق والمراجعة. */
    case Research = 'research';

    public static function default(): self
    {
        return self::Classic;
    }

    /** ما وصل من `brand_kit` قد يكون قديماً أو محرَّفاً، فيسقط إلى الافتراضي. */
    public static function parse(mixed $value): self
    {
        return is_string($value)
            ? self::tryFrom($value) ?? self::default()
            : self::default();
    }

    /**
     * ورقة الأنماط وحدها — والبنية واحدة للجميع.
     *
     * و`classic` يشير إلى موضعه الأصلي لا إلى `themes/`، فالملفّ منقولٌ من
     * المهارة ولا يُنقل ولا يُعاد ترتيبه.
     */
    public function styleView(): string
    {
        return match ($this) {
            self::Classic => 'summary.partials._style',
            default => 'summary.themes.'.$this->value,
        };
    }

    /**
     * صفحةُ القالب كاملةً — T-49، وهنا يقع الاختلاف البنيويّ.
     *
     * **وقبل هذه كانت البنية واحدة للجميع**: سبعةُ `@include` ثابتة في
     * `summary/layout.blade.php`، ولا يتبدّل إلّا CSS. فكانت القوالبُ ثلاثةَ
     * ألوانِ طلاءٍ على مبنًى واحد، والسرلوحُ المذهّب يظهر في «العصري» بنجمته
     * ولا يليق به.
     *
     * **والثلاثةُ الأولى تبقى على المشترك بلا تبديل حرف** — لا انحدارَ في
     * منشورٍ قائم، و`classic` يبقى قالب المهارة بحاله (§2 القاعدة الأولى).
     */
    public function layoutView(): string
    {
        return match ($this) {
            self::Classic, self::Modern, self::Journal => 'summary.layout',
            default => 'summary.templates.'.$this->value,
        };
    }

    /**
     * مفردات كتل T-46 — ورقةٌ ثانية تُدرج بعد ورقة القالب.
     *
     * **وهي منفصلةٌ عن `styleView()` لسببٍ واحد:** `classic` يشير إلى
     * `partials/_style` وهو قالب المهارة، **ولا يُعدَّل CSS فيه** (§2 القاعدة
     * الأولى) ولا يُصرَف عنه (يحرسه اختبار). فلو أُضيفت المفردات الجديدة إليه
     * لانكسر أحدُ القيدين. وبورقةٍ ثانيةٍ يسلم الاثنان: ملفّ المهارة بحاله،
     * والمفرداتُ إلى جانبه لا فوقه.
     */
    public function blocksView(): string
    {
        return 'summary.blocks.'.$this->value;
    }

    public function label(): string
    {
        return (string) __('templates.'.$this->value.'.label');
    }

    /** ما يُميّزه، ولمن يصلح — يُعرض تحت الاسم في شاشة الاختيار. */
    public function description(): string
    {
        return (string) __('templates.'.$this->value.'.description');
    }

    /** @return list<self> */
    public static function all(): array
    {
        return self::cases();
    }
}
