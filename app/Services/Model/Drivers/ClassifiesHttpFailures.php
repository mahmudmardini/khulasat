<?php

declare(strict_types=1);

namespace App\Services\Model\Drivers;

use App\Exceptions\ModelCallFailed;

/**
 * The retry classification of §6-أ, in one place — المواصفة §6-أ «ما لا يُعاد».
 *
 * **تصنيفٌ واحد لكلّ المزوّدين.** ولو نُسخ في كلّ محوّل لتباعدت النسخ، فأُعيد
 * عند مزوّدٍ ما لا يُعاد عند غيره — و«إعادتها تحرق مالاً بلا فائدة».
 */
trait ClassifiesHttpFailures
{
    protected function classify(int $status, string $provider, string $body = ''): ModelCallFailed
    {
        $detail = trim("{$provider} أخفق برمز {$status}. ".mb_substr($body, 0, 300));

        return match (true) {
            // المصادقة: مفتاحٌ خاطئ أو منتهٍ. التكرار لا يصلحه.
            $status === 401 || $status === 403 => ModelCallFailed::permanent('authentication_failed', $detail),

            // رفض المحتوى: النموذج رفض الطلب نفسه، وإعادته تُرفض مثله.
            $status === 400 || $status === 422 => ModelCallFailed::permanent('content_rejected', $detail),

            // حدّ المعدّل — يُعاد بتراجع.
            $status === 429 => ModelCallFailed::retryable('rate_limited', $detail),

            // عطلٌ عند المزوّد — يُعاد.
            $status >= 500 => ModelCallFailed::retryable('provider_error', $detail),

            // ما لم يُصنَّف: يُعامَل دائماً **لا يُعاد**. والافتراض المعاكس
            // يُنفق مالاً على عطلٍ لم نفهمه بعد.
            default => ModelCallFailed::permanent('provider_error', $detail),
        };
    }
}
