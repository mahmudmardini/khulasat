<?php

declare(strict_types=1);

namespace App\Support\Render;

use App\Contracts\ShareCardCapturer;
use App\Models\SummaryJob;
use App\Services\ShareCard\NullShareCardCapturer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * حزمةُ صور الكاروسيل: مقاسُها، ومواضعُ ملفّاتها — T-173.
 *
 * **في قرصٍ خاصّ لا في مخزن النشر**: الحزمة تُنزَّل ولا تُنشر (§9). ولا تُكتب
 * في `outputs.storage_path`، فذاك يمسحه إلغاءُ النشر من مخزن النشر
 * (`UnpublishSummary`)، والحزمةُ ليست هناك. فمواضعُها تُشتقّ من هنا وحده.
 */
final class ImageSet
{
    /** مقاسُ إنستغرام الموصى به، 4:5 — وهو مقاسُ الشريحة في القالب. */
    public const WIDTH = 1080;

    public const HEIGHT = 1350;

    public const VERSION = '1.1.0';

    /**
     * شرائحُ اللقطة الواحدة — T-197. ثمانٍ بارتفاع ١٠٨٠٠ بكسل، دون حدّ
     * المتصفّح في اللقطة الواحدة بهامشٍ واسع.
     */
    public const BATCH = 8;

    /** بعدها تُعدّ «جاريةٌ» متعثّرةً — طابورٌ بلا عاملٍ يتركها جاريةً أبداً. */
    public const STALL_MINUTES = 10;

    public static function disk(): Filesystem
    {
        return Storage::disk((string) config('khulasah.images.disk'));
    }

    /** أيُلتقط هنا شيء؟ متصفّحُ بطاقة المشاركة مطفأٌ افتراضاً (T-144). */
    public static function enabled(): bool
    {
        return ! app(ShareCardCapturer::class) instanceof NullShareCardCapturer;
    }

    public static function directory(SummaryJob $job): string
    {
        return "images/{$job->tenant_id}/{$job->id}";
    }

    /** الشريحةُ بترتيبها من واحد، كما تُسمّى في الحزمة: `01.png`. */
    public static function slidePath(SummaryJob $job, int $position): string
    {
        return self::directory($job).'/'.self::slideName($position);
    }

    public static function slideName(int $position): string
    {
        return sprintf('%02d.png', $position);
    }

    public static function zipPath(SummaryJob $job): string
    {
        return self::directory($job).'/carousel.zip';
    }
}
