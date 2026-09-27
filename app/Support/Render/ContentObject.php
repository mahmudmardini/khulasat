<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Enums\Locale;
use App\Enums\ReviewStatus;
use App\Enums\VenueMode;
use App\Models\EvidenceItem;
use App\Models\SummaryJob;

/**
 * الأصل الذي ترسم منه كلّ العارضات — المواصفة §8-أ.
 *
 * `structure_json` + الشواهد **المحسومة بألفاظ مصادرها** + بيانات المجلس
 * والجهة. **ولا عارض يعدّله** — يقرؤه ويرسم.
 *
 * وبناؤه من المهمّة يمرّ بحارسٍ واحد: **لا يُبنى وفي المهمّة شاهدٌ لم
 * يُحسم**. فالحدّ الرابع لا يُتجاوز، ولا يُرسَم ما لم يُحسم.
 */
final readonly class ContentObject
{
    /**
     * @param  array<string, mixed>  $structure
     * @param  list<RenderedEvidence>  $evidence
     * @param  array<string, mixed>  $majlis
     */
    public function __construct(
        public array $structure,
        public array $evidence,
        public array $majlis,
        public ?string $bodyHtml = null,
        /*
         * رقم المهمّة — **لشاهدة العدّ وحدها** (T-31).
         *
         * ولا يُقرأ منه محتوى: هو مفتاح العدّاد لا غير. ومن أراد ألّا
         * تُعدّ نسخةٌ من الصفحة نزعه بـ{@see self::withoutBeacon()}.
         */
        /*
         * لغةُ هذا المخرَج — T-38. **والافتراض لغةُ المصدر**: كلُّ ما رُسم
         * قبل هذه المهمّة عربيّ، فلا مخرَجَ يحتاج تخميناً.
         */
        public Locale $locale = Locale::Ar,
        public ?int $summaryJobId = null,
        /*
         * بيانات الصفحة — مخرَج المرحلة ٦، T-57. وتفرغ حين تسقط المرحلة،
         * فيسقط الرأسُ إلى العنوان وحده ولا تسقط الصفحة.
         *
         * @var array<string, mixed>
         */
        public array $outputMeta = [],
    ) {}

    /**
     * النسخة نفسها **بلا شاهدة عدّ** — T-31.
     *
     * ★ **ومن عاين صفحته عشراً لا يرى «عشر زيارات» وما زارها أحد.**
     *
     * فالمعاينة (§6) تُرسم من المهمّة نفسها بالقالب نفسه، ولو حملت الشاهدة
     * **لعدّت كلَّ فتحةِ معاينةٍ زيارةً** — فيصير العدّاد مرآةً لصاحب
     * الصفحة لا لقرّائها. وكذلك الملفّ المنزَّل: يُفتح من قرصٍ لا من الشبكة.
     */
    public function withoutBeacon(): self
    {
        return new self(
            structure: $this->structure,
            evidence: $this->evidence,
            majlis: $this->majlis,
            bodyHtml: $this->bodyHtml,
            locale: $this->locale,
            summaryJobId: null,
            outputMeta: $this->outputMeta,
        );
    }

    /**
     * @param  Locale  $locale  لغةُ المخرَج — T-38.
     *
     * **ولغةٌ غير المصدر تُبنى من `summary_translations`**، فإن لم تُترجَم
     * بعدُ سقطت إلى العربيّ. وسقوطٌ إلى الأصل أهونُ من صفحةٍ فارغة: القارئ
     * يجد ملخّصاً كاملاً بلغةٍ غير التي طلب، لا لا شيء.
     */
    public static function fromJob(SummaryJob $job, Locale $locale = Locale::Ar): self
    {
        $lecture = $job->lecture;

        /*
         * ★ **تُقرأ باستعلامٍ لا من العلاقة المخزَّنة** — T-63.
         *
         * فـ`RenderAndPublish` يرسم اللغة الأولى ثمّ يترجم الثانية، فتُحمَّل
         * `$job->translations` وتُخزَّن في الكائن **قبل** أن تُكتب الثانية.
         * فلا تُرى، فيسقط العارضُ إلى العربية صامتاً، ويُنشر ملفٌّ عربيٌّ
         * باسم لغةٍ أخرى — **بعد أن دُفع ثمنُ ترجمتها**.
         *
         * ولم يظهر قبلُ لأنّ العربية كانت أولى دائماً: `isSource()` تسبق
         * قراءة العلاقة، فلا تُحمَّل، فتُقرأ طازجةً لما بعدها.
         */
        $translation = $locale->isSource() ? null : $job->translations()
            ->where('locale', $locale->value)
            ->first();

        // ترجمةٌ بلا متنٍ ليست ترجمة — فتُهمَل ويُعرض الأصل.
        if ($translation !== null && trim((string) $translation->body_html) === '') {
            $translation = null;
        }

        $effective = $translation === null ? Locale::Ar : $locale;

        return new self(
            structure: (array) ($job->structure_json ?? []),
            evidence: self::publishable($job),
            majlis: [
                // العنوان من مخرَج المرحلة الثانية إن وُجد، وإلّا فما أدخله
                // مدير المحتوى. فالنموذج يصوغ عنواناً أدقّ، ولا يُفترض وجوده.
                'title' => $translation?->title
                    ?? $job->structure_json['title_ar'] ?? $lecture?->title_ar,
                'subtitle' => $translation?->subtitle
                    ?? $job->structure_json['subtitle_ar'] ?? $lecture?->subtitle_ar,
                'sheikh' => $lecture?->speaker_name,
                'sheikh_full' => trim(($lecture?->speaker_title ?? '').' '.($lecture?->speaker_name ?? '')) ?: $lecture?->speaker_name,
                'weekday' => $lecture?->weekday,
                'date_gregorian' => $lecture?->gregorian_date?->format('Y/m/d'),
                'date_hijri' => $lecture?->hijri_date,
                'time_note' => $lecture?->time_note,
                'venue_mode' => $lecture?->venue_mode ?? VenueMode::default(),
                'source_url' => $lecture?->source_url,
                // `closing_line` عمداً غير موجود هنا: سقالةٌ لصياغة المرحلة ٥
                // (`WriteBody` تقرؤه من `structure_json` مباشرة)، لا نصٌّ للعرض.
                // النصّ المعروض هو `body.closing` نفسه، مدموجٌ في `bodyHtml`.
            ],
            bodyHtml: $translation?->body_html ?? $job->body_html,
            locale: $effective,
            summaryJobId: $job->id === null ? null : (int) $job->id,
            outputMeta: (array) ($job->output_meta_json ?? []),
        );
    }

    /**
     * الشواهد التي تدخل العرض — **بألفاظ مصادرها**.
     *
     * والمحذوفة لا تدخل: أُخرجت من المتن، فإدخالها في قائمة التخريج يُعيدها
     * من الباب الذي أُخرجت منه.
     *
     * @return list<RenderedEvidence>
     */
    private static function publishable(SummaryJob $job): array
    {
        return $job->evidenceItems()
            ->whereNot('review_status', ReviewStatus::Removed->value)
            ->get()
            ->map(static fn (EvidenceItem $item): RenderedEvidence => RenderedEvidence::fromItem($item))
            ->values()
            ->all();
    }
}
