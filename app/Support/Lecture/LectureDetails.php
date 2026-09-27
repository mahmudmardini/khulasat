<?php

declare(strict_types=1);

namespace App\Support\Lecture;

/**
 * بيانات المحاضرة كما استُخرجت — T-09ب.
 *
 * **وكلّ حقلٍ يقبل `null`، وهذا هو الحدّ الحاكم في هذه المهمّة:** ما تعذّر
 * استخراجه يبقى فارغاً ولا يُخمَّن. واختراعُ تاريخٍ أسوأ من تركه فارغاً،
 * **لأنّ مدير المحتوى يراجع الفارغ ولا يراجع ما بدا مملوءاً**.
 */
final readonly class LectureDetails
{
    public function __construct(
        public ?string $titleAr = null,
        public ?string $subtitleAr = null,
        public ?string $speakerName = null,
        public ?string $speakerTitle = null,
        public ?string $hijriDate = null,
        public ?string $gregorianDate = null,
        public ?string $weekday = null,
        public ?string $timeNote = null,
        public ?string $series = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $value = static function (mixed $raw): ?string {
            if (! is_string($raw)) {
                return null;
            }

            $trimmed = trim($raw);

            // النموذج يُخرج أحياناً «غير معروف» بدل `null` — وهي فراغٌ لا قيمة.
            return in_array($trimmed, ['', 'null', 'غير معروف', 'غير مذكور'], true) ? null : $trimmed;
        };

        return new self(
            titleAr: $value($data['title_ar'] ?? null),
            subtitleAr: $value($data['subtitle_ar'] ?? null),
            speakerName: $value($data['speaker_name'] ?? null),
            speakerTitle: $value($data['speaker_title'] ?? null),
            hijriDate: $value($data['hijri_date'] ?? null),
            gregorianDate: $value($data['gregorian_date'] ?? null),
            weekday: $value($data['weekday'] ?? null),
            timeNote: $value($data['time_note'] ?? null),
            series: $value($data['series'] ?? null),
        );
    }

    /**
     * الحقول كما تُعرض على مدير المحتوى ليصحّحها.
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'title_ar' => $this->titleAr,
            'subtitle_ar' => $this->subtitleAr,
            'speaker_name' => $this->speakerName,
            'speaker_title' => $this->speakerTitle,
            'hijri_date' => $this->hijriDate,
            'gregorian_date' => $this->gregorianDate,
            'weekday' => $this->weekday,
            'time_note' => $this->timeNote,
            'series' => $this->series,
        ];
    }

    /** أخرجت الصورة شيئاً أصلاً؟ صورةٌ ليست ملصقَ درسٍ تعود فارغةً كلَّها. */
    public function isEmpty(): bool
    {
        return array_filter($this->toArray(), static fn (?string $v): bool => $v !== null) === [];
    }
}
