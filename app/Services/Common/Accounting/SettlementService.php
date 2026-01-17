<?php

namespace App\Services\Common\Accounting;

use App\Enum\Common\Settlement\SettlementTypeEnum;
use App\Models\Common\Accounting\Settlement;
use App\Models\Common\Wallet\Wallet;
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
     | - No wallet mutation
     | - Pure accounting decision
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

        if ($amount <= 0) {
            throw new RuntimeException('Invalid settlement amount');
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
                'status'           => SettlementTypeEnum::PENDING->value,
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
     | SETTLE: PLATFORM → USER
     | - Money already paid outside (NEFT / Cash / Razorpay)
     | - Wallet reflects accounting
     ===================================================== */
    public function settleToUser(
        User $admin,
        Settlement $settlement,
        Wallet $userWallet,
        string $externalReference
    ): void {

        $this->assertAdminAndPending($admin, $settlement);

        DB::transaction(function () use ($admin, $settlement, $userWallet, $externalReference) {

            $txn = app(WalletService::class)->createTransaction(
                $userWallet,
                $settlement->amount,
                WalletTypeEnum::CREDIT,
                WalletStatusEnum::COMPLETED,
                [
                    'description' => $settlement->reason,
                    'reference' => 'SETTLEMENT-' . $settlement->id,
                    'payment_reference' => $externalReference,
                    'remark' => 'platform_to_user',
                    'source_type' => Settlement::class,
                    'source_id' => $settlement->id,
                ]
            );

            app(WalletService::class)->finalizeTransaction($txn);

            $this->markSettled($settlement, $txn->id);

            logActivity(
                'settlement_paid_to_user',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'wallet_id' => $userWallet->id,
                    'amount' => $settlement->amount,
                    'reference' => $externalReference,
                ]
            );
        });
    }

    /* =====================================================
     | SETTLE: USER → PLATFORM
     | - Penalty / Commission / Recovery
     | - Wallet reflects deduction
     ===================================================== */
    public function settleFromUser(
        User $admin,
        Settlement $settlement,
        Wallet $userWallet
    ): void {

        $this->assertAdminAndPending($admin, $settlement);

        DB::transaction(function () use ($admin, $settlement, $userWallet) {

            $txn = app(WalletService::class)->createTransaction(
                $userWallet,
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

            $this->markSettled($settlement, $txn->id);

            logActivity(
                'settlement_recovered_from_user',
                $admin,
                Settlement::class,
                $settlement->id,
                null,
                [
                    'wallet_id' => $userWallet->id,
                    'amount' => $settlement->amount,
                ]
            );
        });
    }

    /* =====================================================
     | SETTLE: USER → USER
     | - Dispute / Adjustment / Compensation
     ===================================================== */
    public function settleBetweenUsers(
        User $admin,
        Settlement $settlement,
        Wallet $fromWallet,
        Wallet $toWallet
    ): void {

        $this->assertAdminAndPending($admin, $settlement);

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

            $this->markSettled($settlement, $creditTxn->id);

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
     | CANCEL SETTLEMENT (NO WALLET EFFECT)
     ===================================================== */
    public function cancel(
        User $admin,
        Settlement $settlement,
        string $reason
    ): void {

        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized');
        }

        if ($settlement->status !== SettlementTypeEnum::PENDING->value) {
            return;
        }

        $settlement->update([
            'status' => SettlementTypeEnum::CANCELLED->value,
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

    /* =====================================================
     | INTERNAL HELPERS
     ===================================================== */
    private function assertAdminAndPending(User $admin, Settlement $settlement): void
    {
        if (!$admin->isAdminManagement()) {
            throw new RuntimeException('Unauthorized settlement');
        }

        if ($settlement->status !== SettlementTypeEnum::PENDING->value) {
            throw new RuntimeException('Settlement already processed');
        }
    }

    private function markSettled(Settlement $settlement, int $walletTxnId): void
    {
        $settlement->update([
            'status' => 'settled',
            'wallet_transaction_id' => $walletTxnId,
            'settled_at' => now(),
        ]);
    }
}
