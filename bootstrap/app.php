<?php

use App\Exceptions\ApiExceptions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //

        $middleware->alias([
            'app-checker' => \App\Http\Middleware\AppChecker::class,
            'ms-api-key-checker' => \App\Http\Middleware\MsApiKeyChecker::class,
            'user-checker' => \App\Http\Middleware\UserChecker::class,
            'admin-user-checker' => \App\Http\Middleware\AdminUserChecker::class, // Added Admin User Checker Middleware
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
        // Globally Exceptions handle
        $exceptions->renderable(function (Exception $ex, $request) {
            $apiException = new ApiExceptions();

            return $apiException->handleException($request, $ex);
        });
    })->create();
