<?php

use Illuminate\Support\Facades\Route;

// ✅ Example: API v1 routes file include
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});

use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CheckoutController;

Route::prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'show']);
    Route::post('/items', [CartController::class, 'addItem']);
    Route::put('/items', [CartController::class, 'updateItem']);
    Route::delete('/clear', [CartController::class, 'clear']);
});
Route::post('/checkout', [CheckoutController::class, 'checkout']);


use App\Http\Controllers\API\OrderController;
Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);


use App\Http\Controllers\API\PaymentStartController;
Route::post('/payments/start', [PaymentStartController::class, 'start']);

use App\Http\Controllers\API\Webhooks\PayHereWebhookController;
Route::post('/webhooks/payhere/notify', [PayHereWebhookController::class, 'notify']);

