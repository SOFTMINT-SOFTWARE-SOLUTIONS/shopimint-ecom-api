<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Auth\AuthController;
use App\Http\Controllers\API\V1\Catalog\CategoryController;
use App\Http\Controllers\API\V1\Catalog\CategoryProductController;
use App\Http\Controllers\API\V1\Catalog\ProductController;
use App\Http\Controllers\API\V1\Catalog\BrandController;
    
    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // Categories & Brands
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);
    Route::get('/categories/{slug}/products', [CategoryProductController::class, 'index']);
    Route::get('/brands', [BrandController::class, 'index']);

    // Products
    // Route::get('/products', [ProductController::class, 'index']);
    // Route::get('/products/{slug}', [ProductController::class, 'show']);
    // Route::get('/products/featured', [ProductController::class, 'featured']);
    // Route::get('/products/best-selling', [ProductController::class, 'bestSelling']);
    // Route::get('/products/new', [ProductController::class, 'newArrivals']);

    // use App\Http\Controllers\API\V1\Catalog\ProductController;
    Route::get('/products', [ProductController::class, 'index']); // category filter here
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/top-selling', [ProductController::class, 'topSelling']);
    Route::get('/products/{slug}', [ProductController::class, 'show']);

    // Cart
    use App\Http\Controllers\API\V1\CartController;
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/add', [CartController::class, 'addItem']);
    Route::post('/cart/update', [CartController::class, 'updateItem']);
    Route::post('/cart/clear', [CartController::class, 'clear']);
    Route::post('/checkout', [CheckoutController::class, 'checkout']);
    Route::post('/payments/start', [PaymentStartController::class, 'start']);

    // Checkout
    use App\Http\Controllers\API\V1\CheckoutController;
    Route::post('/checkout', [CheckoutController::class, 'checkout']);

    use App\Http\Controllers\API\V1\OrderController;
    Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);


    use App\Http\Controllers\API\V1\PaymentStartController;
    Route::post('/payments/start', [PaymentStartController::class, 'start']);

    use App\Http\Controllers\API\Webhooks\PayHereWebhookController;
    Route::post('/webhooks/payhere/notify', [PayHereWebhookController::class, 'notify']);
    
