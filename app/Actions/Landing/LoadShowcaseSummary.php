<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Enums\Locale;
use App\Enums\MatchStatus;
use App\Enums\OutputType;
use App\Enums\ReviewStatus;
use App\Models\EvidenceItem;
use App\Models\Output;
use App\Models\SummaryJob;
use App\Support\Landing\Showcase;
use App\Support\Quran\AyahText;
use App\Support\Render\RenderedEvidence;
use Illuminate\Support\Str;

/**
 * The live example shown in the landing page's hero and "what you get" — T-143.
 *
 * ★ **قرارُ مالك المنتج، ١٦ أيلول ٢٠٢٦، ينسخ بنداً من T-113.** كانت الصفحة
 * «صفرَ بياناتٍ حقيقية»، فكان القسمُ الذي بُني ليُثبت المنتجَ مواضعَ موصوفة
 * يقرؤها الزائرُ هيكلاً لم يكتمل. والآن تُعرض الخلاصةُ المنشورة المهيّأة
 * (T-119) نفسُها، وتبقى المواضعُ احتياطاً حين يعيد هذا `null`.
 *
 * ★ **ولا يُعرض إلّا شاهدٌ طُوبِق.** صفحةُ التعريف تَعِد بأنّ كلّ شاهدٍ بلفظ
 * مصدره، فشاهدٌ أُثبت بقرارٍ بشريّ وهو غيرُ مطابَق — وهو مشروعٌ في
 * الخلاصة نفسها — يُكذّب الوعدَ في الموضع الذي يُقدَّم فيه برهاناً عليه.
 */
final class LoadShowcaseSummary
{
    /** يكفي البرهانَ أربعةٌ، ويُثقل الصفحةَ ما زاد. */
    private const EVIDENCE_LIMIT = 4;

    /**
     * ★ **الشاهدُ لا يُقصّ، يُتخطّى.** الفكرةُ الحاكمة كلامُنا فتُقصَر،
     * أمّا آيةٌ أو حديثٌ مبتورٌ فنقلٌ ناقصٌ في الموضع الذي نُثبت فيه الأمانة.
     * فما طال عن اللوح تُرك لغيره.
     */
    private const EVIDENCE_MAX_CHARS = 260;

    /** الفكرةُ الحاكمة تُقرأ في لوحٍ صغير، فتُقصَر على ما يتّسع له. */
    private const CONCEPT_LIMIT = 230;

    public function __construct(
        private readonly FindShowcaseSummaryJob $findJob = new FindShowcaseSummaryJob,
    ) {}

    public function handle(Locale $locale): ?Showcase
    {
        $job = $this->findJob->handle();

        if ($job === null) {
            return null;
        }

        $url = $this->url($job, $locale);

        if ($url === null) {
            return null;
        }

        $lecture = $job->lecture;
        $structure = (array) ($job->structure_json ?? []);

        $translation = $locale->isSource() ? null : $job->translations()
            ->where('locale', $locale->value)
            ->first();

        $translatedTitle = trim((string) $translation?->title);
        $title = $translatedTitle !== ''
            ? $translatedTitle
            : trim((string) ($structure['title_ar'] ?? $lecture?->title_ar ?? ''));

        if ($title === '') {
            return null;
        }

        $evidence = $this->matchedEvidence($job);
        $keyAyah = null;

        foreach ($evidence as $index => $item) {
            if ($item->kind === 'ayah') {
                $keyAyah = $item;
                unset($evidence[$index]);

                break;
            }
        }

        $axes = array_values(array_filter(
            (array) ($structure['axes'] ?? []),
            static fn (mixed $axis): bool => is_array($axis) && trim((string) ($axis['name'] ?? '')) !== '',
        ));

        $tenant = $job->tenant;

        return new Showcase(
            url: $url,
            title: $title,
            titleLocale: $translatedTitle !== '' ? $locale : Locale::Ar,
            subtitle: $this->text($translatedTitle !== ''
                ? $translation?->subtitle
                : ($structure['subtitle_ar'] ?? $lecture?->subtitle_ar)),
            speaker: $this->text(trim(($lecture?->speaker_title ?? '').' '.($lecture?->speaker_name ?? ''))),
            // اسمُ الجهة اللاتينيّ في الصفحة اللاتينية إن وُجد — اسمُ علَمٍ
            // يُكتب بحروف قارئه، ولا يُترجَم.
            venue: $this->text($locale->isSource()
                ? ($tenant?->name_ar ?? $tenant?->name_ar_full)
                : ($tenant?->name_latin ?? $tenant?->name_ar)),
            keyAyah: $keyAyah,
            axisName: $this->text($axes[0]['name'] ?? null),
            coreConcept: $this->clip($structure['core_concept'] ?? null),
            secondAxisName: $this->text(($axes[1] ?? $axes[0] ?? [])['name'] ?? null),
            secondAxisSummary: $this->clip(($axes[1] ?? $axes[0] ?? [])['summary'] ?? null),
            evidence: array_slice(array_values($evidence), 0, self::EVIDENCE_LIMIT),
        );
    }

    /**
     * رابطُ الخلاصة بلسان الصفحة إن نُشرت به، وإلّا فأقصرُ روابطها — الجذر.
     */
    private function url(SummaryJob $job, Locale $locale): ?string
    {
        $outputs = Output::query()
            ->where('summary_job_id', $job->id)
            ->where('type', OutputType::Page)
            ->whereNotNull('public_url')
            ->get();

        $own = $outputs->first(fn (Output $output): bool => $output->locale === $locale);

        return $own?->public_url ?? $outputs
            ->sortBy(fn (Output $output): int => mb_strlen((string) $output->public_url))
            ->first()
            ?->public_url;
    }

    /** @return list<RenderedEvidence> */
    private function matchedEvidence(SummaryJob $job): array
    {
        return $job->evidenceItems()
            ->where('match_status', MatchStatus::Exact->value)
            ->whereIn('review_status', [ReviewStatus::AutoPassed->value, ReviewStatus::Approved->value])
            ->whereNotNull('matched_text')
            ->where('matched_text', '!=', '')
            ->orderBy('id')
            ->get()
            ->map(static fn (EvidenceItem $item): RenderedEvidence => RenderedEvidence::fromItem($item))
            ->filter(static fn (RenderedEvidence $item): bool => mb_strlen(AyahText::plain($item->text)) <= self::EVIDENCE_MAX_CHARS)
            ->values()
            ->all();
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function clip(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : Str::limit($value, self::CONCEPT_LIMIT, '…', preserveWords: true);
    }
}
