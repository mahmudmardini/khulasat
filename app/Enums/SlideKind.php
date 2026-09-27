<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * صنف شريحة الكاروسيل — المرحلة ٧ في `prompts/islamic/PROMPT-PACK.md`.
 *
 * **والقيم مأخوذة من التعليمات حرفاً**، فهي عقد المخرَج معها: النموذج يُخرج
 * `kind` بإحدى هذه القيم، وأيّ زيادةٍ هنا لا تُقابلها زيادةٌ هناك تبقى
 * ميّتة — ونقصٌ هنا يُسقط شريحةً أخرجها النموذج بحقّ.
 */
enum SlideKind: string
{
    case Cover = 'cover';
    case Ayah = 'ayah';
    case Concept = 'concept';
    case Diagnosis = 'diagnosis';
    case Axis = 'axis';
    case Comparison = 'comparison';
    case Evidence = 'evidence';
    case Closing = 'closing';

    /**
     * أتحمل هذه الشريحة **لفظ مصدرٍ لا يُمسّ**؟
     *
     * والفرق ليس زينةً: ما كان لفظَ مصدرٍ يُثبَّت من الشواهد المتحقَّقة ولا
     * يُقاس بسقف الأربعين كلمة، وما عداه نصٌّ حرٌّ كتبه النموذج فيُقاس.
     * فآيةٌ طويلة تُفرد بشريحة، والحشوُ في شريحةِ محورٍ لا يُغتفَر.
     */
    public function carriesSourceWording(): bool
    {
        return $this === self::Ayah || $this === self::Evidence;
    }

    /** الشعار في الأولى والأخيرة فقط — المواصفة §8-أ. */
    public function bearsLogo(): bool
    {
        return $this === self::Cover || $this === self::Closing;
    }
}
