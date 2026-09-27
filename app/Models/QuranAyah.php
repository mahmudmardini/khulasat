<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Arabic;
use Illuminate\Database\Eloquent\Model;

/**
 * One ayah of the Qur'an — Hafs 'an 'Asim.
 *
 * **لا يحمل BelongsToTenant**: المصحف واحد لكل الجهات، وليس بيانات مستأجر.
 */
class QuranAyah extends Model
{
    protected $table = 'quran_ayat';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'surah' => 'integer',
            'ayah' => 'integer',
        ];
    }

    public function reference(): string
    {
        return "سورة {$this->surah_name_ar}، الآية ".Arabic::toArabicIndicDigits($this->ayah);
    }
}
