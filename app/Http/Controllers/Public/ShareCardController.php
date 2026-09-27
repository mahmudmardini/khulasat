<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Domain\Summary\JobState;
use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Models\SummaryJob;
use App\Support\Publish\ShareCard;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a published summary's share card to link-preview crawlers — T-144.
 *
 * ★ **يُجيب بصورةٍ دائماً لخلاصةٍ منشورة**: المولَّدةِ إن وُجدت، وإلّا
 * فبطاقةِ المنصّة بلسانها. فالوسمُ في الصفحة لا يُشير أبداً إلى فراغ —
 * سواءٌ أُطفئ الملتقِطُ، أو سقط، أو لم يبلغ دورَه في الطابور بعد.
 *
 * **ولخلاصةٍ غيرِ منشورة ٤٠٤**، لا بطاقةُ المنصّة: رابطٌ بقي في محادثةٍ
 * بعد إلغاء النشر أو الحذف لا يعرض عنوانَ ما سُحب.
 */
final class ShareCardController extends Controller
{
    public function __invoke(string $job, string $locale): Response
    {
        $lang = Locale::tryFrom($locale);

        $summary = $lang === null ? null : SummaryJob::acrossTenants()
            ->whereKey((int) $job)
            ->where('state', JobState::Published->value)
            ->whereNull('unpublished_at')
            ->first();

        abort_if($summary === null || $lang === null, 404);

        $disk = Storage::disk((string) config('khulasah.share_card.disk'));
        $path = ShareCard::path((int) $summary->id, $lang);

        $headers = [
            'Content-Type' => 'image/png',
            // يومٌ واحد: الرابطُ يتبدّل ببصمته إن تبدّلت البطاقة، والحذفُ
            // يُجاب بـ٤٠٤ بعد انقضائه.
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($disk->exists($path)) {
            return response((string) $disk->get($path), 200, $headers);
        }

        $fallback = ShareCard::fallbackFile($lang);

        abort_unless(is_file($fallback), 404);

        return response()->file($fallback, $headers);
    }
}
