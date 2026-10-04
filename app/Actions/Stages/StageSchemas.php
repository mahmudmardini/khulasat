<?php

declare(strict_types=1);

namespace App\Actions\Stages;

use App\Enums\SlideKind;
use App\Enums\Stage;
use App\Support\I18n\BodyStrings;
use App\Support\Quiz\QuizGuard;
use App\Support\Render\CarouselDesign;

/**
 * The output shape each stage must match — المواصفة §6.
 *
 * **مشتقّة من §6 لا مخترعة**: أشكالُ JSON هناك بنصّها، وهذه صياغتها مخطّطاً
 * يُتحقّق منه. وهي الحاجز الأهمّ أمام حقن التعليمات (§12): نموذجٌ انصاع
 * لأمرٍ في التفريغ يُخرج شكلاً آخر فيُمسَك هنا.
 *
 * وتُفرض المفاتيح **اللازمة** وحدها. المفاتيح الاختيارية تُوصف نوعاً ولا
 * تُلزَم: درسٌ بلا جدول مقارنة درسٌ صحيح، ورفضُه يُوقف مهمّةً بلا سبب.
 */
final class StageSchemas
{
    private function __construct() {}

    /** @return array<string, mixed>|null و`null` لمرحلةٍ خرجها نصّ أو HTML. */
    public static function for(Stage $stage): ?array
    {
        return match ($stage) {
            Stage::Cleaning => null,
            Stage::Writing => self::body(),
            Stage::ExtractingStructure => self::structure(),
            Stage::ExtractingEvidence => self::evidence(),
            Stage::OutputMetadata => self::outputMetadata(),
            Stage::Carousel => self::carousel(),
            Stage::Quiz => self::quiz(),
            Stage::LectureDetails => self::lectureDetails(),
            Stage::Translating => self::translations(),
            Stage::CarouselDesign => self::carouselDesigns(),
        };
    }

    /**
     * متن الملخّص كتلاً — T-43، وPROMPT-PACK المرحلة ٥.
     *
     * **كانت هذه المرحلة وحدها بلا مخطّط**، تُخرج HTML بأصنافٍ يكتبها
     * النموذج بيده. وحارسُها الوحيد `BodyPurifier`، **وهو يُسقط ولا يُخطئ**:
     * صنفٌ أخطأه النموذج يسقط فتخرج الكتلة بلا تنسيق، بلا خطأٍ ولا إعادة.
     * وقد ثبت ذلك عملياً حين وُجدت `article` ساقطةً من قائمة السماح (T-45).
     *
     * فصارت تُخرج كتلاً مسمّاة، **ويكتب العارضُ أصنافَها لا النموذج**. وأنواع
     * الكتل هي عائلات المرحلة ٥ نفسها لا زيادة عليها — والقائمة **مغلقة**:
     * نوعٌ خارجها لا عارض له، فيُرفض عند المخطّط بدل أن يسقط صامتاً.
     *
     * @return array<string, mixed>
     */
    private static function body(): array
    {
        return [
            'type' => 'object',
            'required' => ['sections'],
            'properties' => [
                'sections' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['blocks'],
                        'properties' => [
                            'heading' => ['type' => 'string', 'nullable' => true],
                            // اسمُ أيقونة القسم — T-61، من قائمةٍ مغلقة
                            // يُصفّيها العارضُ ثمّ المنقّي.
                            'icon' => ['type' => 'string', 'nullable' => true],
                            'blocks' => ['type' => 'array', 'items' => self::block()],
                        ],
                    ],
                ],
                'closing' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    /**
     * الكتلة الواحدة — و`type` قائمةٌ مغلقة بعائلات المرحلة ٥.
     *
     * **ولا حقل مطلوباً غير `type`.** فالكتل تختلف حقولاً اختلافاً بيّناً،
     * ومخطّطٌ فرعيّ لكلّ نوع يحتاج `oneOf` وليس في {@see JsonSchema} — وهي
     * «مجموعة فرعية مقصودة» لا تنفيذٌ كامل. فيُفحص النوع هنا، **ويحرس
     * العارضُ ما وراءه**: كتلةٌ ناقصة الحقول تُرسم بما فيها ولا تكسر صفحة.
     *
     * @return array<string, mixed>
     */
    private static function block(): array
    {
        return [
            'type' => 'object',
            'required' => ['type'],
            'properties' => [
                /*
                 * ★ **خمسٌ أُضفن في T-46** — `table` و`list` و`stats`
                 * و`timeline` و`tree`. **واثنتان في T-72** — `pyramid`
                 * و`gauge`، وتوسيعُ `tree` نفسها لتقبل تفريعاً أعمق من
                 * مستويين (البنية لا تُقيَّد هنا، انظر `BodyBlocks::branch`).
                 * **وأربعٌ في T-76** — `term_gloss` و`qa_pair` و
                 * `callout_note` و`misconception_fix`، من مراجعة تصميمٍ
                 * خارجية بإذن مالك المنتج. والقائمة **مغلقة**: ما ليس فيها
                 * لا يُخرجه النموذج أصلاً، فتوسيعُ المفردات يبدأ من هنا لا
                 * من ورقة الأنماط. **وقالبٌ مهما بلغ لا يُظهر ما لا يُولَد.**
                 */
                'type' => ['type' => 'string', 'enum' => [
                    'lead', 'paragraph', 'evidence', 'cards',
                    'comparison', 'domains', 'pillar', 'paths', 'night',
                    'table', 'list', 'stats', 'timeline', 'tree',
                    'pyramid', 'gauge',
                    'term_gloss', 'qa_pair', 'callout_note', 'misconception_fix',
                ]],
                'text' => ['type' => 'string', 'nullable' => true],
                'source' => ['type' => 'string', 'nullable' => true],
                // نبرة الشاهد: عاديّ · `warn` للضعيف المبيَّن · `dark` للمبرَز.
                'tone' => ['type' => 'string', 'nullable' => true],
                'muted' => ['type' => 'boolean', 'nullable' => true],
                'title' => ['type' => 'string', 'nullable' => true],
                'note' => ['type' => 'string', 'nullable' => true],
                'items' => ['type' => 'array', 'nullable' => true],
                'checks' => ['type' => 'array', 'nullable' => true],
                'good' => ['type' => 'object', 'nullable' => true],
                'bad' => ['type' => 'object', 'nullable' => true],
                // رؤوس الجدول وصفوفه — T-46. والصفّ قائمةُ خلايا نصّية.
                'columns' => ['type' => 'array', 'nullable' => true],
                'rows' => ['type' => 'array', 'nullable' => true],
                // قائمةٌ مرقّمة أم منقّطة — T-46. والافتراض منقّطة.
                'ordered' => ['type' => 'boolean', 'nullable' => true],
                // جذر الشجرة — T-46. وفروعُها في `items`، وقد تتفرّع هي
                // نفسها إلى فروعٍ فرعية — T-72، انظر `BodyBlocks::branch`.
                'root' => ['type' => 'string', 'nullable' => true],
                // اسمُ أيقونة الركن — T-61. وأيقوناتُ عناصر `items` فيها.
                'icon' => ['type' => 'string', 'nullable' => true],
                // قراءةُ العجلة، من ٠ إلى ١٠٠ — T-72. وقطباها `good`/`bad`.
                'value' => ['type' => 'number', 'nullable' => true],
                // معنى المصطلح لغةً واصطلاحاً — T-76. و`title` اسمُ
                // المصطلح، و`root` جذرُه — الحقلان مشتركان مع `tree` بمعنًى
                // آخر، وهذا مقصودٌ: حقولٌ عامّة لا مخطّطٌ فرعيّ لكلّ نوع.
                'linguistic' => ['type' => 'string', 'nullable' => true],
                'technical' => ['type' => 'string', 'nullable' => true],
                // ما يُظنّ خطأً وتصحيحُه — T-76. و`note` دليلٌ داعم لهما.
                'claim' => ['type' => 'string', 'nullable' => true],
                'correction' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    /**
     * جدولُ ترجمةٍ مسطّح — T-38.
     *
     * **ولا شجرةَ هنا عمداً.** النموذج يردّ `{مفتاح، نصّ}` ويُركّب
     * {@see BodyStrings} الشجرةَ بنفسه. وعلّةُ ذلك
     * علّةُ T-43: نموذجٌ يُعيد بناء JSON متداخلٍ يُخطئ بنيتَه أحياناً
     * **فتضيع كتلةٌ صامتةً**، وقائمةٌ مسطّحة لا بنيةَ فيها تُخطأ.
     *
     * @return array<string, mixed>
     */
    private static function translations(): array
    {
        return [
            'type' => 'object',
            'required' => ['translations'],
            'properties' => [
                'translations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['key', 'text'],
                        'properties' => [
                            'key' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> المواصفة §6-2. */
    private static function structure(): array
    {
        // الطريقُ الواحد من المسارين — T-71. وشكلُه شكلُ طرفِ كتلة `paths`
        // في المرحلة ٥ نفسه، فينقله الكاتبُ بلا تحويل.
        $path = [
            'type' => 'object',
            'nullable' => true,
            'properties' => [
                'title' => ['type' => 'string', 'nullable' => true],
                'nodes' => ['type' => 'array', 'items' => ['type' => 'string']],
                'final' => ['type' => 'string', 'nullable' => true],
            ],
        ];

        return [
            'type' => 'object',
            'required' => ['title_ar', 'core_concept', 'axes'],
            'properties' => [
                'title_ar' => ['type' => 'string'],
                'subtitle_ar' => ['type' => 'string', 'nullable' => true],
                'key_ayah' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        // النموذج قد يعرف الآية مفهوماً لا رقماً — فيصدق حين
                        // يقول ذلك بـ`null` بدل أن يخترع رقماً أو يُسقط
                        // الآية كلّها. اكتُشف على استدعاء حقيقي، 8 أيلول 2026.
                        'text' => ['type' => 'string', 'nullable' => true],
                        'surah' => ['type' => 'string', 'nullable' => true],
                        'ayah_number' => ['type' => 'integer', 'nullable' => true],
                    ],
                ],
                'core_concept' => ['type' => 'string'],
                'diagnosis' => ['type' => 'string', 'nullable' => true],
                'axes' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['name'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'summary' => ['type' => 'string', 'nullable' => true],
                            'steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
                'comparison' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'left_label' => ['type' => 'string'],
                        'right_label' => ['type' => 'string'],
                        'rows' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                ],
                /*
                 * المساران — T-71: بدايةٌ واحدة تنتهي إلى مصيرين. **اختياريٌّ
                 * كالمقارنة**، فدرسٌ بلا طريقين درسٌ صحيح، ورفضُه يُوقف مهمّةً
                 * بلا سبب.
                 */
                'paths' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => ['good' => $path, 'bad' => $path],
                ],
                'application' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'question' => ['type' => 'string'],
                        'checklist' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
                'closing_line' => ['type' => 'string', 'nullable' => true],
            ],
        ];
    }

    /** @return array<string, mixed> المواصفة §6-3. */
    private static function evidence(): array
    {
        return [
            'type' => 'object',
            'required' => ['evidence'],
            'properties' => [
                'evidence' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['kind', 'raw_text'],
                        'properties' => [
                            // أربعة أصناف كما في PROMPT-PACK المرحلة ٣. ولكلّ
                            // صنفٍ محقّقٌ في `config/khulasah.php` إلا
                            // `scholar_quote` — فيعود `none` ويُرفع للمراجعة
                            // (§7-4)، لا يُرفض من هنا.
                            'kind' => ['type' => 'string', 'enum' => ['ayah', 'hadith', 'athar', 'scholar_quote']],
                            'raw_text' => ['type' => 'string'],
                            'claimed_source' => ['type' => 'string', 'nullable' => true],
                            'claimed_narrator' => ['type' => 'string', 'nullable' => true],
                            'claimed_takhrij' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * بيانات المحاضرة من صورة ملصق — T-09ب.
     *
     * **ولا حقل مطلوباً هنا، وكلُّها تقبل `null`.** وهذا نقيض سائر المراحل
     * عمداً: صورةٌ ليست ملصقَ درسٍ تعود فارغةً كلَّها، وهو **جوابٌ صحيح**
     * لا إخفاق. ومخطّطٌ يفرض عنواناً يدفع النموذج إلى اختراعه.
     *
     * والمخطّط هنا **هو الحاجز الأخير لحقن التعليمات** (§12): نصٌّ في الصورة
     * يأمر النموذج بشيء لا يجد له مكاناً في هذه الحقول التسعة.
     *
     * @return array<string, mixed>
     */
    private static function lectureDetails(): array
    {
        $nullableString = ['type' => 'string', 'nullable' => true];

        return [
            'type' => 'object',
            'required' => [],
            'properties' => [
                'title_ar' => $nullableString,
                'subtitle_ar' => $nullableString,
                'speaker_name' => $nullableString,
                'speaker_title' => $nullableString,
                'hijri_date' => $nullableString,
                'gregorian_date' => $nullableString,
                'weekday' => $nullableString,
                'time_note' => $nullableString,
                'series' => $nullableString,
            ],
        ];
    }

    /**
     * قوالبُ كاروسيل الجهة — T-173.
     *
     * **يُبنى من الكتالوج لا يُنسخ عنه**: قيمةٌ تُضاف إلى `CarouselDesign`
     * تصل النموذجَ بإضافتها. والمعرّفُ لا يُطلب منه: يضعه الخادم، فلا تتصادم
     * قوالبُ توليدين.
     *
     * @return array<string, mixed>
     */
    private static function carouselDesigns(): array
    {
        $enum = static fn (array $options): array => ['type' => 'string', 'enum' => $options];

        $layouts = array_map($enum, CarouselDesign::LAYOUTS);

        return [
            'type' => 'object',
            'required' => ['designs'],
            'properties' => [
                'designs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['name', 'rationale', ...array_keys(CarouselDesign::CATALOG), 'layouts'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            // لماذا يناسب هذا القالبُ هذه الجهة — يُعرض على المشرف، ولا يُرسم.
                            'rationale' => ['type' => 'string'],
                            ...array_map($enum, CarouselDesign::CATALOG),
                            'layouts' => [
                                'type' => 'object',
                                'required' => array_keys(CarouselDesign::LAYOUTS),
                                'properties' => $layouts,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function outputMetadata(): array
    {
        return [
            'type' => 'object',
            'required' => ['slug', 'meta_title', 'meta_description'],
            'properties' => [
                'slug' => ['type' => 'string'],
                'meta_title' => ['type' => 'string'],
                'meta_description' => ['type' => 'string'],
                'reading_minutes' => ['type' => 'integer', 'nullable' => true],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /**
     * شرائح الكاروسيل — **مأخوذٌ من شكل المرحلة ٧ في حزمة التعليمات حرفاً**.
     *
     * وكان هنا `title` و`body` وحدهما، والحزمة تُخرج
     * `index` و`kind` و`heading` و`body` و`source_line`. **فمخطّطٌ يفرض
     * مفتاحاً لا تُخرجه التعليمات يُسقط كلّ ردٍّ صحيح**، ولا يُكتشف قبل أوّل
     * نداء. والمخطّط تابعٌ للتعليمات لا العكس — CLAUDE.md §2 القاعدة الثانية.
     *
     * و`kind` قائمة مغلقة كـ`evidence.kind`: صنفٌ خارجها لا رسم له
     * ({@see SlideKind}).
     *
     * @return array<string, mixed>
     */
    /**
     * المرحلة ٨ — اختبارُ الفهم، T-195.
     *
     * **والمخطّطُ يحرس الشكل وحده**: جوابٌ صحيحٌ واحد، وعددُ الخيارات، والمحورُ
     * والشاهدُ المعروفان — كلُّ ذلك في {@see QuizGuard}، فهو
     * يحذف السؤالَ المخالف وحده ولا يُسقط الاختبار كلَّه بسببه.
     */
    private static function quiz(): array
    {
        return [
            'type' => 'object',
            'required' => ['questions'],
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['kind', 'prompt', 'options', 'explanation', 'axis_id'],
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ['single', 'true_false', 'evidence']],
                            'level' => ['type' => 'string', 'nullable' => true],
                            'prompt' => ['type' => 'string'],
                            'options' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['correct'],
                                    'properties' => [
                                        'text' => ['type' => 'string', 'nullable' => true],
                                        'evidence_id' => ['type' => 'string', 'nullable' => true],
                                        'correct' => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                            'explanation' => ['type' => 'string'],
                            'axis_id' => ['type' => 'string'],
                            'evidence_ids' => ['type' => 'array', 'nullable' => true, 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function carousel(): array
    {
        return [
            'type' => 'object',
            'required' => ['slides'],
            'properties' => [
                'slides' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['kind', 'heading', 'body'],
                        'properties' => [
                            'index' => ['type' => 'integer', 'nullable' => true],
                            'kind' => [
                                'type' => 'string',
                                'enum' => array_column(SlideKind::cases(), 'value'),
                            ],
                            'heading' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            // **الشاهد وحده له تخريج**، وشريحةُ محورٍ بلا
                            // سطر مصدرٍ شريحةٌ صحيحة لا ناقصة.
                            'source_line' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                ],
            ],
        ];
    }
}
