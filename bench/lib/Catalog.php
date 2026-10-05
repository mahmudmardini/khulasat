<?php

declare(strict_types=1);

namespace Bench;

/**
 * The candidates, their ids, and their prices — verified 2026-10-04.
 *
 * **الأسعار من صفحات المزوّدين الرسمية**، لا من مجمِّعات ولا من الذاكرة:
 *   · platform.claude.com/docs/en/about-claude/pricing
 *   · developers.openai.com/api/docs/pricing
 *   · ai.google.dev/gemini-api/docs/pricing
 *
 * والأسعار تتبدّل كلّ أشهر، **فمن قاس بعد شهرٍ فليُعِد التحقّق أوّلاً**
 * وليكتب التاريخ الجديد هنا. رقمٌ قديمٌ في هذا الملفّ يجعل عمود الكلفة
 * كلّه كذباً مرتّباً.
 *
 * والقائمة القصيرة أربعةُ نماذج من ثلاثة مزوّدين — معيار قبول T-00.
 */
final class Catalog
{
    public const PRICES_VERIFIED_ON = '2026-10-04';

    /**
     * @return array<string, array{provider: string, model: string, in: float, out: float, effort: string}>
     */
    public static function all(): array
    {
        return [
            'opus-5.5' => [
                'provider' => 'anthropic',
                'model' => 'claude-opus-5-5',
                'in' => 4.0,
                'out' => 20.0,
                'effort' => 'high',
            ],
            'sonnet-5.5' => [
                'provider' => 'anthropic',
                'model' => 'claude-sonnet-5-5',
                'in' => 2.0,
                'out' => 10.0,
                'effort' => 'high',
            ],
            'gpt-5.6-terra' => [
                'provider' => 'openai',
                'model' => 'gpt-5.6-terra',
                'in' => 2.0,
                'out' => 12.0,
                'effort' => 'high',
            ],
            'gemini-3.1-pro' => [
                'provider' => 'google',
                'model' => 'gemini-3.1-pro-preview',
                'in' => 2.0,
                'out' => 12.0,
                'effort' => 'high',
            ],
            /*
             * **النموذج نفسه بجهدٍ أدنى** — T-59. وسؤالُ «أيفرّق `high` عن
             * `medium` فرقاً يستحقّ ثمنَه؟» لا يُقاس بغير مدخلين للنموذج
             * الواحد: الجهدُ في هذا الجدول ثابتٌ لكلّ مفتاح.
             *
             * والفرقُ كلُّه في توكنز التفكير، **وهي تُحاسَب مخرَجاً** — فلا
             * يظهر أثرُه في السعر بل في الفاتورة.
             */
            'opus-5.5-medium' => [
                'provider' => 'anthropic',
                'model' => 'claude-opus-5-5',
                'in' => 4.0,
                'out' => 20.0,
                'effort' => 'medium',
            ],

            // خارج القائمة القصيرة، ويُنادى بالاسم عند الحاجة:
            'gpt-5.6-sol' => [
                'provider' => 'openai',
                'model' => 'gpt-5.6-sol',
                'in' => 4.0,
                'out' => 20.0,
                'effort' => 'high',
            ],
            'fable-5.1' => [
                'provider' => 'anthropic',
                'model' => 'claude-fable-5-1',
                'in' => 10.0,
                'out' => 50.0,
                'effort' => 'high',
            ],
        ];
    }

    /** @return list<string> الأربعة التي تدخل القياس ما لم يُطلب غيرها. */
    public static function shortlist(): array
    {
        return ['opus-5.5', 'sonnet-5.5', 'gpt-5.6-terra', 'gemini-3.1-pro'];
    }

    /** @return array{provider: string, model: string, in: float, out: float, effort: string} */
    public static function get(string $key): array
    {
        $all = self::all();

        if (! isset($all[$key])) {
            $known = implode(' · ', array_keys($all));

            throw new \InvalidArgumentException("نموذج غير معروف: {$key}. والمعروف: {$known}");
        }

        return $all[$key];
    }

    /**
     * الكلفة بالدولار. **وتوكنز التفكير خرجٌ** عند المزوّدين الثلاثة.
     */
    public static function cost(string $key, int $inputTokens, int $outputTokens): float
    {
        $model = self::get($key);

        return $inputTokens * $model['in'] / 1_000_000 + $outputTokens * $model['out'] / 1_000_000;
    }
}
