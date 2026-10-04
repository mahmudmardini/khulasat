<?php

declare(strict_types=1);

namespace App\Services\Render;

use App\Contracts\OverflowProbe;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * يقيس الفيض بالمتصفّح نفسه الذي يلتقط الشرائح — T-173.
 *
 * يُلحق بالوثيقة سكربتاً **يقيس ولا يغيّر**: ينتظر الخطوط، ثمّ يقارن ما يحتاجه
 * محتوى كلّ إطار (`scrollHeight`) بما يسعه (`clientHeight`)، ويكتب أرقام ما
 * فاض في سمةٍ على `body`. ثمّ يُطبع الـDOM (`--dump-dom`) وتُقرأ السمة.
 *
 * والسكربتُ لا يدخل مخرَجاً: الوثيقةُ هنا نسخةٌ تُقاس وتُرمى. ومهلةٌ صارمة
 * وتنظيفٌ في `finally`، كما في ملتقِط البطاقة (T-144).
 */
final class ChromeOverflowProbe implements OverflowProbe
{
    /** هامشُ تقريب البكسل: إطارٌ يفيض بنصف بكسل لا يُقصّ منه حرف. */
    private const TOLERANCE_PX = 2;

    private const SCRIPT = <<<'JS'
        <script>
        document.fonts.ready.then(function () {
          var over = [];
          document.querySelectorAll('.slide').forEach(function (slide, index) {
            var frame = slide.querySelector('.frame');
            if (frame && frame.scrollHeight > frame.clientHeight + %d) { over.push(index + 1); }
          });
          document.body.setAttribute('data-overflow', over.join(','));
          document.body.setAttribute('data-probed', '1');
        });
        </script>
        JS;

    public function __construct(
        private readonly string $binary,
        private readonly int $timeout,
        private readonly bool $noSandbox = false,
    ) {}

    public function overflowing(string $html): ?array
    {
        $dir = storage_path('app/tmp/overflow-'.Str::random(16));

        try {
            if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                return null;
            }

            $source = $dir.'/deck.html';
            file_put_contents($source, str_replace('</body>', sprintf(self::SCRIPT, self::TOLERANCE_PX).'</body>', $html));

            $command = [
                $this->binary,
                '--headless=new',
                '--disable-gpu',
                '--hide-scrollbars',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--user-data-dir='.$dir.'/profile',
                '--virtual-time-budget=8000',
                // أعرضُ من الشريحة: تحت ١٠٨٠ يصغّر القالبُ نفسه للشاشات الضيّقة.
                '--window-size=1200,1500',
                '--dump-dom',
            ];

            if ($this->noSandbox) {
                $command[] = '--no-sandbox';
            }

            $command[] = 'file://'.$source;

            $process = Process::start($command);

            // كما في الملتقِط: كرومُ بملفّ تعريفٍ قد يبقى قائماً بعد عمله، فيُنتظر
            // الـDOM لا خروجُ المتصفّح.
            $deadline = microtime(true) + $this->timeout;

            while (microtime(true) < $deadline && $process->running() && ! str_contains($process->output(), '</html>')) {
                usleep(200_000);
            }

            $dom = $process->output();

            if ($process->running()) {
                $process->stop(2);
            }

            if (preg_match('/data-probed="1"/', $dom) !== 1) {
                Log::warning('overflow_probe_failed', ['error' => Str::limit($process->errorOutput(), 500)]);

                return null;
            }

            preg_match('/data-overflow="([0-9,]*)"/', $dom, $match);

            return array_values(array_map('intval', array_filter(explode(',', $match[1] ?? ''), 'strlen')));
        } catch (Throwable $failure) {
            Log::warning('overflow_probe_failed', ['error' => $failure->getMessage()]);

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
