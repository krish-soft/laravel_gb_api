<?php

namespace App\Services\Common\Wallet;

use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Common\Wallet\Wallet;
use App\Models\Common\Wallet\WalletLedger;
use App\Models\Common\Wallet\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /* =====================================================
     | CREATE TRANSACTION (RECORD ONLY)
     | - NO balance logic
     | - NO validation
     | - Pure intent recording
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
                'wallet_id' => $wallet->id,
                'user_code' => $wallet->user_code,

                'amount' => $amount,
                'type' => $type->value,
                'status' => $status->value,

                // Business meaning (order, payout, dispute, refund, etc)
                'description' => $meta['description'] ?? null,

                // Optional source linkage
                'source_type' => $meta['source_type'] ?? null,
                'source_id' => $meta['source_id'] ?? null,
                'source_code' => $meta['source_code'] ?? null,

                // Public / audit references
                'reference' => $meta['reference'] ?? null,
                'payment_reference' => $meta['payment_reference'] ?? null,
                'gateway' => $meta['gateway'] ?? null,

                // Internal/system note
                'remark' => $meta['remark'] ?? null,

                // Double-entry linkage
                'related_wallet_txn_id' => $meta['related_wallet_txn_id'] ?? null,
                'related_wallet_txn_code' => $meta['related_wallet_txn_code'] ?? null,
            ]);

            $wallet->updateQuietly([
                'last_transaction_at' => now(),
            ]);

            logActivity(
                'wallet_transaction_created',
                request()?->user() ?? null,
                WalletTransaction::class,
                $txn->id,
                $txn->reference,
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                    'type' => $type->value,
                    'status' => $status->value,
                ]
            );

            return $txn;
        });
    }

    /* =====================================================
     | FINALIZE TRANSACTION
     | - Creates ledger entry
     | - Mutates wallet balances
     | - NO balance validation
     =====================================================*/
    public function finalizeTransaction(WalletTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {

            // 🔒 Non-financial txn → skip ledger & balance
            if (!$txn->is_affecting_balance) {
                return;
            }

            if ($txn->status === WalletStatusEnum::CANCELLED->value) {
                return;
            }

            $wallet = $txn->wallet;
            $amount = $txn->amount;

            /*
             * HOLD → lock funds (escrow / reserve)
             */
            if ($txn->status === WalletStatusEnum::HOLD->value) {

                WalletLedger::create([
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $txn->id,
                    'credit' => $txn->type === WalletTypeEnum::CREDIT->value ? $amount : 0,
                    'debit'  => $txn->type === WalletTypeEnum::DEBIT->value ? $amount : 0,
                    'action' => 'hold',
                    'description' => $txn->description,
                ]);

                $wallet->increment('hold_balance', $amount);
            }

            /*
             * COMPLETED → real accounting movement
             */
            if ($txn->status === WalletStatusEnum::COMPLETED->value) {

                WalletLedger::create([
                    'wallet_id' => $wallet->id,
                    'wallet_transaction_id' => $txn->id,
                    'credit' => $txn->type === WalletTypeEnum::CREDIT->value ? $amount : 0,
                    'debit'  => $txn->type === WalletTypeEnum::DEBIT->value ? $amount : 0,
                    'action' => $txn->type === WalletTypeEnum::CREDIT->value ? 'credit' : 'debit',
                    'description' => $txn->description,
                ]);

                if ($txn->type === WalletTypeEnum::CREDIT->value) {
                    $wallet->increment('available_balance', $amount);
                } else {
                    // 🔥 NO BALANCE CHECK — BUSINESS DECIDED
                    $wallet->decrement('available_balance', $amount);
                }
            }

            $wallet->updateQuietly([
                'last_ledger_at' => now(),
            ]);

            logActivity(
                'wallet_transaction_finalized',
                request()?->user() ?? null,
                WalletTransaction::class,
                $txn->id,
                $txn->reference,
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                    'status' => $txn->status,
                ]
            );
        });
    }

    /* =====================================================
     | RELEASE HOLD → AVAILABLE
     =====================================================*/
    public function releaseHold(WalletTransaction $txn): void
    {
        DB::transaction(function () use ($txn) {

            if ($txn->status !== WalletStatusEnum::HOLD->value) {
                return;
            }

            $wallet = $txn->wallet;
            $amount = $txn->amount;

            WalletLedger::create([
                'wallet_id' => $wallet->id,
                'wallet_transaction_id' => $txn->id,
                'credit' => $amount,
                'debit' => 0,
                'action' => 'release',
                'description' => $txn->description,
            ]);

            $wallet->decrement('hold_balance', $amount);
            $wallet->increment('available_balance', $amount);

            $txn->updateQuietly([
                'status' => WalletStatusEnum::COMPLETED->value,
            ]);

            logActivity(
                'wallet_hold_released',
                request()?->user() ?? null,
                WalletTransaction::class,
                $txn->id,
                $txn->reference,
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $amount,
                ]
            );
        });
    }

    /* =====================================================
     | WALLET → WALLET TRANSFER (DOUBLE ENTRY)
     | - NO balance checks
     | - HOLD transactions untouched
     =====================================================*/
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        string $reason,
        array $meta = []
    ): array {

        return DB::transaction(function () use ($fromWallet, $toWallet, $amount, $reason, $meta) {

            // DEBIT
            $debitTxn = $this->createTransaction(
                $fromWallet,
                $amount,
                WalletTypeEnum::DEBIT,
                WalletStatusEnum::COMPLETED,
                array_merge($meta, [
                    'description' => $reason,
                    'remark' => 'transfer_out',
                ])
            );

            // CREDIT
            $creditTxn = $this->createTransaction(
                $toWallet,
                $amount,
                WalletTypeEnum::CREDIT,
                WalletStatusEnum::COMPLETED,
                [
                    'description' => $reason,
                    'remark' => 'transfer_in',
                    'related_wallet_txn_id' => $debitTxn->id,
                    'related_wallet_txn_code' => $debitTxn->reference,
                ]
            );

            // LINK BACK
            $debitTxn->updateQuietly([
                'related_wallet_txn_id' => $creditTxn->id,
                'related_wallet_txn_code' => $creditTxn->reference,
            ]);

            // FINALIZE
            $this->finalizeTransaction($debitTxn);
            $this->finalizeTransaction($creditTxn);

            logActivity(
                'wallet_transfer_completed',
                request()?->user() ?? null,
                WalletTransaction::class,
                $debitTxn->id,
                $debitTxn->reference,
                [
                    'from_wallet' => $fromWallet->id,
                    'to_wallet' => $toWallet->id,
                    'amount' => $amount,
                    'reason' => $reason,
                ]
            );

            return [$debitTxn, $creditTxn];
        });
    }

    /* =====================================================
     | CANCEL TRANSACTION (NO LEDGER IMPACT)
     =====================================================*/
    public function cancelTransaction(WalletTransaction $txn, string $reason = ''): void
    {
        if ($txn->status === WalletStatusEnum::COMPLETED->value) {
            return;
        }

        $txn->updateQuietly([
            'status' => WalletStatusEnum::CANCELLED->value,
            'remark' => $reason,
        ]);

        logActivity(
            'wallet_transaction_cancelled',
            request()?->user() ?? null,
            WalletTransaction::class,
            $txn->id,
            $txn->reference,
            [
                'reason' => $reason,
            ]
        );
    }

    /* =====================================================
     | READ MODELS (API HELPERS)
     =====================================================*/

    public function getBalance(Wallet $wallet): array
    {
        return [
            'available' => $wallet->available_balance,
            'hold' => $wallet->hold_balance,
            'net' => $wallet->available_balance - $wallet->hold_balance,
        ];
    }

    public function getLedger(
        Wallet $wallet,
        ?string $from = null,
        ?string $to = null
    ) {
        return WalletLedger::where('wallet_id', $wallet->id)
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
