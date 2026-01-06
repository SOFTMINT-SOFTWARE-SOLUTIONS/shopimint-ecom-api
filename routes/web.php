<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\Admin\OrderPrintController;
use Filament\Http\Middleware\Authenticate;

Route::middleware(['web', Authenticate::class])->group(function () {
    Route::get('/admin/orders/{orderNumber}/invoice', [OrderPrintController::class, 'invoice'])
        ->name('admin.orders.invoice');

    Route::get('/admin/orders/{orderNumber}/shipping-label', [OrderPrintController::class, 'shippingLabel'])
        ->name('admin.orders.shippingLabel');
});


