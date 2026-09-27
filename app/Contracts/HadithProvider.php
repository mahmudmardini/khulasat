<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Verification\HadithMatch;

/**
 * A source of hadith texts and rulings — المواصفة §7-3.
 *
 * **سقوط المزوّد لا يُسقط النظام.** من أخفق يرمي استثناءً، ويلتقطه
 * `HadithVerifier` فيجرّب التالي. وإن سقط الجميع عادت النتيجة `none`
 * ورُفعت للمراجعة — والنظام يعمل بلا مزوّد خارجي البتّة، وإن كانت كلّ
 * الشواهد حينها تحتاج إنساناً.
 */
interface HadithProvider
{
    /** اسمه في السجلّ والكاش. */
    public function name(): string;

    /**
     * @return list<HadithMatch>
     *
     * @throws \Throwable عند تعذّر الوصول — يلتقطه المحقّق ولا يُسقط المهمّة.
     */
    public function search(string $normalized): array;
}
