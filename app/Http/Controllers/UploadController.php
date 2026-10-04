<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\UploadRejected;
use App\Models\MediaUpload;
use App\Models\Tenant;
use App\Services\Transcript\ChunkedUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The chunked upload endpoints of the Create screen — المواصفة §5-أ-4-ب.
 *
 * `POST /uploads` يبدأ · `PUT /uploads/{id}/chunks/{n}` جزءٌ جزء ·
 * `GET /uploads/{id}` ما وصل (للاستئناف) · `POST /uploads/{id}/complete` يجمع
 * ويفحص · `DELETE /uploads/{id}` يُلغي ويحذف كلّ ما رُفع.
 *
 * **والجهةُ تحرسها الربطُ نفسه**: `MediaUpload` تحت نطاق الجهة، فرفعُ جهةٍ
 * أخرى يعود 404 كأنّه لم يكن.
 */
class UploadController extends Controller
{
    public function __construct(private readonly ChunkedUploads $uploads) {}

    /**
     * @throws UploadRejected
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
        ]);

        $upload = $this->uploads->start($this->tenant($request), $request->user(), $data['name'], (int) $data['size']);

        return response()->json($this->describe($upload), 201);
    }

    public function show(MediaUpload $upload): JsonResponse
    {
        return response()->json($this->describe($upload));
    }

    /**
     * @throws UploadRejected
     */
    public function chunk(Request $request, MediaUpload $upload, int $index): JsonResponse
    {
        // الجسمُ خامٌ لا `multipart`: يُكتب إلى القرص كما وصل، بلا نسخةٍ مؤقّتة ثانية.
        $this->uploads->putChunk($upload, $index, $request->getContent(true));

        return response()->json(['index' => $index]);
    }

    /**
     * @throws UploadRejected
     */
    public function complete(Request $request, MediaUpload $upload): JsonResponse
    {
        return response()->json($this->describe($this->uploads->complete($upload, $this->tenant($request))));
    }

    /** يُلغى من المتصفّح: أُزيل الملفّ، أو اختير غيرُه، أو غادر المستخدم الصفحة. */
    public function destroy(MediaUpload $upload): Response
    {
        $this->uploads->discard($upload);

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function describe(MediaUpload $upload): array
    {
        return [
            'id' => $upload->id,
            'name' => $upload->original_name,
            'status' => $upload->status,
            'size' => $upload->size_bytes,
            'chunk_bytes' => $upload->chunk_bytes,
            'chunk_count' => $upload->chunk_count,
            'received' => $upload->isReady() ? [] : $this->uploads->received($upload),
            'duration_seconds' => $upload->duration_seconds,
        ];
    }

    private function tenant(Request $request): Tenant
    {
        $tenant = $request->user()?->tenant;

        abort_if(! $tenant instanceof Tenant, 403);

        return $tenant;
    }
}
