<?php

namespace App\Http\Middleware\Admin;

use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminUserChecker
{
    use ApiResponserTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthenticated'),
                401
            );
        }

        if (!$user->isAdminManagement()) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_access_admin'),
                403
            );
        }




        return $next($request);
    }
}
