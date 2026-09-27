<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HadithBook;
use App\Enums\HadithGrade;
use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;

/**
 * One narration of the seeded corpus — T-05ب.
 *
 * **لا يحمل BelongsToTenant**: المدوّنة واحدة لكلّ الجهات كالمصحف، وليست
 * بيانات مستأجر.
 *
 * @property string $book
 * @property string $hadith_number
 * @property string $text
 * @property string $text_plain
 * @property string $text_normalized
 * @property string $takhrij
 */
class Hadith extends Model
{
    protected $table = 'hadith_corpus';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'grade' => HadithGrade::class,
            'graders_json' => 'array',
        ];
    }

    public function bookEnum(): HadithBook
    {
        return HadithBook::from($this->book);
    }

    /** «صحيح البخاري، رقم ٦٤٦٤» — المرجع كما يُعرض. */
    public function reference(): string
    {
        return $this->bookEnum()->title().'، رقم '.Arabic::toArabicIndicDigits($this->hadith_number);
    }
}
