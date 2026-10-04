<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Transcript\ChunkedUploads;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A recording being uploaded in chunks — المواصفة §5-أ-4-ب.
 *
 * **ملفّ نصف غيغابايت لا يُرسَل في طلبٍ واحد.** على اتّصالٍ ضعيف يأخذ دقائق،
 * وانقطاعُ ثانيةٍ في آخره يُعيده من الصفر، والوسطاءُ (nginx وCloudflare) لهم
 * حدودُ حجمٍ ومهلة. فيُقطَّع في المتصفّح أجزاءً، ويُرفع جزءاً جزءاً، ويُعاد
 * الجزءُ الساقط وحده، ثمّ يُجمع في الخادم — {@see ChunkedUploads}.
 *
 * والمعرّف UUID لا رقمٌ متسلسل: يظهر في الروابط، ورقمٌ يُخمَّن تالِيه.
 *
 * @property string $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $extension
 * @property int $size_bytes
 * @property int $chunk_bytes
 * @property int $chunk_count
 * @property string $status
 * @property string|null $path
 * @property int|null $duration_seconds
 */
class MediaUpload extends Model
{
    use BelongsToTenant, HasUuids;

    public const RECEIVING = 'receiving';

    public const READY = 'ready';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'size_bytes' => 'integer',
            'chunk_bytes' => 'integer',
            'chunk_count' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function isReady(): bool
    {
        return $this->status === self::READY && $this->path !== null;
    }

    /** حجمُ الجزء `$index` كما يجب أن يصل — والأخيرُ ما بقي. */
    public function expectedChunkBytes(int $index): int
    {
        if ($index < $this->chunk_count - 1) {
            return $this->chunk_bytes;
        }

        return $this->size_bytes - $this->chunk_bytes * ($this->chunk_count - 1);
    }
}
