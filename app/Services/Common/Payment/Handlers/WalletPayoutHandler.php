<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Models\Common\Wallet\WalletPayout;
use App\Models\Common\Wallet\WalletTransaction;
use App\Services\Common\Wallet\WalletService;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Enum\Common\Wallet\WalletStatusEnum;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletPayoutHandler
{
    public function onSuccess(WalletPayout $payout, string $gatewayRef): void
    {
        DB::transaction(function () use ($payout, $gatewayRef) {

            // 🔒 Idempotency #1: payout already finalized
            if ($payout->status === PayoutStatusEnum::PAID->value) {
                return;
            }

            $wallet = $payout->wallet;

            // 🔒 Idempotency #2: wallet transaction already exists
            $existingTxn = WalletTransaction::where('wallet_id', $wallet->id)
                ->where('reference', $payout->payout_code)
                ->lockForUpdate()
                ->first();

            if ($existingTxn) {
                // Ensure payout status is consistent
                $payout->update(['status' => PayoutStatusEnum::PAID->value]);
                return;
            }

            // 🔒 Safety check
            if ($wallet->available_balance < $payout->amount) {
                throw new RuntimeException('Wallet balance insufficient during payout finalize');
            }

            // 1️⃣ Create wallet transaction (record only)
            $txn = app(WalletService::class)->createTransaction(
                $wallet,
                $payout->amount,
                WalletTypeEnum::DEBIT,
                WalletStatusEnum::COMPLETED,
                [
                    'reference' => $payout->payout_code,
                    'payment_reference' => $gatewayRef,
                    'remark' => 'Wallet payout to bank',
                ]
            );

            // 2️⃣ FINALIZE → ledger + balance update
            app(WalletService::class)->finalizeTransaction($txn);

            // 3️⃣ Mark payout as paid
            $payout->update([
                'status' =>PayoutStatusEnum::PAID->value,
            ]);

            logActivity(
                'wallet_payout_success',
                request()?->user() ?? null,
                WalletPayout::class,
                $payout->id,
                $payout->payout_code,
                [
                    'amount' => $payout->amount,
                    'gateway_ref' => $gatewayRef,
                ]
            );
        });
    }

    public function onFailure(WalletPayout $payout, string $reason): void
    {
        // 🔒 Idempotency
        if (in_array($payout->status, [PayoutStatusEnum::FAILED->value, PayoutStatusEnum::REJECTED->value])) {
            return;
        }

        $payout->update([
            'status' => PayoutStatusEnum::FAILED->value,
            'remark' => $reason,
        ]);

        logActivity(
            'wallet_payout_failed',
            request()?->user() ?? null,
            WalletPayout::class,
            $payout->id,
            $payout->payout_code,
            ['reason' => $reason]
        );
    }
}
