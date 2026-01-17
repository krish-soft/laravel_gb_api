<?php

namespace App\Services\Common\Wallet\Payout;

use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Models\Common\Wallet\WalletPayout;
use App\Services\Common\Payment\Gateways\RazorpayPayoutService;
use App\Services\Common\Payment\Handlers\WalletPayoutHandler;

class WalletPayoutReconciliationService
{
    public function reconcile(WalletPayout $payout): void
    {
        if (!in_array($payout->status, [PayoutStatusEnum::REQUESTED->value, PayoutStatusEnum::PROCESSING->value])) {
            return;
        }

        if (!$payout->razorpay_payout_id) {
            return;
        }

        $gateway = app(RazorpayPayoutService::class);
        $handler = app(WalletPayoutHandler::class);

        $razorpayPayout = $gateway->fetchPayout($payout->razorpay_payout_id);

        if (!$razorpayPayout) {
            return;
        }

        if ($razorpayPayout['status'] === PayoutStatusEnum::PROCESSED->value) {
            $handler->onSuccess(
                $payout,
                $razorpayPayout['id']
            );
        }

        if ($razorpayPayout['status'] === PayoutStatusEnum::FAILED->value) {
            $handler->onFailure(
                $payout,
                $razorpayPayout['failure_reason'] ?? 'Razorpay payout failed'
            );
        }
    }
}
