<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Models\Common\Payment\Payout;
use Illuminate\Support\Facades\DB;

class PayoutHandler
{

    public function onSuccess(Payout $payout, string $gatewayRef): void
    {
        DB::transaction(function () use ($payout, $gatewayRef) {

            if ($payout->status === PayoutStatusEnum::PAID->value) {
                return;
            }

            // TODO:: LEDGER ENTRY + BALANCE DEDUCTION


            $payout->update([
                'status' => PayoutStatusEnum::PAID->value,
            ]);

            logActivity(
                'payout_razorpay_success',
                request()?->user(),
                Payout::class,
                $payout->id,
                $payout->payout_code,
                ['gateway_ref' => $gatewayRef]
            );
        });
    }


    public function onManualSuccess(Payout $payout, string $reference): void
    {
        DB::transaction(function () use ($payout, $reference) {

            if ($payout->status === PayoutStatusEnum::PAID->value) {
                return;
            }

            // TODO:: LEDGER ENTRY + BALANCE DEDUCTION
            //            app(WalletPayoutService::class)->createWalletPayoutDebitTransaction($payout, $reference);

            $payout->update([
                'status' => PayoutStatusEnum::PAID->value,
            ]);

            logActivity(
                'wallet_payout_manual_success',
                request()?->user(),
                Payout::class,
                $payout->id,
                $payout->payout_code,
                ['reference' => $reference]
            );
        });
    }


    public function onFailure(Payout $payout, string $reason): void
    {
        // 🔒 Idempotency
        if (in_array($payout->status, [PayoutStatusEnum::FAILED->value, PayoutStatusEnum::REJECTED->value])) {
            return;
        }

        $payout->update([
            'status' => PayoutStatusEnum::FAILED->value,
            'remarks' => $reason,
        ]);

        logActivity(
            'payout_failed',
            request()?->user() ?? null,
            Payout::class,
            $payout->id,
            $payout->payout_code,
            ['reason' => $reason]
        );
    }
}
