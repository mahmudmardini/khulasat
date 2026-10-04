<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * أيفيض نصُّ شريحةٍ عن إطارها؟ — T-173.
 *
 * الشريحةُ بمقاسٍ ثابت وفيضُها مقصوص (`overflow:hidden`)، فنصٌّ لا يسعها
 * يُقطع في الصورة صامتاً، ويُنشر على إنستغرام ناقصاً. والقياسُ لا يكون إلّا
 * في متصفّح: الخطوطُ والتفافُ الأسطر لا تُحسب في PHP.
 */
interface OverflowProbe
{
    /**
     * @return list<int>|null أرقامُ الشرائح التي يفيض نصُّها، من واحد. فارغةً
     *                        لا فيض، و`null` إن تعذّر القياس (لا متصفّح).
     */
    public function overflowing(string $html): ?array;
}
