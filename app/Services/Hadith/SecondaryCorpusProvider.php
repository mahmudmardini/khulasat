<?php

declare(strict_types=1);

namespace App\Services\Hadith;

use App\Enums\HadithBook;

/**
 * The second tier of the local corpus: Musnad Ahmad and Sunan al-Darimi — T-170.
 *
 * **بلا أحكام**، فيُسأل بعد الكتب المحكومة ولا يُسأل قبلها: `HadithVerifier`
 * يقف عند أوّل مزوّدٍ يبلغ حدَّ المطابقة الجزئية. فما وُجد في الصحيحين أو
 * السنن يبقى بحكمه كما كان، **ولا يُعرف الحديثُ من المسند إلّا إذا لم يوجد
 * في غيره** — فيُقال موضعُه، ولا يُنشر لأنّه بلا حكم.
 */
class SecondaryCorpusProvider extends LocalCorpusProvider
{
    public function name(): string
    {
        return 'local_corpus_secondary';
    }

    /** @return list<HadithBook> */
    protected function books(): array
    {
        return HadithBook::secondary();
    }
}
