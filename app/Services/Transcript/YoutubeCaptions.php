<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Enums\TranscriptSource;
use App\Exceptions\PlaylistUrlGiven;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\Preflight;
use App\Support\Transcript\SourceUrlGuard;
use App\Support\Transcript\SrtConverter;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;

/**
 * Lecture text from the platform's own captions — المواصفة §5-أ، المسار ١ و٢.
 *
 * **أرخص المسارات وأوّلها**: نصٌّ جاهز بلا تفريغ صوتي، فلا يُحتسب من
 * `transcription_minutes_quota` — §11.
 *
 * والترتيب هنا هو المواصفة نفسها:
 *   ١. حاجزُ الرابط قبل كلّ شيء — §12: قائمة السماح ثم العناوين الداخلية.
 *   ٢. الفحص المسبق `--dump-json`، **ومنه يُرفض الطول قبل تنزيل بايت واحد**.
 *   ٣. أولوية المصادر: يدويّة ثم آليّة، ولا مترجَمة عن لغة أخرى — §5-أ-2.
 *   ٤. تحويل SRT بإزالة التداخل — §5-أ-3.
 *   ٥. حاجزُ الطول الأدنى **قبل صرف أيّ توكن على النماذج** — §5-أ-7.
 *
 * وما ليس هنا: مسارُ الصوت والمسارُ اليدوي، وهما T-09.
 *
 * @see khulasah-build-spec.md §5-أ
 */
class YoutubeCaptions implements TranscriptProvider
{
    public function __construct(private readonly YtDlp $ytDlp) {}

    public function name(): string
    {
        return 'youtube_captions';
    }

    public function supports(TranscriptRequest $request): bool
    {
        // محتوىً قدّمه المستخدم يتقدّم على المنصّة: من لصق نصّاً أو رفع ملفّاً
        // لا يُذهب به إلى يوتيوب.
        if ($request->hasPastedText() || $request->hasUpload()) {
            return false;
        }

        if (! $request->hasSourceUrl()) {
            return false;
        }

        $url = $request->sourceUrl();

        // فحصٌ رخيص بلا نداء شبكة. وما لا يُدعَم يُترك للتالي في الصفّ.
        try {
            SourceUrlGuard::assertAllowed($url);
        } catch (TranscriptFailed) {
            return false;
        }

        return ! SourceUrlGuard::isPlaylist($url);
    }

    /**
     * @throws TranscriptFailed
     * @throws PlaylistUrlGiven
     */
    public function fetch(TranscriptRequest $request): TranscriptResult
    {
        $url = $request->sourceUrl();

        // ١ و٣. الفحصان الأوّل والثالث من §5-أ-1 — قبل أيّ نداء.
        SourceUrlGuard::assertAllowed($url);

        if (SourceUrlGuard::isPlaylist($url)) {
            throw PlaylistUrlGiven::forUrl($url);
        }

        // ٢. النداء الرخيص. لا يُنزّل شيئاً.
        $preflight = $this->ytDlp->preflight($url);

        if ($preflight->isPlaylist) {
            // قائمةٌ لم يُظهرها شكلُ الرابط. و`--no-playlist` لا يكفي: يأخذ
            // الفيديو الأوّل صامتاً، فيُلخَّص درسٌ غير الذي قصده المستخدم.
            throw PlaylistUrlGiven::forUrl($url);
        }

        $this->assertWithinDuration($request, $preflight);

        // ٣. أولوية §5-أ-2. و`null` تعني الانتقال إلى تفريغ الصوت (المسار ٣)
        //    لا الإخفاق — ورسالةُ الرمز تقول ذلك للمستخدم.
        $track = $preflight->arabicTrack();

        if ($track === null) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::NoArabicSource,
                'لا مسار ترجمة عربياً أصلياً في هذا الفيديو.',
            );
        }

        // ٤. التحويل بإزالة التداخل. ومن ينسخ الملفّ كما هو يدفع ضعف الكلفة.
        $text = SrtConverter::toText($this->ytDlp->fetchCaptions($url, $track));

        $result = new TranscriptResult($text, TranscriptSource::Captions, $track);

        // ٥. **الحاجز الأخير قبل النماذج.** نصٌّ ناقص يُنتج ملخّصاً ناقصاً
        //    بكلفة تامّة، فيُرفع لمدير المحتوى ولا يُصرف عليه توكن.
        if ($result->isTooShort()) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::TranscriptTooShort,
                "النصّ {$result->wordCount()} كلمة، والحدّ ".TranscriptErrorCode::MINIMUM_WORDS.'.',
            );
        }

        return $result;
    }

    /**
     * المواصفة §5-أ-1 الفحص الثاني و§11: **يُرفض هنا قبل تنزيل بايت واحد.**
     *
     * @throws TranscriptFailed
     */
    private function assertWithinDuration(TranscriptRequest $request, Preflight $preflight): void
    {
        $maxMinutes = $request->maxLectureMinutes();

        if ($maxMinutes <= 0) {
            return;
        }

        if ($preflight->exceedsLimitMinutes($maxMinutes)) {
            throw TranscriptFailed::because(
                TranscriptErrorCode::DurationExceeded,
                "مدّة الفيديو {$preflight->durationSeconds} ثانية، والحدّ {$maxMinutes} دقيقة.",
            );
        }
    }
}
