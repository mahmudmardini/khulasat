<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * البحثُ في الكتب المحكومة تعطّل، فلا حكمَ يُقال — T-213.
 *
 * **«لم يُعثر عليه» حكمٌ على الحديث، و«تعذّر البحث» حالٌ عندنا.** وكان
 * الأوّلُ يُقال عن الثاني: فتقول أداةُ «تحقّق» عن حديثٍ في البخاري إنّه
 * غير موجود، ويُنشر الملخّصُ بلا أحاديثه. فيُرمى هذا ولا يُبتلع.
 */
final class HadithCorpusUnavailable extends RuntimeException
{
    public const CODE = 'corpus_unavailable';

    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
