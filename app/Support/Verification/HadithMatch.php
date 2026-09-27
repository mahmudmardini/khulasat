<?php

declare(strict_types=1);

namespace App\Support\Verification;

use App\Enums\HadithBook;
use App\Enums\HadithGrade;

/**
 * One candidate hadith returned by a provider.
 */
final readonly class HadithMatch
{
    /**
     * @param  ?HadithGrade  $grade  الحكم مقروءاً — تضعه المدوّنة المبذورة وقت
     *                               البذر. وما لم يضعه يُقرأ من `$ruling` نصّاً.
     * @param  ?HadithBook  $bookKey  الكتاب معرَّفاً لا مسمّى — عليه يُبنى
     *                                التخريج و«متّفقٌ عليه».
     * @param  list<array{name: string, grade: string}>  $graders  المحكِّمون كلّهم
     *                                                             بألفاظهم — §7-3 البند ٤.
     */
    public function __construct(
        public string $text,
        public ?string $narrator = null,
        public ?string $book = null,
        public ?string $hadithNumber = null,
        public ?string $ruling = null,
        public ?string $takhrij = null,
        public ?HadithGrade $grade = null,
        public ?HadithBook $bookKey = null,
        public array $graders = [],
    ) {}

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            text: (string) ($row['text'] ?? ''),
            narrator: $row['narrator'] ?? null,
            book: $row['book'] ?? null,
            hadithNumber: isset($row['hadith_number']) ? (string) $row['hadith_number'] : null,
            ruling: $row['ruling'] ?? null,
            takhrij: $row['takhrij'] ?? null,
            grade: isset($row['grade']) ? HadithGrade::from((string) $row['grade']) : null,
            bookKey: isset($row['book_key']) ? HadithBook::from((string) $row['book_key']) : null,
            graders: $row['graders'] ?? [],
        );
    }

    /**
     * الحكم كما يُعتمد: المقروء وقت البذر، وإلّا قراءةُ نصّ الحكم.
     *
     * **والمدوّنة المبذورة تُقدَّم** لأنّ أحكامها لاتينية مقنَّنة تُخرَّج بجدول،
     * أمّا `fromRuling` فتقرأ عربيةً حرّة — وهي للمزوّدين الذين يعطون نثراً.
     */
    public function resolvedGrade(): HadithGrade
    {
        return $this->grade ?? HadithGrade::fromRuling($this->ruling);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'narrator' => $this->narrator,
            'book' => $this->book,
            'hadith_number' => $this->hadithNumber,
            'ruling' => $this->ruling,
            'takhrij' => $this->takhrij,
            'grade' => $this->grade?->value,
            'book_key' => $this->bookKey?->value,
            'graders' => $this->graders,
        ];
    }
}
