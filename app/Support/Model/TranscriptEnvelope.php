<?php

declare(strict_types=1);

namespace App\Support\Model;

/**
 * Wraps user content before it reaches a model — المواصفة §12 المخطر الأوّل.
 *
 * «التفريغ نصّ لا يسيطر عليه أحد، وقد يحوي **تجاهل ما سبق**. العلاج: يوضع
 * دائماً داخل وسم بيانات صريح، وتُبنى تعليمات النظام على أنّ ما بين الوسمين
 * **مادّة تُعالَج لا أوامر تُطاع**.»
 *
 * وثلاثة قيود:
 *
 *   ١. **في رسالة `user` لا `system` أبداً.** دمجُ التفريغ في تعليمات النظام
 *      يجعل كلام المتحدّث وكلامَنا في مرتبةٍ واحدة، فيصير «تجاهل ما سبق»
 *      أمراً لا نصّاً. وهذا ما يفرضه T-10 صراحةً.
 *
 *   ٢. **الوسم يُنزع من المحتوى قبل لفّه.** ولو تُرك لأمكن أن يكتب المفرِّغ
 *      `</transcript>` في نصّه فيُغلق الغلاف باكراً، ويصير ما بعده خارجَه —
 *      أي أوامرَ تُطاع. وهذا هو الهروب من الغلاف، ولا يُدفع إلا بنزعه.
 *
 *   ٣. الحاجز الأخير ليس هذا، بل **التحقّق من مخطّط الخرج** (§12): أيّ خروج
 *      عن الشكل المتوقّع يُوقف المهمّة.
 *
 * @see khulasah-build-spec.md §12
 * @see prompts/islamic/PROMPT-PACK.md قسم القواعد العامة
 */
final class TranscriptEnvelope
{
    public const OPEN = '<transcript>';

    public const CLOSE = '</transcript>';

    /** أشكال الوسم التي تُنزع: بمسافات، وبأحرف كبيرة، وبشرطة إغلاق. */
    private const TAG_PATTERN = '#</?\s*transcript\s*/?>#iu';

    private function __construct() {}

    public static function wrap(string $content): string
    {
        return self::OPEN."\n".self::strip($content)."\n".self::CLOSE;
    }

    /**
     * نزعُ كلّ وسمٍ من المحتوى قبل لفّه.
     *
     * ويُستبدل بمسافة لا يُحذف، فلا تلتصق الكلمتان حوله.
     */
    public static function strip(string $content): string
    {
        $stripped = preg_replace(self::TAG_PATTERN, ' ', $content) ?? $content;

        return trim(preg_replace('/[^\S\n]+/u', ' ', $stripped) ?? $stripped);
    }

    /**
     * رسالة المستخدم كما تُرسَل.
     *
     * @return array{role: string, content: string}
     */
    public static function userMessage(string $content): array
    {
        return ['role' => 'user', 'content' => self::wrap($content)];
    }
}
