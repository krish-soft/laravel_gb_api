<?php

namespace App\Http\Controllers\Api\v1\Admin\Report;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Wallet\Wallet;
use App\Models\Common\Wallet\WalletLedger;
use App\Models\Common\Wallet\WalletTransaction;
use App\Services\Common\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletReportApiController extends ApiResponseWithAdminAuthController
{
    /* =====================================================
     | 1. SYSTEM LEVEL DASHBOARD
     | Total money status for accounting
     =====================================================*/
    public function dashboard()
    {
        /**
         * ============================
         * WALLET STATE
         * ============================
         */
        $wallets = [
            'total_available' => Wallet::sum('available_balance'),
            'total_hold'      => Wallet::sum('hold_balance'),
            'total_balance'   => Wallet::sum(DB::raw('available_balance + hold_balance')),
        ];

        /**
         * ============================
         * TRANSACTION STATE
         * ============================
         */
        $transactions = [
            'completed' => WalletTransaction::where('status', 'completed')->sum('amount'),
            'hold'      => WalletTransaction::where('status', 'hold')->sum('amount'),
            'cancelled' => WalletTransaction::where('status', 'cancelled')->sum('amount'),
        ];

        /**
         * ============================
         * ENTITY EXPOSURE (CORE)
         * WHO OWES WHOM
         * ============================
         */
        $exposure = WalletTransaction::select(
            'from_entity',
            'to_entity',
            DB::raw('SUM(amount) as amount')
        )
            ->where('status', '!=', 'completed')
            ->groupBy('from_entity', 'to_entity')
            ->get()
            ->map(fn($row) => [
                'from'   => $row->from_entity,
                'to'     => $row->to_entity,
                'amount' => (float) $row->amount,
            ]);

        /**
         * ============================
         * LEDGER SANITY
         * ============================
         */
        $ledger = [
            'credit' => WalletLedger::sum('credit'),
            'debit'  => WalletLedger::sum('debit'),
            'net'    => WalletLedger::sum('credit') - WalletLedger::sum('debit'),
        ];

        /**
         * ============================
         * AUDIT COUNTS
         * ============================
         */
        $counts = [
            'wallets'      => Wallet::count(),
            'transactions' => WalletTransaction::count(),
            'ledgers'      => WalletLedger::count(),
        ];

        return $this->successResponse('Wallet finance dashboard', [
            'wallets'      => $wallets,
            'transactions' => $transactions,
            'exposure'     => $exposure,
            'ledger'       => $ledger,
            'counts'       => $counts,
        ]);
    }


    /* =====================================================
     | 2. WALLET LIST (BUYER / SELLER / DELIVERY)
     =====================================================*/
    public function wallets(Request $request)
    {
        $wallets = Wallet::with('user')
            ->when(
                $request->user_id,
                fn($q) =>
                $q->where('user_id', $request->user_id)
            )
            ->orderBy('id', 'desc')
            ->get();

        //

        return $this->successResponse(__('messages.success_messages.success_get'), $wallets);
    }

    /* =====================================================
     | 3. SINGLE WALLET BALANCE
     =====================================================*/
    public function walletBalance(Wallet $wallet)
    {
        $balance = app(WalletService::class)->getBalance($wallet);

        return $this->successResponse(__('messages.success_messages.success_get'), $balance);
    }

    /* =====================================================
     | 4. WALLET LEDGER (DATE FILTER)
     =====================================================*/
    public function walletLedger(Request $request, Wallet $wallet)
    {
        $ledger = app(WalletService::class)->getLedger(
            $wallet,
            $request->from,
            $request->to
        );

        return $this->successResponse(__('messages.success_messages.success_get'), $ledger);
    }

    /* =====================================================
     | 5. WALLET TRANSACTIONS (AUDIT VIEW)
     =====================================================*/
    public function transactions(Request $request)
    {
        $txns = WalletTransaction::query()
            ->when(
                $request->wallet_id,
                fn($q) =>
                $q->where('wallet_id', $request->wallet_id)
            )
            ->when(
                $request->status,
                fn($q) =>
                $q->where('status', $request->status)
            )
            ->when(
                $request->from_entity,
                fn($q) =>
                $q->where('from_entity', $request->from_entity)
            )
            ->when(
                $request->to_entity,
                fn($q) =>
                $q->where('to_entity', $request->to_entity)
            )
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $txns);
    }

    /* =====================================================
     | 6. WHO OWES WHOM (CORE ACCOUNTING API)
     =====================================================*/
    public function owesSummary(Request $request)
    {
        $request->validate([
            'entity' => 'required|string',
            'entity_id' => 'required|integer',
        ]);

        $entity = $request->entity;
        $entityId = $request->entity_id;

        return $this->successResponse('Owes summary', [
            'owed_to_entity' => WalletTransaction::totalOwedTo($entity, $entityId),
            'owed_by_entity' => WalletTransaction::totalOwedBy($entity, $entityId),
            'net_position'   => WalletTransaction::netAmountFor($entity, $entityId),
        ]);
    }

    /* =====================================================
     | 7. PLATFORM PAYABLE / RECEIVABLE
     =====================================================*/
    public function platformExposure()
    {
        return $this->successResponse('Platform exposure', [
            'platform_owes_sellers' =>
            WalletTransaction::totalOwedTo('seller'),

            'platform_owes_buyers' =>
            WalletTransaction::totalOwedTo('buyer'),

            'users_owe_platform' =>
            WalletTransaction::totalOwedBy('platform'),
        ]);
    }

    /* =====================================================
     | 8. SINGLE TRANSACTION VERIFICATION
     =====================================================*/
    public function verifyTransaction(WalletTransaction $transaction)
    {
        return $this->successResponse('Transaction verification', [
            'is_settled' => $transaction->isSettled(),
            'has_ledger' => $transaction->ledgers()->exists(),
            'status'     => $transaction->status,
            'amount'     => $transaction->amount,
        ]);
    }
}
