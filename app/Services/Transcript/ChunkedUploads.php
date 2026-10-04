<?php

declare(strict_types=1);

namespace App\Services\Transcript;

use App\Console\Commands\PruneUploads;
use App\Enums\TranscriptErrorCode;
use App\Exceptions\TranscriptFailed;
use App\Exceptions\UploadRejected;
use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Transcript\UploadedSource;
use App\Support\Transcript\UploadStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Receive a recording in chunks, assemble it, check it — المواصفة §5-أ-4-ب.
 *
 * **ثلاثُ مراحل، وكلٌّ منها طلبٌ قصير:**
 *   ١. `start` — يُعلَن الاسم والحجم، فيُرفض ما تجاوز الحدّ قبل أن يُرفع بايت.
 *   ٢. `putChunk` — جزءٌ واحد في كلّ طلب. **والجزء الساقط يُعاد وحده**،
 *      وإعادةُ جزءٍ وصل تكتب فوقه بلا ضرر — فالرفع يستأنف ولا يبدأ من الصفر.
 *   ٣. `complete` — تُجمع الأجزاء بترتيبها، ويُفحص المحتوى والصوت والمدّة.
 *      وما رُفض هنا يُحذف فوراً: ملفٌّ لا يصلح لا يُبقى ليُرفع غيرُه بجانبه.
 *
 * **ولا يبقى على القرص ما لا صاحب له:** الأجزاءُ تُحذف بعد الفحص، والرفعُ
 * الملغى يُحذف بطلب المتصفّح، والمتروكُ يُكنس بعد ساعات
 * ({@see PruneUploads}).
 */
final class ChunkedUploads
{
    /** هامشُ ترويسات الطلب تحت `post_max_size` — فالجزء جسمُ الطلب لا الطلب كلّه. */
    private const REQUEST_OVERHEAD_BYTES = 64 * 1024;

    public function __construct(private readonly Ffmpeg $ffmpeg) {}

    /**
     * @throws UploadRejected
     */
    public function start(Tenant $tenant, ?User $user, string $name, int $size): MediaUpload
    {
        $max = (int) config('khulasah.transcript.upload.max_bytes');

        if ($size < 1 || $size > $max) {
            throw UploadRejected::because('upload_too_large');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        /** @var list<string> $allowed */
        $allowed = config('khulasah.transcript.upload.media_extensions', []);

        // راحةٌ لا أمان، كما في المتصفّح: المحتوى يُفحص عند الجمع.
        if (! in_array($extension, $allowed, true)) {
            throw UploadRejected::because('upload_invalid');
        }

        $chunk = self::chunkBytes();

        return MediaUpload::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'original_name' => mb_substr($name, 0, 255),
            'extension' => $extension,
            'size_bytes' => $size,
            'chunk_bytes' => $chunk,
            'chunk_count' => (int) ceil($size / $chunk),
            'status' => MediaUpload::RECEIVING,
        ]);
    }

    /**
     * One chunk, written atomically: a half-written part is never taken for a whole one.
     *
     * @param  resource  $stream
     *
     * @throws UploadRejected
     */
    public function putChunk(MediaUpload $upload, int $index, $stream): void
    {
        if ($upload->status !== MediaUpload::RECEIVING) {
            throw UploadRejected::because('upload_closed', 409);
        }

        if ($index < 0 || $index >= $upload->chunk_count) {
            throw UploadRejected::because('upload_chunk_invalid');
        }

        $disk = UploadStore::disk();
        $directory = UploadStore::incomingDirectory($upload->id);
        $temporary = "{$directory}/{$index}.part.tmp";
        $final = "{$directory}/{$index}.part";

        if ($disk->writeStream($temporary, $stream) === false) {
            throw UploadRejected::because('upload_failed', 500);
        }

        // **الحجمُ بالضبط** لا «أقلّ من الحدّ»: جزءٌ ناقص يُجمع ملفّاً تالفاً
        // لا يُكتشف إلّا عند ffmpeg، بعد أن ظنّ المستخدم أنّ الرفع تمّ.
        if ($disk->size($temporary) !== $upload->expectedChunkBytes($index)) {
            $disk->delete($temporary);

            throw UploadRejected::because('upload_chunk_invalid');
        }

        $disk->delete($final);
        $disk->move($temporary, $final);

        // فالمتروكُ يُقاس من آخر جزءٍ وصل، لا من أوّله: رفعٌ بطيءٌ حيّ لا يُكنس.
        $upload->touch();
    }

    /**
     * The chunk numbers already on disk — what a resumed upload skips.
     *
     * @return list<int>
     */
    public function received(MediaUpload $upload): array
    {
        $indexes = [];

        foreach (UploadStore::disk()->files(UploadStore::incomingDirectory($upload->id)) as $file) {
            if (preg_match('/\/(\d+)\.part$/', $file, $match) === 1) {
                $indexes[] = (int) $match[1];
            }
        }

        sort($indexes);

        return $indexes;
    }

    /**
     * Assemble in order, then check the content, the sound and the length.
     *
     * **وبقفلٍ**: نقرتان على «إرسال» أو إعادةُ الطلب بعد مهلةٍ تجمعان الملفّ
     * مرّتين في الوقت نفسه، فيكتب كلٌّ فوق الآخر.
     *
     * @throws UploadRejected
     */
    public function complete(MediaUpload $upload, Tenant $tenant): MediaUpload
    {
        return Cache::lock("media-upload:{$upload->id}", 600)->block(30, function () use ($upload, $tenant): MediaUpload {
            $upload->refresh();

            if ($upload->isReady()) {
                return $upload;
            }

            $missing = array_values(array_diff(range(0, $upload->chunk_count - 1), $this->received($upload)));

            if ($missing !== []) {
                // لا يُحذف شيء: المتصفّح يرفع الناقص ثمّ يعود.
                throw new UploadRejected('upload_incomplete', (string) trans('lectures.create.source.upload_incomplete'), 409);
            }

            $path = $this->assemble($upload);

            try {
                $seconds = $this->inspect(UploadStore::absolute($path), $tenant);
            } catch (UploadRejected $rejected) {
                if ($rejected->reason === 'upload_unavailable') {
                    // **عطلٌ عندنا لا في الملفّ**: تبقى الأجزاء، فيُجمع ويُفحص
                    // من جديد حين يعود المتصفّح، ولا يُرفع بايتٌ مرّتين.
                    UploadStore::delete($path);

                    throw $rejected;
                }

                $this->discard($upload->forceFill(['path' => $path]));

                throw $rejected;
            }

            // الأجزاء صارت ملفّاً سليماً، فلا يبقى منها شيء.
            UploadStore::deleteDirectory(UploadStore::incomingDirectory($upload->id));

            $upload->forceFill([
                'status' => MediaUpload::READY,
                'path' => $path,
                'duration_seconds' => (int) ceil($seconds),
            ])->save();

            return $upload;
        });
    }

    /** Remove every byte of an upload: its chunks, its assembled file, its row. */
    public function discard(MediaUpload $upload): void
    {
        UploadStore::deleteDirectory(UploadStore::incomingDirectory($upload->id));
        UploadStore::delete($upload->path);

        $upload->delete();
    }

    /**
     * Uploads nobody finished or sent within `$hours`, removed with their files.
     *
     * @return int كم رفعاً كُنس.
     */
    public function pruneStale(int $hours): int
    {
        $stale = MediaUpload::query()->where('updated_at', '<', now()->subHours($hours))->get();

        $stale->each(fn (MediaUpload $upload) => $this->discard($upload));

        // وأجزاءٌ بلا صفّ — عطلٌ وقع بين كتابة الجزء وحفظ الصفّ.
        $known = MediaUpload::query()->pluck('id')->all();

        foreach (UploadStore::staleIncoming($hours) as $directory) {
            if (! in_array(basename($directory), $known, true)) {
                UploadStore::deleteDirectory($directory);
            }
        }

        return $stale->count();
    }

    /**
     * أكبرُ جزءٍ يقبله الخادم: الإعدادُ، ولا يتجاوز `post_max_size` في PHP —
     * فـ`ValidatePostSize` يردّ كلّ طلبٍ أكبر منه بـ413، أيّاً كان نوعه.
     */
    public static function chunkBytes(): int
    {
        $configured = (int) config('khulasah.transcript.upload.chunk_bytes', 10 * 1024 * 1024);
        $postMax = self::iniBytes((string) ini_get('post_max_size'));

        if ($postMax <= 0) {
            return $configured;
        }

        return min($configured, max(1024 * 1024, $postMax - self::REQUEST_OVERHEAD_BYTES));
    }

    /**
     * يُجمع في ملفٍّ مؤقّت ثمّ يُسمّى: ملفٌّ نصفُ مجموع لا يُرى كاملاً.
     *
     * @throws UploadRejected
     */
    private function assemble(MediaUpload $upload): string
    {
        $path = UploadStore::assembledPath($upload->tenant_id, $upload->id, $upload->extension);
        $target = UploadStore::absolute($path);
        $directory = UploadStore::incomingDirectory($upload->id);

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0750, true);
        }

        $out = fopen($target.'.tmp', 'wb');

        if ($out === false) {
            throw UploadRejected::because('upload_failed', 500);
        }

        try {
            for ($index = 0; $index < $upload->chunk_count; $index++) {
                $in = fopen(UploadStore::absolute("{$directory}/{$index}.part"), 'rb');

                if ($in === false) {
                    throw UploadRejected::because('upload_failed', 500);
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        if (filesize($target.'.tmp') !== $upload->size_bytes) {
            @unlink($target.'.tmp');

            throw UploadRejected::because('upload_failed', 500);
        }

        rename($target.'.tmp', $target);

        return $path;
    }

    /**
     * «النوع بالمحتوى لا بالامتداد»، والصوتُ موجود، والمدّةُ في حدّ الاشتراك.
     *
     * @throws UploadRejected
     */
    private function inspect(string $absolute, Tenant $tenant): float
    {
        if (! UploadedSource::isMedia($absolute)) {
            throw UploadRejected::because('upload_invalid');
        }

        try {
            if (! $this->ffmpeg->hasAudioTrack($absolute)) {
                throw UploadRejected::because('upload_no_audio');
            }

            $seconds = $this->ffmpeg->durationSeconds($absolute);
        } catch (TranscriptFailed $failed) {
            if ($failed->errorCode === TranscriptErrorCode::MediaToolUnavailable) {
                // يُكتب في السجلّ: من يرى «أعد المحاولة» لا يخبرنا أنّ ffprobe غائب.
                Log::error('upload.media_tool_unavailable', ['error' => $failed->getMessage()]);

                throw UploadRejected::because('upload_unavailable', 503);
            }

            throw UploadRejected::because('upload_invalid');
        }

        $limit = (int) ($tenant->max_lecture_minutes ?? 0);

        // «تُفحص على حدّ الاشتراك قبل أي معالجة» — §5-أ-4-ب.
        if ($limit > 0 && $seconds > $limit * 60) {
            throw new UploadRejected('upload_too_long', (string) trans('lectures.create.preflight.too_long', [
                'minutes' => (int) ceil($seconds / 60),
                'limit' => $limit,
            ]));
        }

        return $seconds;
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
