<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Verify\RunVerifyCheck;
use App\Enums\VerifyCheckStatus;
use App\Models\VerifyCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * طلبُ «تحقّق» في الطابور — T-181.
 *
 * **في الطابور لا في الطلب**: نداءُ الاستخراج قد يطول عشرين ثانية، وطلبُ
 * HTTP معلَّقٌ هذه المدّة يسقط عند أوّل مهلةٍ في الوسيط. فيُعاد الرابطُ
 * فوراً، وتتابع الصفحةُ الحالَ حتى يجهز التقرير.
 *
 * **ومحاولةٌ واحدة**: الإعادةُ نداءٌ مدفوعٌ ثانٍ لطلبٍ عامّ، والباحثُ يملك
 * زرَّ «أعد المحاولة» إن شاء.
 */
final class RunVerifyCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public VerifyCheck $check) {}

    public function handle(RunVerifyCheck $run): void
    {
        $run->handle($this->check);
    }

    /** عطلٌ لم يُتوقَّع لا يترك الصفحة تنتظر إلى الأبد. */
    public function failed(?Throwable $exception): void
    {
        $this->check->refresh();

        if (! $this->check->status->isSettled()) {
            $this->check->forceFill([
                'status' => VerifyCheckStatus::Failed,
                'error_code' => 'internal',
                'completed_at' => now(),
            ])->save();
        }
    }
}
