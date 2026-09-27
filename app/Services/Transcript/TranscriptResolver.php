<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Contracts\TranscriptProvider;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;

/**
 * Tries the sources in the order of §5-أ-2 — «وأوّل ناجحٍ يُعتمد».
 *
 * **الترتيب هو المنطق، والانتقال ليس إخفاقاً.** غيابُ ترجمة عربية
 * (`no_arabic_source`) ليس عطلاً يُبلَّغ به المستخدم، بل هو بعينه سببُ وجود
 * المسار الثالث. ولو عُومل كلّ إخفاقٍ وقوفاً لما عمل الجدول أصلاً.
 *
 * وثلاثةٌ تقف ولا تنتقل — {@see TranscriptErrorCode::haltsSourceFallback()}.
 *
 * **والحجب يُبلَّغ به آخراً لا أوّلاً:** يوتيوب يحجب عناوين مراكز البيانات
 * (§5-أ-6 البند ١)، فيُخفق المساران الأوّلان معاً بالرمز نفسه. والمُبلَّغ به
 * إخفاقُ آخر مصدرٍ جُرّب لا أوّلِه، لأنّ الأوّل كثيراً ما يكون انتقالاً
 * مقصوداً. ورمزُ الحجب يحمل {@see TranscriptErrorCode::fallsBackToManualPath()}
 * فيعرف المُستدعي أن يعرض نموذج اللصق.
 *
 * @see khulasah-build-spec.md §5-أ-2
 * @see khulasah-build-spec.md §5-أ-6
 */
class TranscriptResolver
{
    /**
     * @param  list<TranscriptProvider>  $providers  بترتيب جدول §5-أ-2.
     */
    public function __construct(private readonly array $providers) {}

    /**
     * @throws TranscriptFailed إن أخفقت المصادر كلّها.
     */
    public function resolve(TranscriptRequest $request): TranscriptResult
    {
        $lastFailure = null;
        $tried = 0;

        foreach ($this->providers as $provider) {
            // ما لا يُدعَم يُترك بلا إخفاق: غيابُ المدخل ليس عطلاً.
            if (! $provider->supports($request)) {
                continue;
            }

            $tried++;

            try {
                return $provider->fetch($request);
            } catch (TranscriptFailed $failure) {
                if ($failure->errorCode->haltsSourceFallback()) {
                    throw $failure;
                }

                $lastFailure = $failure;
            }
        }

        if ($lastFailure !== null) {
            throw $lastFailure;
        }

        // لم يُجرَّب مصدرٌ واحد: لا رابط ولا ملفّ ولا نصّ. وهذا خطأ إدخال
        // لا عطل تشغيل، ويُصلحه المستخدم بإعطاء مصدر.
        throw TranscriptFailed::because(
            TranscriptErrorCode::NoArabicSource,
            $tried === 0 ? 'لا مصدر صالح لهذه المحاضرة.' : 'أخفقت المصادر كلّها.',
        );
    }

    /** أسماء المصادر بترتيبها — للسجلّ والتشخيص. */
    public function order(): array
    {
        return array_map(
            static fn (TranscriptProvider $provider): string => $provider->name(),
            $this->providers,
        );
    }
}
