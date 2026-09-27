<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\Lecture\LectureDetails;

/**
 * مصدرُ بيانات المحاضرة — T-09ب.
 *
 * **ولا تُقحَم الصورة في `TranscriptSource`.** هذه بيانات المحاضرة لا نصُّها:
 * مسارُها ومخرَجُها ووقتُها مختلفة، وهي تجري **قبل** الخطّ لا داخله.
 * ودمجُهما يجعل عقداً واحداً يُجيب سؤالين.
 *
 * والإدخال اليدوي تنفيذٌ منه أيضاً، فيكون المسار واحداً ويكون فشلُ
 * الاستخراج ارتداداً لا طريقاً مسدوداً.
 */
interface LectureDetailsSource
{
    public function name(): string;

    public function extract(mixed $input): LectureDetails;
}
