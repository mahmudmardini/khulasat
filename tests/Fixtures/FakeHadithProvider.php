<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Contracts\HadithProvider;
use App\Support\Verification\HadithMatch;
use RuntimeException;

/**
 * A provider under the test's control.
 *
 * الاختبارات لا تمسّ الشبكة: حتمية وسريعة، ولا تنكسر بسقوط مزوّد خارجي.
 */
class FakeHadithProvider implements HadithProvider
{
    /** @param list<HadithMatch> $corpus */
    public function __construct(
        private readonly array $corpus = [],
        private readonly ?string $failWith = null,
        private readonly string $name = 'fake',
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    /** @return list<HadithMatch> */
    public function search(string $normalized): array
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }

        return $this->corpus;
    }

    /** المتن التسعة المستعملة في العيّنة الحاكمة. */
    public static function withKnownHadiths(): self
    {
        return new self([
            new HadithMatch(
                text: 'أحب الأعمال إلى الله أدومها وإن قل',
                narrator: 'عائشة رضي الله عنها',
                book: 'صحيح البخاري',
                hadithNumber: '6464',
                ruling: 'صحيح',
                takhrij: 'متفق عليه: رواه البخاري ومسلم',
            ),
            new HadithMatch(
                text: 'المسلم من سلم المسلمون من لسانه ويده',
                narrator: 'أبو موسى الأشعري رضي الله عنه',
                book: 'صحيح البخاري',
                hadithNumber: '11',
                ruling: 'صحيح',
                takhrij: 'متفق عليه: رواه البخاري ومسلم',
            ),
            new HadithMatch(
                text: 'اطلبوا العلم ولو بالصين',
                narrator: 'أنس بن مالك',
                book: 'شعب الإيمان',
                ruling: 'ضعيف جدا',
                takhrij: 'رواه البيهقي وضعّفه',
            ),
            new HadithMatch(
                text: 'حب الوطن من الإيمان',
                book: 'مقاصد الحسنة',
                ruling: 'موضوع لا أصل له',
                takhrij: 'ذكره السخاوي وقال لا أصل له',
            ),
            new HadithMatch(
                text: 'من عرف نفسه فقد عرف ربه',
                ruling: 'لا يصح مرفوعا',
                takhrij: 'قال النووي ليس بثابت',
            ),
        ]);
    }
}
