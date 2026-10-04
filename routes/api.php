<?php

declare(strict_types=1);

use App\Http\Controllers\Api\VerifyApiController;
use Illuminate\Support\Facades\Route;

/*
 * الواجهة البرمجية — **أداة «تحقّق» وحدها** (T-181)، بلا مصادقة كصفحتها.
 * وحدُّ المعدّل وسقفُ الإنفاق في `SubmitVerifyCheck`، مشتركان مع الصفحة.
 */
Route::prefix('v1')->group(function (): void {
    Route::post('/verify', [VerifyApiController::class, 'store'])->name('api.verify.store');
    Route::get('/verify/{check}', [VerifyApiController::class, 'show'])->whereUuid('check')->name('api.verify.show');
});
