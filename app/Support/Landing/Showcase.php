<?php

declare(strict_types=1);

namespace App\Support\Landing;

use App\Enums\Locale;
use App\Support\Render\RenderedEvidence;

/**
 * A real published summary, reduced to what the landing page shows of it — T-143.
 *
 * **ولغتان لا واحدة.** عنوانُ الخلاصة يأتي بلسان صفحة التعريف إن نُشرت
 * بها، وإلّا فبالعربية (`$titleLocale`). وبنيةُ الدرس — المحورُ والفكرةُ
 * الحاكمة — عربيّةٌ دائماً: تُستخرج مرّةً من المحاضرة ولا تُترجم. فيعرف
 * القالبُ أيَّ سطرٍ يوسَم عربياً في صفحةٍ لاتينية.
 */
final readonly class Showcase
{
    /**
     * @param  list<RenderedEvidence>  $evidence  شواهدُ طُوبِقت فقط، والآيةُ المفتاح ليست منها.
     */
    public function __construct(
        public string $url,
        public string $title,
        public Locale $titleLocale,
        public ?string $subtitle,
        public ?string $speaker,
        public ?string $venue,
        public ?RenderedEvidence $keyAyah,
        public ?string $axisName,
        public ?string $coreConcept,
        public ?string $secondAxisName,
        public ?string $secondAxisSummary,
        public array $evidence,
    ) {}

    /** The hadith (or other citation) shown under the hero's first section. */
    public function heroQuote(): ?RenderedEvidence
    {
        foreach ($this->evidence as $item) {
            if ($item->kind !== 'ayah') {
                return $item;
            }
        }

        return $this->evidence[0] ?? null;
    }
}
