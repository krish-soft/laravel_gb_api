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
        // Get cutoff settings (cached)
        $cutoffSettings = MstCutoffSetting::getOrCreate();

        // Check only if buyer cutoff feature is enabled
        if ($cutoffSettings->is_buyer_auto_cutoff) {

            // Current date & time
            $now = now();

            // Convert stored buyer start time to today's datetime
            // Example: if DB value = 09:00:00
            // Result = 2026-03-30 09:00:00
            $start = now()->setTimeFromTimeString(
                $cutoffSettings->buyer_start_time->format('H:i:s')
            );

            // Convert stored buyer end time to today's datetime
            // Example: if DB value = 23:59:59
            // Result = 2026-03-30 23:59:59
            $end = now()->setTimeFromTimeString(
                $cutoffSettings->buyer_end_time->format('H:i:s')
            );

            // Check if current time is outside allowed purchasing time
            if (!$now->between($start, $end)) {
                return $this->showErrorMessage(
                    __('messages.error_messages.purchasing_not_allowed'),
                    403
                );
            }
        }

        // Continue request if within allowed time
        return $next($request);
    }
}
