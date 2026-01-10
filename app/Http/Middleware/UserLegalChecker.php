<?php

namespace App\Http\Middleware;

use App\Enum\Legal\KycStatusEnum;
use App\Traits\ApiResponserTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserLegalChecker
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

        // Secondly Check Kyc approved or not 
        if ($user && !$user->isKycApproved()) {

            // Check is it pending or have comment
            $kyc = $user->kyc ?? null;

            if ($kyc && $kyc->status === KycStatusEnum::PENDING->value) {
                $rviewComment = $kyc->review_comment ?? null;

                if ($rviewComment) {
                    return $this->showErrorMessage(__('messages.error_messages.kyc_already_under_review' . ":'\n" . $rviewComment), 403);
                }

                return $this->showErrorMessage(__('messages.error_messages.kyc_already_under_review'), 403);
            }

            return $this->showErrorMessage(__('messages.error_messages.kyc_not_approved'), 403);
        }



        return $next($request);
    }
}
