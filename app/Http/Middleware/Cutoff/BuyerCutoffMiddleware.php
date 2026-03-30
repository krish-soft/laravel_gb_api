<?php

namespace App\Http\Middleware\Cutoff;

use App\Models\Master\Setting\MstCutoffSetting;
use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BuyerCutoffMiddleware
{
    use ApiResponserTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Also Handle Cutoff Time for Orders if needed
        $cutoffSettings = MstCutoffSetting::getOrCreate();

        if ($cutoffSettings->is_buyer_auto_cutoff) {

            $now = now();

            $start = $cutoffSettings->buyer_start_time;
            $end = $cutoffSettings->buyer_end_time;

            if ($now->lt($start) || $now->gt($end)) {
                return $this->showErrorMessage(__('messages.error_messages.purchasing_not_allowed'), 403);
            }
        }



        return $next($request);
    }
}
