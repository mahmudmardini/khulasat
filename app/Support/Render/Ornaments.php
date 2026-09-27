<?php

declare(strict_types=1);

namespace App\Support\Render;

/**
 * The skill's ornaments, filled in after purification — T-54.
 *
 * **ورقةُ المهارة تحجز مقاساتٍ لا يملؤها أحد**: `.sec-head .mark` مربّعٌ
 * بـ٣٤ بكسل يخرج `<span>` فارغاً في صدر كلّ قسم، و`.imam .ico` و
 * `.pillar-title .ico` و`.quad .ico` مثلُه، و`.arrow` بين عُقد المسارين
 * لا يرسمه أحد. فتظهر فجواتٌ بيضاء في موضع الزينة.
 *
 * ★ **والحقنُ بعد التنقية عمداً.** جُرّب قبلها فسقط: `BodyPurifier` قائمتُه
 * مغلقة، و`<svg>` ليس فيها — **فيُحذف الوسمُ وما فيه بلا خطأ ولا سجلّ**،
 * وهو عين العطب الصامت الذي بُني المنقّي ليمنعه. وتوسيعُ القائمة لتقبل
 * SVG قرارُ §12 لا قرارُ عرض: يفتح سطحاً لكلّ ما يمرّ بالمنقّي.
 *
 * **والرسمُ هنا ثابتٌ في الكود لا يأتي من نموذجٍ ولا من مستخدم**، فحقنُه
 * بعد الحارس لا يُضعفه: الحارس يحرس ما يُكتب من الخارج، وهذا يُكتب هنا.
 *
 * ومفرداتُه من `reference-summary.html` نفسها، بمقاساتها وألوانها.
 */
final class Ornaments
{
    /** نجمةُ التاج نفسها، مصغَّرة — تصدُّر القسم. */
    private const MARK = '<svg class="mark" viewBox="0 0 40 40" fill="none" stroke="#A87C33" '
        .'stroke-width="1.5" aria-hidden="true"><path d="M20 4 25 15 36 20 25 25 20 36 15 25 4 20 15 15Z"/>'
        .'<circle cx="20" cy="20" r="4"/></svg>';

    /** ميزانٌ — بطاقةُ قولٍ أو وجهٍ من وجوه المعنى. */
    private const CARD = '<svg class="ico" viewBox="0 0 44 44" fill="none" stroke="#1B4D3E" '
        .'stroke-width="1.5" aria-hidden="true"><path d="M22 7v27M9 34h26M6 13h32M12 13l-5 9a5.4 5.4 0 0 0 10 0z'
        .'M32 13l-5 9a5.4 5.4 0 0 0 10 0z"/></svg>';

    /** مصباحٌ على عمود — ركنٌ تُبنى عليه خطوات. */
    private const PILLAR = '<svg class="ico" viewBox="0 0 44 44" fill="none" stroke="#A87C33" '
        .'stroke-width="1.6" aria-hidden="true"><path d="M22 6v6M22 12a9 9 0 0 1 9 9c0 5-4 7-4 11H17c0-4-4-6-4-11'
        .'a9 9 0 0 1 9-9zM17 38h10"/></svg>';

    /** دوائرُ متراكزة — مجالٌ يُطبَّق فيه المعنى. */
    private const DOMAIN = '<svg class="ico" viewBox="0 0 44 44" fill="none" stroke="#1B4D3E" '
        .'stroke-width="1.5" aria-hidden="true"><circle cx="22" cy="22" r="14"/><circle cx="22" cy="22" r="8"/>'
        .'<circle cx="22" cy="22" r="2.4" fill="#1B4D3E" stroke="none"/></svg>';

    /** سهمٌ نازل بين عقدتين — ولونُه لون المسار. */
    private const ARROW_GOOD = '<svg class="arrow" viewBox="0 0 14 22" fill="none" stroke="#1B4D3E" '
        .'stroke-width="1.6" aria-hidden="true"><path d="M7 0v18M2 13l5 6 5-6"/></svg>';

    private const ARROW_BAD = '<svg class="arrow" viewBox="0 0 14 22" fill="none" stroke="#8E3B2E" '
        .'stroke-width="1.6" aria-hidden="true"><path d="M7 0v18M2 13l5 6 5-6"/></svg>';

    /**
     * مفرداتُ الأيقونات — T-61، **مغلقةٌ كأصناف الكتل**.
     *
     * يختار النموذجُ منها **بالاسم لا بالرسم**، نظيرَ ما فعلت T-43 بالأصناف.
     * **ولا يُخرج SVG البتّة**: اسمٌ خارج القائمة يسقط إلى زخرفة نوعه، ولا
     * يُبنى وسمٌ من نصٍّ يكتبه نموذج.
     *
     * ورسومُها من `reference-summary.html` نفسه — بمقاساتها ومسالكها، فلا
     * يختلف لسانُ الصفحة البصريّ عمّا رضيه مالك المنتج.
     */
    private const PATHS = [
        // كنزٌ مقفل — العنوان الجامع، وما يُطلب ولا يُوجد.
        'treasure' => '<rect x="6" y="16" width="28" height="18" rx="2"/><path d="M6 22h28M20 16v18M13 16V12a7 7 0 0 1 14 0v4"/><circle cx="20" cy="22" r="2.6" fill="currentColor" stroke="none"/>',
        // كتابٌ مفتوح — التفسير والنقل عن الأئمّة.
        'book' => '<path d="M6 10h11a4 4 0 0 1 3 1.6A4 4 0 0 1 23 10h11v20H23a4 4 0 0 0-3 1.4A4 4 0 0 0 17 30H6z"/><path d="M20 11.6V31"/>',
        // نبضٌ مضطرب — الداء والقلق والهلع.
        'pulse' => '<path d="M4 20h7l3-8 5 17 4-13 3 4h10"/>',
        // ميزان — المقارنة والعدل وتفاضل الأعمال.
        'scales' => '<path d="M20 7v27M9 34h22M6 13h28M12 13l-5 9a5.4 5.4 0 0 0 10 0zM28 13l-5 9a5.4 5.4 0 0 0 10 0z"/>',
        // أركانٌ قائمة — الأسس والمجالات.
        'pillars' => '<path d="M5 34h30M8 34V16M16 34V16M24 34V16M32 34V16M4 16h32L20 6z"/>',
        // مسارٌ متفرّع — الطريقان والمآل.
        'fork' => '<path d="M20 5v9M20 14 8 24v11M20 14l12 10v11"/><circle cx="20" cy="5" r="2.5"/><circle cx="8" cy="35" r="2.5"/><circle cx="32" cy="35" r="2.5"/>',
        // قلبٌ مشرق — السكينة وحياة القلب.
        'heart' => '<path d="M20 34S7 26 7 17a6.4 6.4 0 0 1 13-3.4A6.4 6.4 0 0 1 33 17c0 9-13 17-13 17z"/><path d="M20 7V3M29 10l2.6-2.6M11 10 8.4 7.4"/>',
        // درعٌ بعلامة — الثبات والحفظ.
        'shield' => '<path d="M20 4l12 4.4v10.6c0 7.4-4.8 13-12 14.8-7.2-1.8-12-7.4-12-14.8V8.4z"/><path d="M15 18.5l4 4L27 14"/>',
        // نبتةٌ نامية — الدعوة والتربية والثمرة.
        'plant' => '<path d="M20 34V18M20 18c0-5.4 4.4-9 10-9 0 6.4-3.6 11-10 11zM20 23.6c0-4.4-3.6-8-9-8 0 5.4 3.6 10 9 10z"/><path d="M13 34h14"/>',
        // دوائرُ متراكزة — المقصد والغاية والنجاح.
        'target' => '<circle cx="20" cy="20" r="12.6"/><circle cx="20" cy="20" r="7.2"/><circle cx="20" cy="20" r="2.2" fill="currentColor" stroke="none"/>',
        // مصباح — الهداية والعلم.
        'lamp' => '<path d="M20 5v5M20 10a8 8 0 0 1 8 8c0 4.4-3.6 6.2-3.6 9.8H15.6c0-3.6-3.6-5.4-3.6-9.8a8 8 0 0 1 8-8zM15.6 32.8h8.8"/>',
    ];

    /** الأسماءُ كما تُكتب في التعليمات — يحرسها اختبار. */
    public static function names(): array
    {
        return array_keys(self::PATHS);
    }

    /**
     * المواضعُ الفارغة التي يكتبها {@see BodyBlocks} ← رسمُ كلٍّ منها.
     *
     * **ومغلقةٌ كأصناف الكتل**: موضعٌ لا اسم له لا يُملأ، ولا يُبنى وسمٌ
     * من نصٍّ متغيّر.
     */
    private const FILLERS = [
        '<span class="mark"></span>' => self::MARK,
        '<span class="ico card"></span>' => self::CARD,
        '<span class="ico pillar"></span>' => self::PILLAR,
        '<span class="ico domain"></span>' => self::DOMAIN,
        '<span class="arrow good"></span>' => self::ARROW_GOOD,
        '<span class="arrow bad"></span>' => self::ARROW_BAD,
    ];

    private function __construct() {}

    /** يُنادى **بعد** `BodyPurifier` لا قبله. */
    public static function apply(string $html): string
    {
        $html = self::named($html);

        return str_replace(array_keys(self::FILLERS), array_values(self::FILLERS), $html);
    }

    /**
     * الأيقونةُ المختارةُ بالاسم — T-61.
     *
     * والموضعُ يُكتب `<span class="mark m-scales"></span>`، فيُبدَّل رسمُه.
     * **واسمٌ خارج القائمة لا يُبدَّل**، فيسقط إلى زخرفة نوعه في
     * {@see self::FILLERS} — ولا فجوةَ بيضاء ولا وسمٌ مخترَع.
     */
    private static function named(string $html): string
    {
        $search = [];
        $replace = [];

        foreach (self::PATHS as $name => $path) {
            foreach (['mark' => ['34', '40'], 'ico' => ['42', '44']] as $slot => [$_, $box]) {
                $search[] = '<span class="'.$slot.' m-'.$name.'"></span>';
                $replace[] = '<svg class="'.$slot.'" viewBox="0 0 '.$box.' '.$box.'" fill="none" '
                    .'stroke="'.($slot === 'mark' ? '#A87C33' : '#1B4D3E').'" stroke-width="1.5" '
                    .'aria-hidden="true">'.self::scaled($path, $box).'</svg>';
            }
        }

        return str_replace($search, $replace, $html);
    }

    /**
     * الرسمُ مرسومٌ على شبكة ٤٠، و`.ico` صندوقُها ٤٤ — فتُوسَّع الرؤية لا
     * المسلك. **ولا يُعاد رسمُ المسالك بحساب**: تحويلُ إحداثيّاتٍ بالضرب
     * يُنتج أرقاماً طويلة ويُفسد التناسب، و`viewBox` أوسعُ تُبقي الرسم
     * كما هو وتُعطيه هامشاً.
     */
    private static function scaled(string $path, string $box): string
    {
        return $box === '40' ? $path : '<g transform="translate(2 2)">'.$path.'</g>';
    }
}
