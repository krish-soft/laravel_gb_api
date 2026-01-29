<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Payment\Payout;
use App\Models\Master\Setting\MstAppSetting;
use App\Services\Common\Payment\Handlers\PayoutHandler;

use Illuminate\Http\Request;

class PayoutApiController extends ApiResponseWithAdminAuthController
{
    /**
     * List payout requests
     */
    public function index(Request $request)
    {
        $status = $request->query('status', 'requested');

        $payouts = Payout::with(['user', 'userBank'])
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
     * ✔ Account debit ONLY when appropriate
     */
    public function approve(Request $request, Payout $payout)
    {
        $data = $request->validate([
            'mode' => 'required|in:razorpay,manual',
            'reference' => 'nullable|string|max:100',
        ]);

        $mode = $data['mode'];
        $reference = $data['reference'] ?? null;

        $appMode = MstAppSetting::payOutMode();

        // 🔒 Hard safety: app setting must match request
        if ($mode !== $appMode) {
            //
            logActivity(
                'wallet_payout_approval_rejected_mode_mismatch',
                request()->user(),
                Payout::class,
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
         * → debit happens NOW
         */
        if ($mode === PaymentMethodEnum::MANUAL->value) {

            if (!$reference) {
                return $this->showErrorMessage(
                    'Reference is required for manual payout',
                    422
                );
            }

            app(PayoutHandler::class)
                ->onManualSuccess($payout, $reference);

            //
            logActivity(
                'wallet_payout_manual_approved',
                request()->user(),
                Payout::class,
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
         * →  debit happens via webhook / reconcile
         */
        if ($mode === PaymentMethodEnum::RAZORPAY->value) {



            //
            logActivity(
                'wallet_payout_razorpay_approved',
                request()->user(),
                Payout::class,
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
    public function fail(Request $request, Payout $payout)
    {
        app(PayoutHandler::class)->onFailure(
            $payout,
            $request->input('reason', 'Admin marked failed')
        );

        return $this->showSuccessMessage('Payout marked as failed');
    }

    /**
     * Reconcile Razorpay payout
     */
    public function reconcile(Payout $payout)
    {
       
        // return $this->showSuccessMessage(
        //     'Payout reconciled. Current status: ' . $payout->fresh()->status
        // );
    }
}
