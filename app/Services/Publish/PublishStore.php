<?php

declare(strict_types=1);

namespace App\Services\Publish;

use App\Contracts\PublishStore as PublishStoreContract;
use App\Support\Publish\Paths;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * رفع الصفحات إلى التخزين وخدمتها من CDN — المواصفة §9.
 *
 * **ولا يمرّ عرض الصفحة على Laravel إطلاقاً.** الملفّ يُرفع مرّةً ويُخدَم من
 * الحافّة، فتتحمّل الصفحةُ انتشاراً لا يحتمله خادمنا، وتبقى قائمةً وإن سقط.
 */
class PublishStore implements PublishStoreContract
{
    public function put(string $path, string $contents, string $contentType): string
    {
        $this->disk()->put($path, $contents, [
            'visibility' => 'public',
            'ContentType' => $contentType,

            /*
             * كاشُ الحافّة طويل، وكاشُ المتصفّح قصير. فإعادةُ النشر تُبطل
             * الحافّة فوراً، ولا نملك إبطالَ ما في متصفّحات الناس — فلا
             * يُترك عندهم يوماً كاملاً.
             */
            'CacheControl' => 'public, max-age=300, s-maxage=31536000',
        ]);

        return $this->url($path);
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function get(string $path): ?string
    {
        return $this->disk()->exists($path) ? $this->disk()->get($path) : null;
    }

    /**
     * لا CDN بعد؟ يُخدَم عبر التطبيق نفسه لا برابط القرص الخام — T-127.
     *
     * `disk()->url()` يخرج `/storage/{tenant}/{slug}/index.html`: مسارُ
     * تخزينٍ داخليّ لا رابطاً يُشارَك. والمسار هنا مطابقٌ لما يحسبه
     * {@see Paths::forOutput()} حرفاً بحرف بعد إسقاط
     * `/index.html`، وهو ما يقرؤه `ShowPublishedSummaryController`.
     */
    public function url(string $path): string
    {
        $base = rtrim((string) config('khulasah.publish.cdn_url'), '/');

        if ($base !== '') {
            return $base.'/'.ltrim($path, '/');
        }

        $clean = preg_replace('#/index\.html$#', '', $path);

        return rtrim((string) config('app.url'), '/').'/'.ltrim((string) $clean, '/');
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('khulasah.publish.disk'));
    }
}
