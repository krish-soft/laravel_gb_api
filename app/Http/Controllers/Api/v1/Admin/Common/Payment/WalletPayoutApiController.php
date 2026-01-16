<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Wallet\WalletPayout;
use App\Services\Common\Payment\Handlers\WalletPayoutHandler;
use App\Services\Common\Wallet\WalletPayoutReconciliationService;
use App\Services\Common\Wallet\WalletPayoutService;
use Illuminate\Http\Request;

class WalletPayoutApiController extends ApiResponseWithAdminAuthController
{
    public function approve(WalletPayout $payout)
    {
        app(WalletPayoutService::class)->approveAndProcess($payout);

        return $this->showSuccessMessage('Payout approved');
    }

    public function forceSuccess(WalletPayout $payout)
    {
        app(WalletPayoutHandler::class)
            ->onSuccess($payout, 'admin_force');

        return $this->showSuccessMessage('Payout marked as paid');
    }

    public function forceFail(Request $request, WalletPayout $payout)
    {
        app(WalletPayoutHandler::class)
            ->onFailure(
                $payout,
                $request->input('reason', 'Admin marked failed')
            );

        return $this->showSuccessMessage('Payout marked as failed');
    }

    public function reconcile(WalletPayout $payout)
    {
        app(WalletPayoutReconciliationService::class)
            ->reconcile($payout);


        return $this->showSuccessMessage('Payout reconciled. ' . 'Current status: ' . $payout->fresh()->status);
        // return response()->json([
        //     'status' => $payout->fresh()->status,
        // ]);
    }
}
