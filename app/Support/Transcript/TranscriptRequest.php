<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Models\Lecture;

/**
 * What a transcript is being asked for — المواصفة §5-أ.
 *
 * **لماذا كائنٌ لا محاضرةٌ وحدها:** مسارات §5-أ-2 الخمسة لا تقرأ كلّها من
 * `lectures`. المساران الأوّل والثاني يقرآن `source_url`، والرابع ملفّاً
 * رُفع من الجهاز، **والخامس نصّاً يلصقه المستخدم لا موضع له في الجدول
 * أصلاً**. فلو كان المدخل محاضرةً وحدها لبقي المسار اليدوي بلا مدخل، وهو
 * المسار الذي «يُنقذ الجهة حين يُخفق كلّ ما سبق» — §5-أ-5.
 *
 * @see khulasah-build-spec.md §5-أ-2
 */
final readonly class TranscriptRequest
{
    public function __construct(
        public Lecture $lecture,
        /** نصّ يلصقه المستخدم — المسار الخامس. */
        public ?string $pastedText = null,
        /** مسار ملفّ رُفع من الجهاز: صوت أو فيديو أو ترجمة — المسار الرابع والخامس. */
        public ?string $uploadedPath = null,
        /** اسمه كما رفعه المستخدم، للاحقة وحدها. **والنوع يُفحص بالمحتوى لا به.** */
        public ?string $uploadedName = null,
    ) {}

    public static function for(Lecture $lecture): self
    {
        return new self($lecture);
    }

    public function withPastedText(string $text): self
    {
        return new self($this->lecture, pastedText: $text);
    }

    public function withUploadedFile(string $path, ?string $originalName = null): self
    {
        return new self(
            $this->lecture,
            uploadedPath: $path,
            uploadedName: $originalName ?? basename($path),
        );
    }

    public function sourceUrl(): string
    {
        return trim((string) ($this->lecture->source_url ?? ''));
    }

    public function hasSourceUrl(): bool
    {
        return $this->sourceUrl() !== '';
    }

    public function hasPastedText(): bool
    {
        return trim((string) $this->pastedText) !== '';
    }

    public function hasUpload(): bool
    {
        return $this->uploadedPath !== null && is_file($this->uploadedPath);
    }

    /** حدّ الجهة بالدقائق — §11، ويُفحص قبل أيّ معالجة. */
    public function maxLectureMinutes(): int
    {
        return (int) ($this->lecture->tenant->max_lecture_minutes ?? 0);
    }
}
