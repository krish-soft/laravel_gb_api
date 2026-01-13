<?php

namespace App\Services\Common\Wallet;

use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Common\Wallet\Wallet;
use App\Models\Common\Wallet\WalletTransaction;
use App\Models\Common\Wallet\WalletLedger;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    /* =====================================================
     | Create Transaction (INTERMEDIATE)
     =====================================================*/

    public function createTransaction(
        Wallet $wallet,
        float $amount,
        WalletTypeEnum $type,
        WalletStatusEnum $status,
        array $meta = []
    ): WalletTransaction {

        return DB::transaction(function () use ($wallet, $amount, $type, $status, $meta) {

            $txn = WalletTransaction::create([
                'wallet_id'      => $wallet->id,
                'user_code'      => $wallet->user_code,

                'amount'         => $amount,
                'type'           => $type->value,
                'status'         => $status->value,
                'description'    => $meta['description'] ?? null,

                'source_type'    => $meta['source_type'] ?? null,
                'source_id'      => $meta['source_id'] ?? null,
                'source_code'    => $meta['source_code'] ?? null,

                'reference'      => $meta['reference'] ?? null,
                'payment_reference' => $meta['payment_reference'] ?? null,
                'gateway'        => $meta['gateway'] ?? null,
                'remark'         => $meta['remark'] ?? null,
            ]);

            $wallet->updateQuietly([
                'last_transaction_at' => now(),
            ]);

            return $txn;
        });
    }

    /* =====================================================
     | Finalize Transaction → LEDGER + WALLET
     =====================================================*/

    public function finalizeTransaction(WalletTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {

            if ($txn->status === WalletStatusEnum::CANCELLED->value) {
                return;
            }

            $wallet = $txn->wallet;
            $amount = $txn->amount;

            // HOLD → lock money
            if ($txn->status === WalletStatusEnum::HOLD->value) {

                WalletLedger::create([
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $txn->id,
                    'credit' => $txn->type === 'credit' ? $amount : 0,
                    'debit'  => $txn->type === 'debit'  ? $amount : 0,
                    'action' => 'hold',
                    'description' => $txn->description,
                ]);

                $wallet->increment('hold_balance', $amount);
            }

            // COMPLETED → direct balance
            if ($txn->status === WalletStatusEnum::COMPLETED->value) {

                WalletLedger::create([
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $txn->id,
                    'credit' => $txn->type === 'credit' ? $amount : 0,
                    'debit'  => $txn->type === 'debit'  ? $amount : 0,
                    'action' => 'completed',
                    'description' => $txn->description,
                ]);

                if ($txn->type === 'credit') {
                    $wallet->increment('available_balance', $amount);
                } else {
                    if ($wallet->available_balance < $amount) {
                        throw new RuntimeException('Insufficient balance');
                    }
                    $wallet->decrement('available_balance', $amount);
                }
            }

            $wallet->updateQuietly([
                'last_ledger_at' => now(),
            ]);
        });
    }

    /* =====================================================
     | Release HOLD → move to available
     =====================================================*/

    public function releaseHold(WalletTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {

            if ($txn->status !== WalletStatusEnum::HOLD->value) {
                throw new RuntimeException('Transaction is not in HOLD state');
            }

            $wallet = $txn->wallet;
            $amount = $txn->amount;

            WalletLedger::create([
                'wallet_id' => $wallet->id,
                'wallet_transaction_id' => $txn->id,
                'credit' => $amount,
                'debit'  => 0,
                'action' => 'release',
                'description' => 'Hold released',
            ]);

            $wallet->decrement('hold_balance', $amount);
            $wallet->increment('available_balance', $amount);

            $txn->updateQuietly([
                'status' => WalletStatusEnum::RELEASED->value,
            ]);

            $wallet->updateQuietly([
                'last_ledger_at' => now(),
            ]);
        });
    }

    /* =====================================================
     | Cancel Transaction (NO BALANCE CHANGE)
     =====================================================*/

    public function cancelTransaction(WalletTransaction $txn): void
    {
        $txn->updateQuietly([
            'status' => WalletStatusEnum::CANCELLED->value,
        ]);
    }



    //
}
