<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Wallet\WalletPayout;
use App\Models\Setting\AppSetting;
use App\Services\Common\Payment\Handlers\WalletPayoutHandler;
use App\Services\Common\Wallet\Payout\WalletPayoutReconciliationService;
use App\Services\Common\Wallet\Payout\WalletPayoutService;
use Illuminate\Http\Request;

class WalletPayoutApiController extends ApiResponseWithAdminAuthController
{
    /**
     * List payout requests
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'requested');

        $payouts = WalletPayout::with(['wallet.user', 'bank'])
            ->whereIn('status', match ($status) {
                'requested'  => [PayoutStatusEnum::REQUESTED->value],
                'processing' => [PayoutStatusEnum::PROCESSING->value],
                'paid'       => [PayoutStatusEnum::PAID->value],
                'failed'     => [PayoutStatusEnum::FAILED->value],
                default      => [
                    PayoutStatusEnum::REQUESTED->value,
                    PayoutStatusEnum::PROCESSING->value,
                ],
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return $this->successResponse('Payout requests fetched', $payouts);
    }

    /**
     * Approve payout (MANUAL or RAZORPAY)
     * ✔ ONE endpoint
     * ✔ Mode-driven
     * ✔ Wallet debit ONLY when appropriate
     */
    public function approve(Request $request, WalletPayout $payout)
    {
        $data = $request->validate([
            'mode' => 'required|in:razorpay,manual',
            'reference' => 'nullable|string|max:100',
        ]);

        $mode = $data['mode'];
        $reference = $data['reference'] ?? null;

        $appMode = AppSetting::payOutMode();

        // 🔒 Hard safety: app setting must match request
        if ($mode !== $appMode) {
            // 
            logActivity(
                'wallet_payout_approval_rejected_mode_mismatch',
                request()->user(),
                WalletPayout::class,
                $payout->id,
                $payout->payout_code,
                [
                    'requested_mode' => $mode,
                    'app_mode' => $appMode,
                ]
            );

            return $this->showErrorMessage(
                'Payout mode not allowed by system configuration',
                422
            );
        }

        // 🔒 Prevent double approval
        $payout->refresh();
        if ($payout->status !== PayoutStatusEnum::REQUESTED->value) {
            return $this->showErrorMessage(
                'Payout already processed',
                409
            );
        }

        /**
         * 🔹 MANUAL PAYOUT
         * Admin already transferred money outside system
         * → Wallet debit happens NOW
         */
        if ($mode === PaymentMethodEnum::MANUAL->value) {

            if (!$reference) {
                return $this->showErrorMessage(
                    'Reference is required for manual payout',
                    422
                );
            }

            app(WalletPayoutHandler::class)
                ->onManualSuccess($payout, $reference);

            //
            logActivity(
                'wallet_payout_manual_approved',
                request()->user(),
                WalletPayout::class,
                $payout->id,
                $payout->payout_code,
                [
                    'amount' => $payout->amount,
                    'reference' => $reference,
                ]
            );

            return $this->showSuccessMessage('Manual payout approved and settled');
        }

        /**
         * 🔹 RAZORPAY PAYOUT
         * Approval ONLY initiates Razorpay transfer
         * → Wallet debit happens via webhook / reconcile
         */
        if ($mode === PaymentMethodEnum::RAZORPAY->value) {

            app(WalletPayoutService::class)
                ->approveAndProcess($payout);

            // 
            logActivity(
                'wallet_payout_razorpay_approved',
                request()->user(),
                WalletPayout::class,
                $payout->id,
                $payout->payout_code,
                [
                    'amount' => $payout->amount,
                ]
            );

            return $this->showSuccessMessage('Razorpay payout initiated');
        }

        return $this->showErrorMessage('Invalid payout flow', 500);
    }



    /**
     * Force fail payout
     */
    public function fail(Request $request, WalletPayout $payout)
    {
        app(WalletPayoutHandler::class)->onFailure(
            $payout,
            $request->input('reason', 'Admin marked failed')
        );

        return $this->showSuccessMessage('Payout marked as failed');
    }

    /**
     * Reconcile Razorpay payout
     */
    public function reconcile(WalletPayout $payout)
    {
        app(WalletPayoutReconciliationService::class)
            ->reconcile($payout);

        return $this->showSuccessMessage(
            'Payout reconciled. Current status: ' . $payout->fresh()->status
        );
    }
}
