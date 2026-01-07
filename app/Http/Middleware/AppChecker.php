<?php

namespace App\Http\Middleware;

use App\Models\Setting\AppSetting;
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

        $appSetting = app(AppSetting::class)->first();
        if ($appSetting && $appSetting->maintenance_mode) {
            $appStatus = 'maintenance';
        }

        if ($appStatus === 'maintenance') {
            return $this->showErrorMessage(__('messages.error_messages.maintenance_mode'), 503);
        }

        // Keep Pending to check Version code to check (optional)
        $appVersion = $request->header('X-APP-VERSION') ?? '';


        //


        return $next($request);
    }
}
