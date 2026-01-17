<?php

namespace App\Services\Common\Accounting;

use App\Models\Common\Accounting\Settlement;
use App\Models\Common\Wallet\Wallet;
use App\Models\Common\Wallet\WalletTransaction;
use App\Services\Common\Wallet\WalletService;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettlementService
{
    /* =====================================================
     | CREATE SETTLEMENT (ADMIN ONLY)
     ===================================================== */
    public function create(
        User $admin,
        string $fromType,
        int|string|null $fromId,
        string $toType,
        int|string|null $toId,
        float $amount,
        string $reason,
        ?string $sourceType = null,
        ?int $sourceId = null
    ): Settlement {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized settlement creation');
        }

        return DB::transaction(function () use (
            $admin,
            $fromType,
            $fromId,
            $toType,
            $toId,
            $amount,
            $reason,
            $sourceType,
            $sourceId
        ) {

            $settlement = Settlement::create([
                'from_entity_type' => $fromType,
                'from_entity_id'   => $fromId,
                'to_entity_type'   => $toType,
                'to_entity_id'     => $toId,
                'amount'           => $amount,
                'reason'           => $reason,
                'source_type'      => $sourceType,
                'source_id'        => $sourceId,
                'status'           => 'pending',
            ]);

            logActivity(
                'settlement_created',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'from' => "{$fromType}:{$fromId}",
                    'to'   => "{$toType}:{$toId}",
                    'amount' => $amount,
                    'reason' => $reason,
                ]
            );

            return $settlement;
        });
    }

    /* =====================================================
     | SETTLE → PLATFORM → USER
     | (Manual / NEFT / Razorpay already done)
     ===================================================== */
    public function settleToUser(
        User $admin,
        Settlement $settlement,
        Wallet $wallet,
        string $reference
    ): void {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized settlement');
        }

        if ($settlement->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($admin, $settlement, $wallet, $reference) {

            $txn = app(WalletService::class)->createTransaction(
                $wallet,
                $settlement->amount,
                WalletTypeEnum::CREDIT,
                WalletStatusEnum::COMPLETED,
                [
                    'description' => $settlement->reason,
                    'reference' => 'SETTLEMENT-' . $settlement->id,
                    'payment_reference' => $reference,
                    'remark' => 'platform_to_user',
                    'source_type' => Settlement::class,
                    'source_id' => $settlement->id,
                ]
            );

            app(WalletService::class)->finalizeTransaction($txn);

            $settlement->update([
                'status' => 'settled',
                'wallet_transaction_id' => $txn->id,
                'settled_at' => now(),
            ]);

            logActivity(
                'settlement_paid_to_user',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $settlement->amount,
                    'reference' => $reference,
                ]
            );
        });
    }

    /* =====================================================
     | SETTLE → USER → PLATFORM
     | (Penalty / Recovery / Commission)
     ===================================================== */
    public function settleFromUser(
        User $admin,
        Settlement $settlement,
        Wallet $wallet
    ): void {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized settlement');
        }

        if ($settlement->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($admin, $settlement, $wallet) {

            $txn = app(WalletService::class)->createTransaction(
                $wallet,
                $settlement->amount,
                WalletTypeEnum::DEBIT,
                WalletStatusEnum::COMPLETED,
                [
                    'description' => $settlement->reason,
                    'reference' => 'SETTLEMENT-' . $settlement->id,
                    'remark' => 'user_to_platform',
                    'source_type' => Settlement::class,
                    'source_id' => $settlement->id,
                ]
            );

            app(WalletService::class)->finalizeTransaction($txn);

            $settlement->update([
                'status' => 'settled',
                'wallet_transaction_id' => $txn->id,
                'settled_at' => now(),
            ]);

            logActivity(
                'settlement_recovered_from_user',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'wallet_id' => $wallet->id,
                    'amount' => $settlement->amount,
                ]
            );
        });
    }

    /* =====================================================
     | USER → USER SETTLEMENT (OPTIONAL)
     ===================================================== */
    public function settleBetweenUsers(
        User $admin,
        Settlement $settlement,
        Wallet $fromWallet,
        Wallet $toWallet
    ): void {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized settlement');
        }

        if ($settlement->status !== 'pending') {
            return;
        }

        DB::transaction(function () use ($admin, $settlement, $fromWallet, $toWallet) {

            [$debitTxn, $creditTxn] = app(WalletService::class)->transfer(
                $fromWallet,
                $toWallet,
                $settlement->amount,
                $settlement->reason,
                [
                    'source_type' => Settlement::class,
                    'source_id' => $settlement->id,
                ]
            );

            $settlement->update([
                'status' => 'settled',
                'wallet_transaction_id' => $creditTxn->id,
                'settled_at' => now(),
            ]);

            logActivity(
                'settlement_user_to_user',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'from_wallet' => $fromWallet->id,
                    'to_wallet' => $toWallet->id,
                    'amount' => $settlement->amount,
                ]
            );
        });
    }

    /* =====================================================
     | CANCEL SETTLEMENT
     ===================================================== */
    public function cancel(
        User $admin,
        Settlement $settlement,
        string $reason
    ): void {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized');
        }

        if ($settlement->status !== 'pending') {
            return;
        }

        $settlement->update([
            'status' => 'cancelled',
        ]);

        logActivity(
            'settlement_cancelled',
            $admin,
            Settlement::class,
            $settlement->id,
            null,
            ['reason' => $reason]
        );
    }
}
