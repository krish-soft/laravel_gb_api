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
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,

            'app-checker' => \App\Http\Middleware\AppChecker::class,
            'ms-api-key-checker' => \App\Http\Middleware\MsApiKeyChecker::class,
            'user-checker' => \App\Http\Middleware\User\UserChecker::class,
            'admin-user-checker' => \App\Http\Middleware\Admin\AdminUserChecker::class, // Added Admin User Checker Middleware
            'user-legal-checker' => \App\Http\Middleware\User\UserLegalChecker::class,

            'buyer-checker' => \App\Http\Middleware\User\BuyerChecker::class,
            'seller-checker' => \App\Http\Middleware\User\SellerChecker::class,
            'delivery-checker' => \App\Http\Middleware\User\DeliveryChecker::class,

            //
            'buyer-cutoff' => \App\Http\Middleware\Cutoff\BuyerCutoffMiddleware::class,
            'seller-cutoff' => \App\Http\Middleware\Cutoff\SellerCutoffMiddleware::class,
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
