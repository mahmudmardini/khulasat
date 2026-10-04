<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Stages\ResolveTranscript;
use App\Models\SummaryJob;
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
 * **ولا يُمسّ ملفُّ مهمّةٍ ما زالت تجري**، مهما طال مكثُه.
 */
class PruneUploads extends Command
{
    protected $signature = 'khulasah:prune-uploads';

    protected $description = 'يحذف الملفّات المرفوعة لمهامّ متروكة بعد مدّة الاحتفاظ — المواصفة §5-أ-4-ب';

    public function handle(): int
    {
        $days = (int) config('khulasah.transcript.upload.retention_days', 7);
        $old = UploadStore::olderThan($days);

        if ($old === []) {
            $this->components->info('لا ملفّات متروكة.');

            return self::SUCCESS;
        }

        $running = SummaryJob::query()
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
        SummaryJob::query()->whereIn('upload_path', $doomed)->update([
            'upload_path' => null,
            'upload_name' => null,
        ]);

        $this->components->info(sprintf('حُذف %d ملفّاً مضى عليه أكثر من %d أيام.', count($doomed), $days));

        return self::SUCCESS;
    }
}
