<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class AppChecker
{
    use ApiResponserTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        //
        if ($request->hasHeader('Accept-Language')) {
            App::setLocale($request->header('Accept-Language'));
        }

        // Check From env variable if app on maintance or live 
        $appStatus = env('APP_ENV', 'production');

        if ($appStatus === 'maintenance') {
            return $this->showErrorMessage("The application is currently under maintenance. Please try again later.", 503);
        }

        // Keep Pending to check Version code to check (optional)
        $appVersion = $request->header('X-APP-VERSION') ?? '';


        //


        return $next($request);
    }
}
