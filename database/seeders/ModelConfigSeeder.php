<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Stage;
use App\Models\ModelConfig;
use Illuminate\Database\Seeder;

/**
 * Default model per stage — المواصفة §4 و§6-أ، وPROMPT-PACK.
 *
 * **التشكيلة المتوازنة، بلا Haiku** — قرار مالك المنتج في T-00، 8 أيلول 2026.
 * وسببُ إخراج Haiku اثنان: تاريخ إيقافه (ليس قبل 2026-10-15) هو الأقرب في
 * الجدول كلّه، ولا يقبل معامل `effort` أصلاً. فحلّ محلّه `claude-sonnet-5`.
 *
 * **والأسعار من صفحات المزوّدين الرسمية، 8 أيلول 2026.** وكانت قبلها أسعار
 * جيلٍ متقاعد (Opus بـ15$/75$)، فكانت الكلفة تُسجَّل مضخَّمةً 2.6× ويُقفل
 * سقفُ الإنفاق الطابورَ قبل أوانه. **ومن غيّر نموذجاً فليغيّر سعره معه** —
 * السعر هنا هو ما يُحاسب به `usage_ledger`، لا ما يقوله المزوّد في ردّه.
 *
 * **وما تزال قيمُ الجودة غير محسومة:** أيّ نموذج أصدق نقلاً وأجود نثراً
 * لا يحسمه إلّا قياس T-00 على محاضرات حقيقية — `bench/RESULTS.md`. وهذه
 * تشغّل الخطّ وتضبط كلفته، ولا تُقرأ اختياراً نهائياً.
 *
 * والفئات من قواعد PROMPT-PACK العامّة: «المراحل 2 و5 تستعمل الفئة العليا.
 * البقية الفئة الاقتصادية أو المتوسطة».
 *
 * و`on_exhausted` من §4: `fail` لما لا يحتمل جودة أدنى — **استخراج البنية
 * وكتابة المتن** — و`degrade` للمراحل الميكانيكية.
 *
 * والبديل **من مزوّد مختلف** دائماً (§6-أ)، فيتبادل المزوّدان الموضعين.
 * و`google` صار له محوّلٌ (T-41)، **ولا يُكتب هنا مع ذلك**: أيّ نموذجٍ
 * فعليّ لأيّ مرحلة قرارُ جودةٍ وسعرٍ لمالك المنتج، لا قدرةً تقنية —
 * نظير T-00. القدرة تسبق القرار، والقرار لم يُتّخذ بعد.
 *
 * ★ **التشكيلة الحالية — T-58، ٩ أيلول ٢٠٢٦.** ومبدؤها مستخرَجٌ من أرقام
 * `cost_breakdown` لا من مذهب: **الجودةُ كلُّها في مرحلةٍ واحدة، فتُنفَق
 * فيها، وتُقتصَد كلُّ مرحلةٍ لا تصنع منتجاً — إلّا الشواهد.**
 *
 * وقياسُ محاضرةٍ من ٤٢٩٥ كلمة (‏$0.0692) هو ما بُنيت
 * عليه، معكوساً على أسعار المزوّدين:
 *
 *   - **الكتابة على `claude-opus-5`**: هي المنتج. وكانت على `gpt-5.6-terra`
 *     فأخرجت ~٢٨٨٠ توكناً، والمرجع (`reference-summary.html`) كتبه Opus.
 *     وهي ~٧٣٪ من كلفة الملخّص، **وذلك صحيحٌ ومقصود**.
 *   - **الشواهد على `claude-sonnet-5` ولا تُقتصَد**: `gpt-5-nano` أنفق
 *     ~٣٤٠ توكناً على مرحلةٍ تُخرج اثني عشر شاهداً، فخرجت بأربعة. **وهي
 *     أرخصُ مرحلةٍ وأعلاها أثراً في المصداقية الشرعية.** فإن قصُر الالتقاط
 *     تُرفع إلى `claude-opus-5`: الفارق ‏$0.06.
 *   - **البنية على `claude-sonnet-5` لا Opus**: بعد T-52 صارت المرحلةُ ٥
 *     تقرأ التفريغ بنفسها، **فلم تعد رهينةَ غِنى البنية**. ووظيفتُها بعدها
 *     **تقييدُ الكاتب بتقسيم المتكلّم** لا إغناؤه — انضباطُ تعليماتٍ لا
 *     قدرةٌ حدّية. ووضعُ Opus هنا يزيد ٤٢٪ بلا حرفٍ أفضل في المتن.
 *   - **الميكانيكيّ يبقى على `gpt-5-nano`**: التنظيفُ وبيانات الإخراج
 *     وتفاصيل المحاضرة. والتنظيفُ أغلى مرحلةٍ توكنزاً وأقلُّها اجتهاداً.
 *
 * والتقدير ‏$0.39 للملخّص بطول المهمّة ٢٨، و~‏$0.76 عند خمسة عشر ألف كلمة.
 *
 * ⚠ **وهذه تحتاج رصيد `anthropic`** — وكان منفَداً يوم كُتبت، وعليه بُنيت
 * التشكيلة المختلطة التي سبقتها. **والبديلُ إن ضاق الرصيد** `gpt-5.6-sol`
 * (‏$4/$20) في الكتابة: أرخصُ بـ١٥٪ ويُبقي الخطَّ كلَّه على OpenAI. وأيُّهما
 * أجودُ نثراً **لا يحسمه إلّا قياس T-59** على محاضرة المهمّة ٢٨ ومرجعِها.
 *
 * وروافعُ فُحصت ورُفضت: **تخزين السياق** لا يعمل هنا (الذاكرة لا تُشترك بين
 * نموذجين، وبوّابةُ `needs_review` البشرية تقع بين المرحلة ٣ والمرحلة ٥
 * فتتجاوز المهلة)، و**Batch API** يوفّر النصف بمهلةٍ حتى ٢٤ ساعة — قرارُ
 * منتجٍ لا هندسة. و`claude-haiku-4-5` يبقى مرفوضاً لتاريخ إيقافه.
 */
class ModelConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::rows() as $row) {
            ModelConfig::query()->updateOrCreate(
                ['stage' => $row['stage']],
                $row + ['updated_at' => now()],
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private static function rows(): array
    {
        return [
            [
                'stage' => Stage::Cleaning->value,
                'provider' => 'openai',
                'model_id' => 'gpt-5-nano',
                'max_tokens' => 32_000,
                // التنظيف يُخرج التفريغ كاملاً تقريباً، فسقفُه أوسع من غيره.
                'thinking_level' => 'none',
                'fallback_provider' => 'anthropic',
                'fallback_model_id' => 'claude-sonnet-5',
                'timeout_seconds' => 180,
                'max_retries' => 1,
                // ميكانيكية: تنظيفٌ أدنى جودةً خيرٌ من مهمّة واقفة.
                'on_exhausted' => 'degrade',
                'input_price_per_m' => 0.05,
                'output_price_per_m' => 0.40,
            ],
            [
                'stage' => Stage::ExtractingStructure->value,
                'provider' => 'anthropic',
                'model_id' => 'claude-sonnet-5',
                // ٨٠٠٠ كانت تكفي عند anthropic، لا عند نموذج استدلال حقيقي:
                // توكنز التفكير تُخصم من السقف نفسه، فيُقطع JSON قبل تمامه —
                // اكتُشف على استدعاء حقيقي، 8 أيلول 2026.
                'max_tokens' => 16_000,
                'thinking_level' => 'medium',
                'fallback_provider' => 'openai',
                'fallback_model_id' => 'gpt-5.6-terra',
                'timeout_seconds' => 300,
                'max_retries' => 1,
                // البنية هيكلُ الملخّص كلّه، ولا تحتمل جودة أدنى.
                'on_exhausted' => 'fail',
                'input_price_per_m' => 2.0,
                'output_price_per_m' => 10.0,
            ],
            [
                'stage' => Stage::ExtractingEvidence->value,
                'provider' => 'anthropic',
                'model_id' => 'claude-sonnet-5',
                'max_tokens' => 8_000,
                // نقلٌ حرفيّ لا اجتهاد، فأدنى جهدٍ أصدق وأرخص.
                'thinking_level' => 'none',
                'fallback_provider' => 'openai',
                'fallback_model_id' => 'gpt-5.6-terra',
                'timeout_seconds' => 180,
                'max_retries' => 1,
                // الشاهدُ المستخرَج ناقصاً يُفسد التحقّق كلّه.
                'on_exhausted' => 'fail',
                'input_price_per_m' => 2.0,
                'output_price_per_m' => 10.0,
            ],
            [
                'stage' => Stage::Writing->value,
                'provider' => 'anthropic',
                'model_id' => 'claude-opus-5',
                // نفس علّة استخراج البنية: جهدٌ عالٍ يستهلك توكنز تفكيرٍ من
                // السقف نفسه — 8 أيلول 2026.
                'max_tokens' => 32_000,
                'thinking_level' => 'high',
                'fallback_provider' => 'openai',
                'fallback_model_id' => 'gpt-5.6-sol',
                // «كتابة المتن بتفكير عالٍ تحتاج أضعاف ما يحتاجه التنظيف.
                // والمهلة تشمل زمن التفكير» — §6-أ.
                'timeout_seconds' => 600,
                'max_retries' => 1,
                'on_exhausted' => 'fail',
                'input_price_per_m' => 5.0,
                'output_price_per_m' => 25.0,
            ],
            [
                'stage' => Stage::OutputMetadata->value,
                'provider' => 'openai',
                'model_id' => 'gpt-5-nano',
                'max_tokens' => 4_000,
                'thinking_level' => 'none',
                'fallback_provider' => 'anthropic',
                'fallback_model_id' => 'claude-sonnet-5',
                'timeout_seconds' => 120,
                'max_retries' => 1,
                'on_exhausted' => 'degrade',
                'input_price_per_m' => 0.05,
                'output_price_per_m' => 0.40,
            ],
            [
                'stage' => Stage::Carousel->value,
                'provider' => 'anthropic',
                'model_id' => 'claude-sonnet-5',
                'max_tokens' => 4_000,
                'thinking_level' => 'none',
                'fallback_provider' => 'openai',
                'fallback_model_id' => 'gpt-5-nano',
                'timeout_seconds' => 120,
                'max_retries' => 1,
                'on_exhausted' => 'degrade',
                'input_price_per_m' => 2.0,
                'output_price_per_m' => 10.0,
            ],
            [
                /*
                 * **الترجمة** — T-38، وخارج الستّ كذلك.
                 *
                 * وعلى الفئة المتوسطة لا الأرخص: النقلُ بين اللغات ليس
                 * ميكانيكياً كالتنظيف — نموذجٌ ضعيف يُخرج تركيةً ركيكة
                 * تُقرأ في صفحةٍ منشورة باسم الجهة.
                 *
                 * و`on_exhausted: degrade` لا `fail`: **العربيّ منشورٌ على
                 * كل حال**، وسقوطُ الترجمة يمنع لغةً ولا يُسقط ملخّصاً تمّ
                 * عملُه كلُّه — نظيرُ الكاروسيل في `RenderAndPublish`.
                 */
                'stage' => Stage::Translating->value,
                'provider' => 'openai',
                'model_id' => 'gpt-5.6-terra',
                // جدولُ نصوصٍ كاملٌ ذهاباً وإياباً، فسقفُه واسع.
                'max_tokens' => 32_000,
                'thinking_level' => 'low',
                'fallback_provider' => 'google',
                'fallback_model_id' => 'gemini-3.7-flash',
                'timeout_seconds' => 600,
                'max_retries' => 1,
                'on_exhausted' => 'degrade',
                'input_price_per_m' => 2.0,
                'output_price_per_m' => 12.0,
            ],
            [
                /*
                 * **خارج الستّ، وتُنادى فعلاً** — T-09ب. وكانت بلا صفٍّ،
                 * فتسقط في الوضع الحقيقي بـ`model_not_configured`. وسقوطُها
                 * صامتٌ عمداً (تُفتح الحقول للإدخال اليدوي)، فلا يعرف أحدٌ
                 * أنّ الميزة ميّتة إلّا من سجلّ التحذيرات.
                 */
                'stage' => Stage::LectureDetails->value,
                'provider' => 'openai',
                'model_id' => 'gpt-5-nano',
                'max_tokens' => 2_000,
                // قراءةُ ملصقٍ لا اجتهاد فيها: البيانات تُقرأ لا تُنشأ.
                'thinking_level' => 'none',
                'fallback_provider' => 'anthropic',
                'fallback_model_id' => 'claude-sonnet-5',
                'timeout_seconds' => 120,
                'max_retries' => 1,
                // توفيرُ وقتٍ لا حراسةُ بوّابة، فالسقوط لا يوقف المستخدم.
                'on_exhausted' => 'degrade',
                'input_price_per_m' => 0.05,
                'output_price_per_m' => 0.40,
            ],
        ];
    }
}
