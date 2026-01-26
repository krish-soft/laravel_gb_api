<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Models\Common\Payment\Payout;
use App\Services\Common\Wallet\Payout\WalletPayoutService;
use App\Services\Common\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class PayoutHandler
{
    // public function onSuccess(Payout $payout, string $gatewayRef): void
    // {
    //     DB::transaction(function () use ($payout, $gatewayRef) {

    //         // 🔒 Idempotency #1: payout already finalized
    //         if ($payout->status === PayoutStatusEnum::PAID->value) {
    //             return;
    //         }

    //         $wallet = $payout->wallet;

    //         // 🔒 Idempotency #2: wallet transaction already exists
    //         $existingTxn = WalletTransaction::where('wallet_id', $wallet->id)
    //             ->where('reference', $payout->payout_code)
    //             ->lockForUpdate()
    //             ->first();

    //         if ($existingTxn) {
    //             // Ensure payout status is consistent
    //             $payout->update(['status' => PayoutStatusEnum::PAID->value]);
    //             return;
    //         }

    //         // 🔒 Safety check
    //         if ($wallet->available_balance < $payout->amount) {
    //             throw new RuntimeException('Wallet balance insufficient during payout finalize');
    //         }

    //         // 1️⃣ Create wallet transaction (record only)
    //         $txn = app(WalletService::class)->createTransaction(
    //             $wallet,
    //             $payout->amount,
    //             WalletTypeEnum::DEBIT,
    //             WalletStatusEnum::COMPLETED,
    //             [
    //                 'reference' => $payout->payout_code,
    //                 'payment_reference' => $gatewayRef,
    //                 'remarks' => 'Wallet payout to bank',
    //             ]
    //         );

    //         // 2️⃣ FINALIZE → ledger + balance update
    //         app(WalletService::class)->finalizeTransaction($txn);

    //         // 3️⃣ Mark payout as paid
    //         $payout->update([
    //             'status' => PayoutStatusEnum::PAID->value,
    //         ]);

    //         logActivity(
    //             'wallet_payout_success',
    //             request()?->user() ?? null,
    //             Payout::class,
    //             $payout->id,
    //             $payout->payout_code,
    //             [
    //                 'amount' => $payout->amount,
    //                 'gateway_ref' => $gatewayRef,
    //             ]
    //         );
    //     });
    // }

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
