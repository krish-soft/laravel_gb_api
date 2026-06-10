<?php

namespace App\Http\Middleware\User;

use App\Enum\Common\ActionCodeEnum;
use App\Enum\Common\Legal\KycStatusEnum;
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

        // Check for Depot Assigned or not 
        if ($user && !$user->hasAssignedDepot()) {
            return $this->showErrorMessageWithAction(__('messages.error_messages.depot_not_assigned'), 403);
        }

        // By passing KYC for now 
        // Secondly Check Kyc approved or not
        // KYC Start
        // if ($user && !$user->isKycApproved()) {

        //     // Check is it pending or have comment
        //     $kyc = $user->kyc ?? null;

        //     if ($kyc && $kyc->status === KycStatusEnum::PENDING->value) {
        //         $rviewComment = $kyc->review_comment ?? null;

        //         if ($rviewComment) {
        //             return $this->showErrorMessageWithAction(__('messages.error_messages.kyc_already_under_review' . ":'\n" . $rviewComment), 403, ActionCodeEnum::FORCE_KYC);
        //         }

        //         return $this->showErrorMessageWithAction(__('messages.error_messages.kyc_already_under_review'), 403, ActionCodeEnum::FORCE_KYC);
        //     }

        //     return $this->showErrorMessageWithAction(__('messages.error_messages.kyc_not_approved'), 403, ActionCodeEnum::FORCE_KYC);
        // }
        // KYC END



        return $next($request);
    }
}
