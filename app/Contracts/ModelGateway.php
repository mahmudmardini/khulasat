<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\Stage;
use App\Exceptions\ModelCallFailed;
use App\Models\SummaryJob;
use App\Support\Model\ModelResponse;

/**
 * The one door through which a language model is called — المواصفة §6.
 *
 * **ولا نداء نموذج خارج هذا العقد.** ثلاثةٌ تُفرض هنا فتُفرض على الجميع:
 * الكلفةُ تُسجَّل، والمخطّطُ يُتحقّق منه، ومحتوى المستخدم يُلَفّ في
 * `<transcript>`. ومن نادى مزوّداً مباشرةً تجاوز الثلاثة معاً.
 *
 * @see khulasah-build-spec.md §6
 */
interface ModelGateway
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     *                                                                التعليمات في `system`، **ومحتوى المستخدم في `user` وحده** — §12.
     * @param  array<string, mixed>|null  $schema
     *                                             مخطّط الخرج، ويُتحقّق منه. `null` لمرحلةٍ خرجها نصّ أو HTML.
     *
     * @throws ModelCallFailed عند سقوط الاستدعاء أو مخالفة المخطّط.
     */
    public function call(
        Stage $stage,
        array $messages,
        ?array $schema = null,
        ?SummaryJob $job = null,
    ): ModelResponse;
}
