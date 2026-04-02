<?php

namespace App\Http\Middleware\Cutoff;

use App\Models\Master\Setting\MstCutoffSetting;
use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SellerCutoffMiddleware
{
    use ApiResponserTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Also Handle Cutoff Time for Listings if needed
        $cutoffSettings = MstCutoffSetting::getOrCreate();

        if ($cutoffSettings->is_seller_auto_cutoff) {

            // Current date & time
            $now = now();

            // Convert stored seller start time to today's datetime
            // Example: if DB value = 09:00:00
            // Result = 2026-03-30 09:00:00
            $start = now()->setTimeFromTimeString(
                $cutoffSettings->seller_start_time->format('H:i:s')
            );

            // Convert stored seller end time to today's datetime
            // Example: if DB value = 15:00:00
            // Result = 2026-03-30 15:00:00
            $end = now()->setTimeFromTimeString(
                $cutoffSettings->seller_end_time->format('H:i:s')
            );

            // Check if current time is outside allowed listing time
            if (!$now->between($start, $end)) {
                return $this->showErrorMessage(
                    __('messages.error_messages.listing_not_allowed'),
                    403
                );
            }
        }

        return $next($request);
    }
}
