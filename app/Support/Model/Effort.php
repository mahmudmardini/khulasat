<?php

declare(strict_types=1);

namespace App\Support\Model;

/**
 * Translate `model_config.thinking_level` into each provider's own parameter.
 *
 * الجدول يخزّن ثلاث درجات — `none` و`medium` و`high` — والمزوّدون يسمّونها
 * بأسماء مختلفة ويقبلون سلالم مختلفة. فالترجمة هنا في موضع واحد، لأنّ
 * نسختين منها تتباعدان فتصير المرحلة الخامسة تجري بجهدٍ غير الذي في الجدول.
 *
 * **ولا `none` عند أنثروبيك:** أدنى درجاته `low`، وليس فيها إطفاء. فمرحلةٌ
 * مكتوبٌ لها `none` تُنادى بـ`low` — وهو أقلّ ما يقبله، لا ما نتمنّاه.
 *
 * **ولا `none` عند OpenAI كذلك** — اكتُشف على `gpt-5-nano` الحقيقي، 8 أيلول
 * 2026: يردّ 400 صراحةً بأنّ `reasoning_effort` لا يقبل `none`، وأدنى ما
 * يقبله `minimal`. فمرحلةٌ مكتوبٌ لها `none` تُنادى بـ`minimal` هنا أيضاً.
 *
 * @see khulasah-build-spec.md §6-أ
 */
final class Effort
{
    /** درجات الجهد عند أنثروبيك: low · medium · high · xhigh · max. */
    public static function forAnthropic(string $thinkingLevel): string
    {
        return match ($thinkingLevel) {
            'none', 'low' => 'low',
            'medium' => 'medium',
            'xhigh' => 'xhigh',
            'max' => 'max',
            default => 'high',
        };
    }

    /** درجات الجهد عند OpenAI — وأدناها `minimal`، لا `none`. */
    public static function forOpenAi(string $thinkingLevel): string
    {
        return match ($thinkingLevel) {
            'none', 'minimal' => 'minimal',
            'low' => 'low',
            'medium' => 'medium',
            'xhigh' => 'xhigh',
            'max' => 'max',
            default => 'high',
        };
    }

    /**
     * درجة `thinkingLevel` عند Gemini — وقيمتاها المعروفتان `low` و`high`
     * فقط (`bench/lib/Catalog.php`)، بلا سلّم أوسع كأنثروبيك وOpenAI.
     * ولا `none`: نظير أنثروبيك، أدناها تفكيرٌ لا إطفاء.
     */
    public static function forGoogle(string $thinkingLevel): string
    {
        return match ($thinkingLevel) {
            'none', 'low' => 'low',
            default => 'high',
        };
    }
}
