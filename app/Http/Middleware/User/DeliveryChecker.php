<?php

namespace App\Http\Middleware\User;

use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeliveryChecker
{
    use ApiResponserTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if (!$request->user()->isDelivery()) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        }


        return $next($request);
    }
}
