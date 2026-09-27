<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\TranscriptFailed;
use App\Services\Transcript\YtDlp;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The weekly yt-dlp canary — المواصفة §5-أ-6 البند ٢.
 *
 * «`yt-dlp` يتعطّل مع تغيّرات يوتيوب. يُحدَّث كثيراً. العلاج: تثبيت الإصدار،
 * **ومهمّة أسبوعية تُحدّثه وتُشغّل فيديو اختبار ثابتاً، وتنبيه عند الفشل**.»
 *
 * ولماذا فيديو ثابت لا فحصُ إصدار: yt-dlp يعمل ويُعطي إصداره وهو عاجزٌ عن
 * قراءة يوتيوب. **والانكسار لا يظهر إلا في نداءٍ حقيقي**، وبلا هذا لا
 * يُعرف إلّا من شكوى جهةٍ لم يُنتَج ملخّصها.
 *
 * ولا تُشغَّل في CI — CLAUDE.md §2 القاعدة السابعة. موضعُها الخادم، وجدولتُها
 * في `routes/console.php`.
 */
class CheckYtDlp extends Command
{
    protected $signature = 'khulasah:check-ytdlp
                            {--url= : فيديو الاختبار، وافتراضه من الإعدادات}';

    protected $description = 'يتحقّق أنّ yt-dlp ما زال يقرأ يوتيوب — المواصفة §5-أ-6';

    public function handle(YtDlp $ytDlp): int
    {
        $url = (string) ($this->option('url')
            ?: config('khulasah.transcript.canary_url'));

        if (trim($url) === '') {
            $this->components->error('لا فيديو اختبار مضبوط. اضبط TRANSCRIPT_CANARY_URL.');

            return self::FAILURE;
        }

        try {
            $preflight = $ytDlp->preflight($url);
        } catch (TranscriptFailed $failure) {
            return $this->alert_(
                "yt-dlp أخفق على فيديو الاختبار برمز {$failure->errorCode->value}.",
                $failure,
            );
        } catch (Throwable $exception) {
            return $this->alert_('yt-dlp أخفق على فيديو الاختبار.', $exception);
        }

        // **العنوان دليلُ القراءة الحقيقية.** yt-dlp قد ينجح ويعود بكائنٍ
        // شبه فارغ حين يتغيّر يوتيوب، فيُعدّ ناجحاً وهو أعمى. ومدّةٌ صفرٌ
        // لفيديو حقيقيّ دليل العطب نفسه كغيابها — لا محاضرة مدّتها صفر.
        if (trim((string) $preflight->title) === ''
            || $preflight->durationSeconds === null
            || $preflight->durationSeconds === 0) {
            return $this->alert_('yt-dlp عاد بلا عنوان أو مدّة — قراءةٌ ناقصة لا نجاح.');
        }

        $this->components->info("yt-dlp سليم: «{$preflight->title}» — {$preflight->durationSeconds} ثانية.");

        return self::SUCCESS;
    }

    /** التنبيه في السجلّ بمستوى `error`، فتلتقطه المراقبة. */
    private function alert_(string $message, ?Throwable $exception = null): int
    {
        Log::error('[khulasah:check-ytdlp] '.$message, array_filter([
            'exception' => $exception?->getMessage(),
        ]));

        $this->components->error($message);

        return self::FAILURE;
    }
}
