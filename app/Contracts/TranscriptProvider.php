<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\TranscriptSource;
use App\Exceptions\TranscriptFailed;
use App\Support\Transcript\TranscriptRequest;
use App\Support\Transcript\TranscriptResult;

/**
 * A source of lecture text — المواصفة §5-أ.
 *
 * ثلاثة تنفيذات: `YoutubeCaptions` (T-08)، و`WhisperAudio` و`ManualUpload`
 * (T-09). وتُجرَّب بأولوية جدول §5-أ-2، وأوّل ناجحٍ يُعتمد.
 *
 * **والمسار اليدوي آخرُ الصفّ ولا يُخفق أبداً** — §5-أ-5: «يبقى متاحاً دائماً،
 * وليس حالة طوارئ فقط… وهو ما يُنقذ الجهة حين يُخفق كلّ ما سبق». فالعقد
 * مبنيّ على أنّ الإخفاق مرحلةٌ لا نهاية.
 *
 * وسُمّي `TranscriptProvider` لا `TranscriptSource` كما في نصّ المواصفة:
 * الاسم الثاني مأخوذٌ لـ {@see TranscriptSource}، وهو عمود
 * `summary_jobs.transcript_source`. والتنفيذ الواحد يحتاج الاثنين معاً،
 * فيُسمّى العقد على وزن {@see HadithProvider} ويبقى التعداد لقيمة العمود.
 *
 * @see khulasah-build-spec.md §5-أ
 */
interface TranscriptProvider
{
    /** اسمه في السجلّ والقياس. */
    public function name(): string;

    /**
     * Whether this source can be tried for this request at all.
     *
     * فحصٌ رخيص بلا نداء شبكة ولا قراءة ملفّ كبير: أهنا مدخلي أصلاً؟
     * وما لا يُدعَم يُترك للتالي في الصفّ **بلا إخفاق ولا رمز خطأ** — فغيابُ
     * المدخل ليس عطلاً، وإنّما هو معنى الترتيب في §5-أ-2.
     */
    public function supports(TranscriptRequest $request): bool;

    /**
     * @throws TranscriptFailed بأحد أكواد §5-أ-7.
     */
    public function fetch(TranscriptRequest $request): TranscriptResult;
}
