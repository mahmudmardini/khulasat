<?php

declare(strict_types=1);

namespace App\Domain\Summary;

use RuntimeException;

/**
 * Thrown when a job tries to leave review with evidence still unsettled.
 *
 * **هذا هو المنع الباتّ** الذي تصفه المواصفة §5: التوقّف عند `needs_review`
 * يمنع النشر، ولا إعداد يتجاوزه ولا وضع تطوير. وهو فحصٌ في الكود لا شرطٌ
 * في الواجهة، لأنّ الواجهة يُلتفّ عليها بأمرٍ سطريّ أو مهمّة في الطابور.
 */
final class ReviewIncomplete extends RuntimeException
{
    public static function forJob(int $jobId, int $pending): self
    {
        return new self(sprintf(
            'المهمّة %d فيها %d شاهداً لم يُحسم بعد. لا انتقال إلى الكتابة ولا نشر قبل حسمها كلّها.',
            $jobId,
            $pending,
        ));
    }
}
