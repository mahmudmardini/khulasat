<?php

declare(strict_types=1);

namespace App\Services\ShareCard;

use App\Contracts\ShareCardCapturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Captures the card with a headless Chrome/Chromium binary — T-144.
 *
 * **مهلةٌ صارمة وتنظيفٌ في كلّ حال** (معيار القبول): متصفّحٌ عالقٌ يحبس
 * عاملَ الطابور، وملفّاتٌ مؤقّتةٌ تبقى تملأ القرص صامتة. فالمجلّدُ المؤقّت
 * يُحذف في `finally` نجح الالتقاطُ أو سقط.
 *
 * **والبطاقةُ بلا سكربت**: قالبُها يحمل `Content-Security-Policy` يمنعه،
 * وما فيها من نصّ المستخدم مهرَّبٌ في Blade. فلا ينفّذ المتصفّحُ ما لم نكتبه.
 */
final class ChromeShareCardCapturer implements ShareCardCapturer
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
        private readonly bool $noSandbox = false,
    ) {}

    public function capture(string $html, int $width, int $height): ?string
    {
        $dir = storage_path('app/tmp/share-card-'.Str::random(16));

        try {
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                return null;
            }

            $source = $dir.'/card.html';
            $target = $dir.'/card.png';
            file_put_contents($source, $html);

            $command = [
                $this->binary,
                '--headless=new',
                '--disable-gpu',
                '--hide-scrollbars',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--user-data-dir='.$dir.'/profile',
                // وقتٌ افتراضيٌّ يكفي الخطوطَ لتُحمَّل قبل الالتقاط.
                '--virtual-time-budget=8000',
                '--window-size='.$width.','.$height,
                '--screenshot='.$target,
            ];

            if ($this->noSandbox) {
                $command[] = '--no-sandbox';
            }

            $command[] = 'file://'.$source;

            $process = Process::start($command);

            // ★ **لا يُنتظر خروجُ المتصفّح، يُنتظر ملفُّه.** كرومُ بملفّ
            // تعريفٍ (`--user-data-dir`) يكتب اللقطةَ ثمّ يبقى قائماً — وُجد
            // بالتجربة، ١٦ أيلول ٢٠٢٦. فانتظارُ خروجه يبلغ المهلةَ دائماً
            // ويُهدر صورةً صحيحة. فيُراقَب الملفُّ حتى يثبت حجمُه، ثمّ يُوقَف.
            $deadline = microtime(true) + $this->timeout;
            $lastSize = -1;

            while (microtime(true) < $deadline) {
                clearstatcache(true, $target);
                $size = is_file($target) ? (int) filesize($target) : 0;

                if ($size > 0 && $size === $lastSize) {
                    break;
                }

                if (! $process->running() && $size === 0) {
                    break;
                }

                $lastSize = $size;
                usleep(300_000);
            }

            if ($process->running()) {
                $process->stop(2);
            }

            clearstatcache(true, $target);

            if (! is_file($target) || filesize($target) === 0) {
                Log::warning('share_card_capture_failed', [
                    'error' => Str::limit($process->errorOutput(), 500),
                ]);

                return null;
            }

            return (string) file_get_contents($target);
        } catch (Throwable $failure) {
            Log::warning('share_card_capture_failed', ['error' => $failure->getMessage()]);

            return null;
        } finally {
            $this->removeDirectory($dir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
