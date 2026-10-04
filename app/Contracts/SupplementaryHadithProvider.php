<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\HadithCorpusUnavailable;

/**
 * مزوّدٌ لا حكمَ فيه يُنشر — كالطبقة الثانية (T-170).
 *
 * **سقوطُه يُتجاوز**، إذ لا يُنشر منه شيءٌ أصلاً. أمّا سقوطُ الكتب المحكومة
 * كلِّها فلا يُتجاوز: {@see HadithCorpusUnavailable} — T-213.
 */
interface SupplementaryHadithProvider extends HadithProvider {}
