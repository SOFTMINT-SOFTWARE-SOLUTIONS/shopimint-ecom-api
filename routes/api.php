<?php

use Illuminate\Support\Facades\Route;

// ✅ Example: API v1 routes file include
Route::prefix('v1')->group(function () {
    require __DIR__ . '/api/v1.php';
});