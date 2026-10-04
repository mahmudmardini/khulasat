<?php

declare(strict_types=1);

namespace App\Services\Transcript\Speech;

use App\Contracts\SpeechToText;

/**
 * The default speech-to-text in development and CI — CLAUDE.md §2 القاعدة ٧.
 *
 * **لا نداء تفريغ حقيقي في التطوير المحلّي ولا في CI.** وهذا الافتراضي،
 * وتبديله يحتاج `WHISPER_PROVIDER` صريحاً. والسبب مالٌ لا مبدأ فقط: تفريغ
 * ساعةٍ يكلّف، ومجموعةُ اختبارات تعمل مئات المرّات في اليوم.
 *
 * ويعيد نصّاً **معلوماً مشتقّاً من اسم الملفّ**، فيُميَّز المقطع من المقطع
 * ويُختبر جمعُها بترتيبها.
 */
class FakeSpeechToText implements SpeechToText
{
    public function name(): string
    {
        return 'fake';
    }

    public function maxBytes(): int
    {
        return (int) config('khulasah.transcript.whisper.max_bytes');
    }

    public function pricePerMinute(): float
    {
        return (float) config('khulasah.transcript.whisper.price_per_minute');
    }

    /** @param  list<string>  $glossary */
    public function transcribe(string $audioPath, string $languageCode, array $glossary = []): string
    {
        $marker = pathinfo($audioPath, PATHINFO_FILENAME);

        // نصّ عربيّ لا لاتينيّ: ما بعده يمرّ على التطبيع وعدّ الكلمات،
        // وكلاهما يسلك مع اللاتينية سلوكاً آخر فيخفي أخطاء حقيقية.
        return "هذا تفريغ وهميّ للمقطع {$marker} بلغة {$languageCode}.";
    }
}
