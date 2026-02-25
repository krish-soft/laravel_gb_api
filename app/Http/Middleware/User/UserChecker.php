<?php

namespace App\Http\Middleware\User;

use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserChecker
{

    use ApiResponserTrait;


    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $token = $request->user()?->currentAccessToken();

        if ($token && $token->expires_at && now()->greaterThan($token->expires_at)) {
            $token->delete();
            // return $this->showErrorMessage('Unauthenticated. Token expired', 401);
            return $this->showErrorMessage(
                __('messages.error_messages.unauthenticated'),
                401
            );
        }

        // Check if user is active or inactive

        $user = $request->user();
        if ($user && !$user->is_active) {
            // return $this->showErrorMessage('Your account is inactive.\n' . $user->incactive_reason, 403);
            return $this->showErrorMessage(
                __('messages.error_messages.account_inactive') . '\n: ' . $user->inactive_reason,
                403
            );
        }

        // Check if its admin then ignore for this path
        if ($user && $user->isAdminManagement()) {
            // return $this->showErrorMessage('Access denied to this path(s) for admin users', 403);
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_access'),
                403
            );
        }

        // last login update
        if ($user) {
            $user->last_login_at = now();
            $user->last_login_ip = $request->ip();
            $user->save();
        }



        return $next($request);
    }
}
