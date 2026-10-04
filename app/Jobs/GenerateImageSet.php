<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Render\RenderImageSet;
use App\Models\SummaryJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * حزمةُ صور الكاروسيل في الطابور — T-173.
 *
 * **لا في الطلب**: عشرُ شرائح بمتصفّحٍ لكلّ واحدة تبلغ دقيقةً أحياناً، وطلبٌ
 * يُمسك عاملَ الويب دقيقةً يُسقطه الوسيطُ قبلها. فتُعلَّم الحزمة «جارية»،
 * وتنتظرها الشاشة.
 *
 * **ومحاولةٌ واحدة**: إخفاقُ الالتقاط في الغالب علّةٌ في الخادم لا عارض،
 * وتكرارُه عشرَ مرّاتٍ لا يُصلحه. والسببُ يُكتب في الصفّ، والمستخدمُ يعيد.
 */
final class GenerateImageSet implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    /** @param  string|null  $design  قالبٌ معتمدٌ اختارته الجهة، وإلّا افتراضيُّها — T-173. */
    public function __construct(public SummaryJob $summaryJob, public ?string $design = null) {}

    public function uniqueId(): string
    {
        return (string) $this->summaryJob->id;
    }

    public function handle(RenderImageSet $render): void
    {
        $job = $this->summaryJob->fresh();

        if ($job === null) {
            return;
        }

        try {
            $render->handle($job, $this->design);
        } catch (RuntimeException $refused) {
            RenderImageSet::mark($job, 'failed', $refused->getMessage());
        } catch (Throwable $failure) {
            Log::error('image_set_failed', ['summary_job_id' => $job->id, 'error' => $failure->getMessage()]);

            RenderImageSet::mark($job, 'failed', trans('jobs.images.failed'));
        }
    }
}
