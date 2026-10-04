<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Verify\SubmitVerifyCheck;
use App\Exceptions\VerifyRefused;
use App\Http\Controllers\Controller;
use App\Http\Controllers\VerifyController;
use App\Models\VerifyCheck;
use App\Support\Verify\CheckPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * أداة «تحقّق» — الواجهة البرمجية، T-181.
 *
 * **غير متزامنة، كالصفحة**: `POST` يُعيد معرّف الطلب فوراً بـ202، و`GET`
 * يُعيد حاله ثمّ تقريره. فنداءُ الاستخراج لا يُعلّق اتّصالَ العميل، والحقولُ
 * هي حقولُ الصفحة نفسها — {@see CheckPresenter}.
 */
class VerifyApiController extends Controller
{
    public function store(Request $request, SubmitVerifyCheck $submit): JsonResponse
    {
        $text = VerifyController::validated($request);

        try {
            $check = $submit->handle($text, (string) $request->ip());
        } catch (VerifyRefused $refused) {
            return response()->json(
                ['error' => ['code' => $refused->reason, 'message' => $refused->getMessage()]],
                $refused->status(),
                $refused->retryAfter > 0 ? ['Retry-After' => (string) $refused->retryAfter] : [],
            );
        }

        return response()->json(
            ['check' => CheckPresenter::toArray($check, withText: false)],
            202,
            ['Location' => route('api.verify.show', $check->id)],
        );
    }

    public function show(VerifyCheck $check): JsonResponse
    {
        // **التقريرُ المحذوف «انقضى» لا «غير موجود»**: الرابطُ كان صحيحاً.
        return response()->json(
            ['check' => CheckPresenter::toArray($check, withText: false)],
            $check->isPurged() ? 410 : 200,
        );
    }
}
