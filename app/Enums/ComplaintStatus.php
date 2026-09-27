<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * حال الاعتراض — T-28، ودراسة المشروع المادة 15.
 *
 * **وثلاثةُ مخارج لا مخرجٌ واحد.** فـ«مُغلَقة» تخفي الفرق بين صفحةٍ أُزيلت
 * وشكوى رُدّت، وهما أبعدُ ما يكونان: الأولى فعلٌ لا يُستردّ يُسأل عنه بعد
 * أشهر، والثانية قرارٌ يُراجَع. ومن جمعهما في حالةٍ واحدة لم يستطع أن يقول
 * كم صفحةً أزال ولا كم شكوى ردّ.
 */
enum ComplaintStatus: string
{
    /** وصلت ولم يُنظر فيها — والمهلة تجري عليها. */
    case Open = 'open';

    /** أُزيلت الصفحة، وبقيت شاهدةُ 410 مكانها (§9). */
    case Unpublished = 'unpublished';

    /** عولجت **بغير إزالة**: صُحّح التخريج، أو أُجيب المعترض. */
    case Resolved = 'resolved';

    /** لا إجراء — بسببٍ مكتوب. */
    case Dismissed = 'dismissed';

    /** أانتهى أمرُها؟ وما انتهى لا تجري عليه المهلة. */
    public function isSettled(): bool
    {
        return $this !== self::Open;
    }

    /**
     * أهي فعلٌ على المنشور يُبلَّغ به صاحبُ الجهة؟
     *
     * **والإزالة وحدها.** فصفحةُ الجهة سقطت من تحتها، ومن لم يُبلَّغ ظنّ
     * العطلَ عندنا. وأمّا ردُّ شكوى أو تصحيحُ تخريجٍ فلا يمسّ ما نشرت.
     */
    public function notifiesTenant(): bool
    {
        return $this === self::Unpublished;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
