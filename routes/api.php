<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Webhooks\OnePayWebhookController;
use App\Http\Controllers\API\Webhooks\KokoWebhookController;

// ✅ Example: API v1 routes file include
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});

Route::post('/webhooks/onepay', [OnePayWebhookController::class, 'handle']);
Route::post('/webhooks/koko', [KokoWebhookController::class, 'handle']);