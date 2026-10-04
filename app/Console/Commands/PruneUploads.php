<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Stages\ResolveTranscript;
use App\Models\SummaryJob;
use App\Services\Transcript\ChunkedUploads;
use App\Support\Transcript\UploadStore;
use Illuminate\Console\Command;

/**
 * Sweep the recordings no pipeline is waiting for — المواصفة §5-أ-4-ب.
 *
 * الملفّ يُحذف متى صار نصّاً ({@see ResolveTranscript}).
 * **وما يبقى بعدها ملفّاتُ مهامّ أخفقت ولم تُستأنف**: يُنتظر بها أيّاماً لعلّ
 * صاحبها يضغط «أعد المحاولة»، ثمّ تُكنس — تسجيلُ درسٍ ليس مادّةً نحتفظ بها،
 * والقرصُ لا يتّسع لنصف غيغابايت لكلّ محاولةٍ متروكة.
 *
 * **وأجزاءُ رفعٍ لم يكتمل** — انقطع الاتّصال ولم يعد صاحبُه، أو رُفع الملفّ
 * ولم يُرسَل النموذج — تُكنس بعد `UPLOAD_STALE_HOURS`، ويجري الكنسُ كلّ ساعة.
 *
 * **ولا يُمسّ ملفُّ مهمّةٍ ما زالت تجري**، مهما طال مكثُه.
 */
class PruneUploads extends Command
{
    protected $signature = 'khulasah:prune-uploads';

    protected $description = 'يكنس الرفع المتروك وملفّات المهامّ المتروكة بعد مدّتها — المواصفة §5-أ-4-ب';

    public function handle(ChunkedUploads $uploads): int
    {
        // ١. رفعٌ لم يكتمل، أو اكتمل ولم يُرسَل نموذجُه — بالساعات.
        $hours = (int) config('khulasah.transcript.upload.stale_hours', 24);
        $stale = $uploads->pruneStale($hours);

        // ٢. ملفُّ مهمّةٍ أخفقت إخفاقاً عارضاً ولم تُستأنف — بالأيّام.
        $days = (int) config('khulasah.transcript.upload.retention_days', 3);
        $old = UploadStore::olderThan($days);

        $running = $old === [] ? [] : SummaryJob::query()
            ->whereIn('upload_path', $old)
            ->get(['id', 'state', 'upload_path'])
            ->reject(static fn (SummaryJob $job): bool => $job->state->isTerminal())
            ->pluck('upload_path')
            ->all();

        $doomed = array_values(array_diff($old, $running));

        foreach ($doomed as $path) {
            UploadStore::delete($path);
        }

        // فلا يبقى في المهامّ مسارٌ إلى ملفٍّ غير موجود.
        if ($doomed !== []) {
            SummaryJob::query()->whereIn('upload_path', $doomed)->update([
                'upload_path' => null,
                'upload_name' => null,
            ]);
        }

        $this->components->info(sprintf(
            'كُنس %d رفعاً متروكاً (أقدم من %d ساعة)، و%d ملفّاً لمهامّ أخفقت (أقدم من %d أيام).',
            $stale, $hours, count($doomed), $days,
        ));

        return self::SUCCESS;
    }
}
