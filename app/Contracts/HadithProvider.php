<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\HadithCorpusUnavailable;
use App\Support\Verification\HadithMatch;

/**
 * A source of hadith texts and rulings — المواصفة §7-3.
 *
 * **سقوط المزوّد لا يُسقط النظام.** من أخفق يرمي استثناءً، ويلتقطه
 * `HadithVerifier` فيجرّب التالي. **وإن لم يُجب مزوّدٌ محكومٌ واحد رُمي
 * {@see HadithCorpusUnavailable}** (T-213): بحثٌ لم يجرِ
 * لا يُقال فيه «لم يُعثر عليه». وسقوطُ مزوّدٍ تكميليّ يُتجاوز دائماً.
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
