<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\CategoryProductController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\BrandController;       

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

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
    Route::get('/products/{slug}', [ProductController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']); // category filter here
    Route::get('/products/featured', [ProductController::class, 'featured']);
    Route::get('/products/top-selling', [ProductController::class, 'topSelling']);


    // Cart
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{id}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // Checkout
    Route::post('/checkout', [CheckoutController::class, 'placeOrder']);
    
