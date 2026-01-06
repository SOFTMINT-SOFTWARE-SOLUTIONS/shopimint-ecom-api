<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ✅ This is required for Sanctum cookie auth (Next.js)
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // (Optional) If you want CORS globally handled, usually not needed if config/cors.php is correct.
        // $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
