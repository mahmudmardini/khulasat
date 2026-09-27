<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\ModelCallFailed;
use App\Models\ModelConfig;
use App\Support\Model\DriverResult;

/**
 * One provider's HTTP shape, and nothing more — المواصفة §6-أ.
 *
 * **المحوّل لا يعرف مرحلةً ولا كلفةً ولا مخطّطاً.** يأخذ رسائل ويعيد نصّاً
 * وعدَّ توكنز. والقرارُ كلّه — أيّ نموذج، وكم يكلّف، وهل طابق المخطّط —
 * في {@see ModelGateway}. فإضافة مزوّدٍ ثالثٍ لا تمسّ منطقاً.
 *
 * **والمفتاح من `.env` وحده** — §12 وCLAUDE.md §2 القاعدة السادسة.
 *
 * **ولا `temperature` هنا** — T-34: المعامل مهجورٌ على نماذج الجيل الحالي
 * ويُعيد 400 إذا ضُبط بغير الافتراضي. وضبطُ عمق التفكير صار بـ`thinking_level`
 * في {@see ModelConfig}، يترجمه كلّ محوّل إلى معامل مزوّده.
 */
interface ModelDriver
{
    /** اسمه كما يُكتب في `model_config.provider`. */
    public function name(): string;

    /** أمضبوطٌ مفتاحه؟ ومن لا مفتاح له لا يُنادى، ولا يصلح بديلاً. */
    public function isConfigured(): bool;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     *
     * @throws ModelCallFailed
     */
    public function send(ModelConfig $config, array $messages): DriverResult;
}
