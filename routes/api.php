<?php

use App\Http\Controllers\Api\V1\BorderController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API V1 Routes
Route::prefix('v1')->group(function () {
    
    // Sessions
    Route::post('/sessions', [SessionController::class, 'store']);
    Route::get('/sessions/{code}', [SessionController::class, 'show']);
    Route::patch('/sessions/{code}', [SessionController::class, 'update']);
    Route::post('/sessions/{code}/photos', [SessionController::class, 'uploadPhoto']);
    Route::delete('/sessions/{code}/photos/{photo}', [SessionController::class, 'removePhoto']);
    Route::post('/sessions/{code}/border', [SessionController::class, 'selectBorder']);
    Route::post('/sessions/{code}/compose', [SessionController::class, 'composeImage']);
    Route::post('/sessions/{code}/compose-all', [SessionController::class, 'composeAllPhotos']);
    Route::post('/sessions/{code}/checkout', [SessionController::class, 'checkout']);
    Route::get('/sessions/{code}/status', [SessionController::class, 'status']);
    Route::post('/sessions/{code}/regenerate', [SessionController::class, 'regeneratePayment']);
    Route::get('/sessions/{code}/download', [SessionController::class, 'download']);
    Route::get('/sessions/{code}/download/qr', [SessionController::class, 'downloadQrCode']);
    Route::get('/sessions/{code}/download/direct', [SessionController::class, 'downloadDirect'])->name('api.v1.sessions.download.direct');
    Route::get('/sessions/{code}/print', [SessionController::class, 'printPhoto']);

    // Borders
    Route::get('/borders', [BorderController::class, 'index']);
    Route::get('/borders/{border}', [BorderController::class, 'show']);
    Route::get('/border-categories', [BorderController::class, 'categories']);

    // Webhooks (no auth required)
    Route::post('/webhooks/midtrans', [PaymentController::class, 'midtransWebhook']);
    Route::post('/webhooks/xendit', [PaymentController::class, 'xenditWebhook']);
});