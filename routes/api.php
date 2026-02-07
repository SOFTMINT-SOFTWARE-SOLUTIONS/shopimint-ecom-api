<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Webhooks\OnePayWebhookController;

// ✅ Example: API v1 routes file include
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});

Route::post('/webhooks/onepay', [OnePayWebhookController::class, 'handle']);