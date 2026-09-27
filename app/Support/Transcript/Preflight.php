<?php

declare(strict_types=1);

namespace App\Support\Transcript;

/**
 * What `yt-dlp --dump-json --no-download` told us — المواصفة §5-أ-1.
 *
 * نداءٌ رخيص لا يُنزّل شيئاً، **وعليه تُبنى كلّ قرارات الرفض قبل صرف مورد**:
 * المدّة والمضيف وقائمة الترجمات. والمواصفة §11 صريحة: الطول الزائد يُرفض
 * «قبل صرف أيّ توكن»، وهذا موضع الرفض.
 *
 * @see khulasah-build-spec.md §5-أ-1
 * @see khulasah-build-spec.md §5-أ-2
 */
final readonly class Preflight
{
    /**
     * لغات الترجمة العربية المقبولة: `ar` وصورها الإقليمية و`ar-orig`.
     *
     * و`ar-Latn` مستثنى: عربيةٌ بحرف لاتيني، أي نقلٌ صوتيّ لا نصّ عربي،
     * ولا يُطابَق به شاهد.
     */
    private const ARABIC_LANGUAGE = '/^ar(?:-(?!latn$)[a-z0-9]+)*$/i';

    /**
     * @param  array<string, list<array<string, mixed>>>  $subtitles
     * @param  array<string, list<array<string, mixed>>>  $automaticCaptions
     */
    public function __construct(
        public ?int $durationSeconds = null,
        public ?string $title = null,
        public ?string $channel = null,
        public ?string $uploadDate = null,
        public ?string $language = null,
        public array $subtitles = [],
        public array $automaticCaptions = [],
        public bool $isPlaylist = false,
    ) {}

    /**
     * @param  array<string, mixed>  $json  مخرَج `--dump-json` كما هو.
     */
    public static function fromDumpJson(array $json): self
    {
        return new self(
            durationSeconds: isset($json['duration']) ? (int) $json['duration'] : null,
            title: isset($json['title']) ? (string) $json['title'] : null,
            channel: isset($json['channel']) ? (string) $json['channel'] : null,
            uploadDate: isset($json['upload_date']) ? (string) $json['upload_date'] : null,
            language: isset($json['language']) ? (string) $json['language'] : null,
            subtitles: is_array($json['subtitles'] ?? null) ? $json['subtitles'] : [],
            automaticCaptions: is_array($json['automatic_captions'] ?? null) ? $json['automatic_captions'] : [],
            isPlaylist: ($json['_type'] ?? null) === 'playlist',
        );
    }

    /**
     * The Arabic track to use, by the priority in §5-أ-2 — or `null` for none.
     *
     * اليدويّة أوّلاً لأنّها مكتوبة بيد إنسان، ثم الآليّة. و`null` تعني
     * الانتقال إلى المسار الثالث (تفريغ الصوت) لا الإخفاق.
     */
    public function arabicTrack(): ?CaptionTrack
    {
        return $this->firstArabic($this->subtitles, isAutomatic: false)
            ?? $this->firstArabic($this->automaticCaptions, isAutomatic: true);
    }

    /** المواصفة §11 و§5-أ-1 الفحص الثاني: يُرفض قبل تنزيل بايت واحد. */
    public function exceedsLimitMinutes(int $maxMinutes): bool
    {
        if ($this->durationSeconds === null) {
            // مدّة مجهولة لا تُعدّ تجاوزاً: الرفض يحتاج دليلاً، والفحص
            // اللاحق على الملفّ بـ ffprobe يمسكها — §5-أ-4-ب.
            return false;
        }

        return $this->durationSeconds > $maxMinutes * 60;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $tracks
     */
    private function firstArabic(array $tracks, bool $isAutomatic): ?CaptionTrack
    {
        foreach ($tracks as $languageCode => $formats) {
            if (preg_match(self::ARABIC_LANGUAGE, (string) $languageCode) !== 1) {
                continue;
            }

            if ($isAutomatic && $this->isTranslated($formats)) {
                continue;
            }

            $format = self::preferredFormat($formats);

            return new CaptionTrack(
                languageCode: (string) $languageCode,
                isAutomatic: $isAutomatic,
                ext: isset($format['ext']) ? (string) $format['ext'] : null,
                url: isset($format['url']) ? (string) $format['url'] : null,
            );
        }

        return null;
    }

    /**
     * Whether an automatic track is a machine translation of another language.
     *
     * **هذا أخطر فحص في §5-أ-2.** يوتيوب يعرض ترجمةً آليّة عربيةً لكلّ فيديو
     * تقريباً — ومنه الإنجليزي — بترجمة نصّ التعرّف الآلي إلى العربية. فلو
     * أُخذت لخرج نصّ درسٍ إنجليزي «عربياً»، وفيه ألفاظ أحاديث مترجمةً عن
     * ترجمة. والمواصفة تمنعه صراحةً: «لا تُقبل ترجمة مترجَمة آلياً عن لغة
     * أخرى»، ومنعُه هنا هو ما يمنع نشر لفظٍ لم يقله أحد.
     *
     * والفاصل `tlang=` في عنوان المسار، وهو معامل الترجمة عند يوتيوب: المسار
     * الأصلي لا يحمله، والمترجَم لا يخلو منه. ولغة الفيديو المعلنة قرينةٌ
     * ثانية حين تُذكر.
     *
     * @param  list<array<string, mixed>>  $formats
     */
    private function isTranslated(array $formats): bool
    {
        $urls = array_filter(array_map(
            static fn (array $format): string => (string) ($format['url'] ?? ''),
            $formats,
        ), static fn (string $url): bool => $url !== '');

        foreach ($urls as $url) {
            if (! str_contains($url, 'tlang=')) {
                // مسارٌ واحد بلا معامل ترجمة يكفي: هو الأصل.
                return false;
            }
        }

        if ($urls !== []) {
            // عناوينُ كلّها مترجَمة.
            return true;
        }

        // بلا عنوانٍ يُفحَص لا دليلَ على الترجمة، فتبقى لغة الفيديو المعلنة
        // قرينةً. وغيابها لا يُعدّ ترجمةً: الرفض يحتاج دليلاً.
        return $this->language !== null
            && preg_match(self::ARABIC_LANGUAGE, $this->language) !== 1;
    }

    /**
     * ‏`--convert-subs srt` يحوّل أيّ صيغة، فتُقدَّم srt ثم vtt ثم أوّل متاح.
     *
     * @param  list<array<string, mixed>>  $formats
     * @return array<string, mixed>
     */
    private static function preferredFormat(array $formats): array
    {
        foreach (['srt', 'vtt'] as $wanted) {
            foreach ($formats as $format) {
                if (($format['ext'] ?? null) === $wanted) {
                    return $format;
                }
            }
        }

        return $formats[0] ?? [];
    }
}
