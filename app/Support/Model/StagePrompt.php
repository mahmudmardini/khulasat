<?php

declare(strict_types=1);

namespace App\Support\Model;

use App\Enums\Stage;
use RuntimeException;

/**
 * Loads a stage's system prompt from disk — T-11 وCLAUDE.md §2 القاعدة الثانية.
 *
 * **التعليمات تُقرأ كما هي حرفاً بحرف.** لا تُعاد صياغتها، ولا تُختصر، ولا
 * تُدمج تعليمات مرحلة في أخرى. «هذه التعليمات مضبوطة بعناية **وهي جوهر
 * جودة المنتج**. إن رأيت فيها نقصاً، اعرضه على المستخدم ولا تعدّله.»
 *
 * وهي في `resources/prompts/<المجال>/` ملفّاتٍ نصّية تُحمَّل عند التشغيل **لا
 * مكتوبةً داخل PHP**: تعديلُ حرفٍ في تعليماتٍ مدفونةٍ في صنفٍ يحتاج نشراً،
 * وتعديلُه في ملفٍّ نصّي لا يحتاج. والمصدر `prompts/<المجال>/PROMPT-PACK.md`،
 * ويحرس التطابقَ اختبارٌ فلا تتباعد النسختان صامتتين.
 *
 * **والمجال في المسار** — T-02ب. حزمةٌ واحدة اليوم (الشرعية)، ووضعُها في
 * مسار مجالها يجعل الثانية إضافةَ مجلّد لا إعادةَ بناء. **ولا تُنسخ حزمة
 * ثانية ولا يُعدَّل حرفٌ في هذه** — CLAUDE.md §2 القاعدة الثانية.
 *
 * @see prompts/islamic/PROMPT-PACK.md
 */
final class StagePrompt
{
    private function __construct() {}

    public static function directory(string $domain): string
    {
        return base_path("resources/prompts/{$domain}");
    }

    public static function path(Stage $stage, string $domain): string
    {
        // مرحلةٌ لا تختلف باختلاف المجال تُقرأ من `shared/` — انظر
        // {@see Stage::isDomainSpecific()}.
        return self::directory($stage->isDomainSpecific() ? $domain : 'shared').'/'.$stage->value.'.txt';
    }

    /**
     * تعليمات النظام لهذه المرحلة.
     *
     * **ولا يُخترع نصٌّ عند الغياب.** تعليماتٌ مصنوعة في الطيران تُنتج
     * ملخّصاً بجودةٍ أخرى بلا أن يدري أحد، وذلك أسوأ من مهمّةٍ تقف.
     *
     * @throws RuntimeException
     */
    public static function for(Stage $stage, string $domain): string
    {
        $path = self::path($stage, $domain);

        if (! is_file($path)) {
            throw new RuntimeException(
                "لا ملفّ تعليمات للمرحلة {$stage->value} في المجال «{$domain}» على المسار {$path}."
            );
        }

        $contents = trim((string) file_get_contents($path));

        if ($contents === '') {
            throw new RuntimeException("ملفّ تعليمات المرحلة {$stage->value} في المجال «{$domain}» فارغ.");
        }

        return $contents;
    }

    /**
     * الرسائل كما تُرسَل: التعليمات في `system`، ومحتوى المستخدم **ملفوفاً**.
     *
     * — المواصفة §12 وقواعد PROMPT-PACK العامّة: «التعليمات الظاهرة أدناه
     * هي `system`. مدخل المستخدم في `user`».
     *
     * @return list<array{role: string, content: string}>
     */
    public static function messages(Stage $stage, string $userContent, string $domain): array
    {
        return [
            ['role' => 'system', 'content' => self::for($stage, $domain)],
            TranscriptEnvelope::userMessage($userContent),
        ];
    }
}
