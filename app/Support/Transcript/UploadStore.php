<?php

declare(strict_types=1);

namespace App\Support\Transcript;

use App\Console\Commands\PruneUploads;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    private function __construct() {}

    /**
     * يُكتب باسمٍ مولَّد لا باسم المستخدم: الاسمُ يكتبه المستخدم، والمسار
     * يبلغ ffmpeg. واللاحقة تُبقى للتشخيص وحده — النوع يُفحص بالمحتوى.
     */
    public static function store(UploadedFile $file, int $tenantId): string
    {
        $extension = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $file->getClientOriginalExtension()));
        $name = (string) Str::uuid().($extension === '' ? '' : '.'.$extension);

        return (string) self::disk()->putFileAs(self::DIRECTORY.'/'.$tenantId, $file, $name);
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

    /**
     * ما مضى على كتابته أكثر من `$days` يوماً.
     *
     * @return list<string>
     */
    public static function olderThan(int $days): array
    {
        $cutoff = now()->subDays($days)->getTimestamp();
        $disk = self::disk();

        return array_values(array_filter(
            $disk->allFiles(self::DIRECTORY),
            static fn (string $path): bool => $disk->lastModified($path) < $cutoff,
        ));
    }

    private static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    private static function diskName(): string
    {
        return (string) config('khulasah.transcript.upload.disk', 'local');
    }
}
