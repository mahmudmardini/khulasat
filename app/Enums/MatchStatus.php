<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Outcome of matching an extracted evidence against its source — المواصفة §4.
 */
enum MatchStatus: string
{
    case Exact = 'exact';
    case Partial = 'partial';
    case None = 'none';

    /*
     * **ولا `mayAutoPass()` هنا.** كانت في هذا الصنف تقول «المطابق تماماً
     * وحده يمرّ»، وهي سياسةُ المجال الشرعي لا سياسةَ النظام — §7-5 وT-02ب.
     * ووضعُها في `enum` عامّ يفرض تشدّد الشرعي على كلّ مجالٍ قادم.
     * ومكانها اليوم {@see \App\Support\Verification\DomainPolicy}.
     */
}
