<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Transcript\YtDlp;
use App\Support\Transcript\SourceUrlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The cheap look before anything is spent — SCREENS.md §3-أ، والمواصفة §5-أ-1.
 *
 * **يُشغَّل قبل أي صرف موارد**، ويعرض خلال ثوانٍ العنوان والمدّة وهل توجد
 * ترجمة عربية. **وإن تجاوزت المدّة حدّ الاشتراك ظهرت الرسالة هنا لا بعد
 * البدء** — والفرق بينهما أنّ الثانية تُغضب من انتظر ثمّ عرف.
 *
 * ولا ينزّل بايتاً من الفيديو: قراءة بيانات وصفية وحدها.
 */
class PreflightController extends Controller
{
    public function __invoke(Request $request, YtDlp $ytdlp): JsonResponse
    {
        $request->validate(['source_url' => ['required', 'string', 'max:2048']]);

        $url = $request->string('source_url')->toString();

        try {
            // ★ الحاجز قبل النداء — §12 المخطر الثالث. ومدخلُ المستخدم لا
            //   يُمرَّر إلى عملية خارجية قبل أن يُفحص مضيفه.
            SourceUrlGuard::assertAllowed($url);
        } catch (Throwable) {
            return response()->json([
                'ok' => false,
                'message' => trans('errors.transcript.host_not_allowed'),
            ], 422);
        }

        if (SourceUrlGuard::isPlaylist($url)) {
            return response()->json([
                'ok' => false,
                'message' => trans('errors.transcript.playlist_given'),
            ], 422);
        }

        try {
            $preflight = $ytdlp->preflight($url);
        } catch (Throwable) {
            // **ولا يُعرض سبب الإخفاق التقني.** المستخدم يحتاج ما يفعله،
            // لا رسالةَ أداةٍ لا يعرفها.
            return response()->json([
                'ok' => false,
                'message' => trans('lectures.create.preflight.unavailable'),
            ], 422);
        }

        $limitMinutes = (int) ($request->user()?->tenant?->max_lecture_minutes ?? 0);
        $minutes = $preflight->durationSeconds === null
            ? null
            : (int) ceil($preflight->durationSeconds / 60);

        return response()->json([
            'ok' => true,
            'title' => $preflight->title,
            'duration_minutes' => $minutes,
            'has_arabic_captions' => $preflight->arabicTrack() !== null,
            'limit_minutes' => $limitMinutes,
            // يُحسم في الخادم لا في المتصفّح: الحدّ من الاشتراك، وحسابُه
            // في الواجهة يجعله قابلاً للتعديل من أدوات المطوّر.
            'exceeds_limit' => $limitMinutes > 0 && $preflight->exceedsLimitMinutes($limitMinutes),
        ]);
    }
}
