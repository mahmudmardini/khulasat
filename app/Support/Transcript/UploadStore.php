<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Console\Commands\PruneUploads;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Where an uploaded recording waits for the pipeline — المواصفة §5-أ-4-ب.
 *
 * **بين الطلب والطابور لا أكثر.** الملفّ يُكتب عند الإنشاء، ويقرؤه عاملُ
 * الطابور في مرحلة التفريغ، ويُحذف متى صار نصّاً. وما بقي لأنّ مهمّته أخفقت
 * ولم تُستأنف يُكنس بعد مدّة ({@see PruneUploads}):
 * تسجيلُ درسٍ ليس مادّةً نحتفظ بها، والقرصُ لا يتّسع لنصف غيغابايت لكلّ محاولة.
 *
 * **والقرص محلّيّ** (`local` افتراضاً): ffmpeg يحتاج مساراً على القرص،
 * وعاملُ الطابور يقرأ ما كتبه الخادم. فإن فُصلا على جهازين لزم قرصٌ مشترك.
 */
final class UploadStore
{
    private const DIRECTORY = 'uploads';

    private const INCOMING = 'incoming';

    private function __construct() {}

    /** مجلّدُ أجزاء رفعٍ لم يكتمل — `uploads/incoming/{id}`. */
    public static function incomingDirectory(string $uploadId): string
    {
        return self::DIRECTORY.'/'.self::INCOMING.'/'.$uploadId;
    }

    /**
     * أين يُجمع الملفّ — باسمٍ مولَّد لا باسم المستخدم: الاسمُ يكتبه المستخدم،
     * والمسار يبلغ ffmpeg. واللاحقة تُبقى للتشخيص وحده — النوع يُفحص بالمحتوى.
     */
    public static function assembledPath(int $tenantId, string $uploadId, string $extension): string
    {
        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $extension));

        return self::DIRECTORY.'/'.$tenantId.'/'.$uploadId.($extension === '' ? '' : '.'.$extension);
    }

    /** المسار المطلق على القرص، وُجد الملفّ أم لم يوجد بعد. */
    public static function absolute(string $path): string
    {
        return Storage::disk(self::diskName())->path($path);
    }

    /** المسار المطلق للملفّ إن كان ما زال على القرص. */
    public static function localPath(?string $path): ?string
    {
        if ($path === null || $path === '' || ! self::disk()->exists($path)) {
            return null;
        }

        return Storage::disk(self::diskName())->path($path);
    }

    public static function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            self::disk()->delete($path);
        }
    }

    public static function deleteDirectory(string $directory): void
    {
        self::disk()->deleteDirectory($directory);
    }

    /**
     * مجلّداتُ أجزاءٍ لم يُكتب فيها شيءٌ منذ `$hours` ساعة — بقايا رفعٍ سقط
     * صفُّه (عطلٌ بين كتابة الجزء وحفظ الصفّ) فلا يراها كنسُ الجدول.
     *
     * @return list<string>
     */
    public static function staleIncoming(int $hours): array
    {
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = self::disk();

        return array_values(array_filter(
            $disk->directories(self::DIRECTORY.'/'.self::INCOMING),
            static function (string $directory) use ($disk, $cutoff): bool {
                $times = array_map($disk->lastModified(...), $disk->files($directory));

                return ($times === [] ? 0 : max($times)) < $cutoff;
            },
        ));
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /**
     * ما مضى على كتابته أكثر من `$days` يوماً.
     *
     * @return list<string>
     */
    public static function olderThan(int $days): array
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = self::disk();

        // أجزاءُ الرفع الجاري لها كنسُها بالساعات ({@see staleIncoming()})، لا بالأيام.
        $incoming = self::DIRECTORY.'/'.self::INCOMING.'/';

        return array_values(array_filter(
            $disk->allFiles(self::DIRECTORY),
            static fn (string $path): bool => ! str_starts_with($path, $incoming)
                && $disk->lastModified($path) < $cutoff,
        ));
    }

    private static function diskName(): string
    {
        return (string) config('khulasah.transcript.upload.disk', 'local');
    }
}
