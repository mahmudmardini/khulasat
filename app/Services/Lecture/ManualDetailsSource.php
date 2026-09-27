<?php

declare(strict_types=1);

namespace App\Services\Lecture;

use App\Contracts\LectureDetailsSource;
use App\Support\Lecture\LectureDetails;

/**
 * الإدخال اليدوي — تنفيذٌ من العقد نفسه.
 *
 * **ووجودُه هو ما يجعل فشلَ الاستخراج ارتداداً لا طريقاً مسدوداً.** فالشاشة
 * تتعامل مع مصدرٍ واحد، والصورة طريقٌ إليه لا بديلٌ عنه.
 */
class ManualDetailsSource implements LectureDetailsSource
{
    public function name(): string
    {
        return 'manual';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function extract(mixed $input): LectureDetails
    {
        return LectureDetails::fromArray((array) $input);
    }
}
