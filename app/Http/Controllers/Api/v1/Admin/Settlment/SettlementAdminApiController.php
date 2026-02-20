<?php

namespace App\Http\Controllers\Api\v1\Admin\Settlment;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Accounting\Settlement\SettlementAccountLedger;
use App\Models\Common\Accounting\Settlement\SettlementBatch;
use App\Models\Master\MstFinancialYear;
use App\Models\Master\Setting\MstFinanceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettlementAdminApiController extends ApiResponseWithAdminAuthController
{

    /*
    |--------------------------------------------------------------------------
    | PREVIEW ENGINE (MAIN SOURCE OF TRUTH)
    |--------------------------------------------------------------------------
    */
    public function getPayoutSettlementPreview(Request $request)
    {
        $request->validate([
            'cutoff_date' => 'required|date',
            'owner_type'   => 'nullable|in:seller,buyer,delivery',
            'filter_type'  => 'nullable|in:need_to_pay_online,need_to_pay_cash,need_to_receive_online',
            'platform_account_id' => 'required|integer|exists:accounts,id',
        ]);

        $cutOffDate = $request->cutoff_date;

        $ledgerData  = $this->getAccountLedgerDataByCutOffDate($cutOffDate);
        $previewData = $ledgerData['preview'] ?? [];

        $platformAccountId = $request->platform_account_id;
        $platformAccount = Account::findOrFail($platformAccountId);


        /*
    |--------------------------------------------------------------------------
    | APPLY FILTER IF EXISTS
    |--------------------------------------------------------------------------
    */
        if ($request->owner_type && $request->filter_type) {

            $previewData = $this->applyCombinationFilter(
                $previewData,
                $request->owner_type,
                $request->filter_type
            );

            $platformBalance = $platformAccount->available_balance;



            /*
        |--------------------------------------------------------------------------
        | 🔥 REBUILD SUMMARY FROM FILTERED DATA (THIS WAS MISSING)
        |--------------------------------------------------------------------------
        */
            $totalCredit = array_sum(array_column($previewData, 'calc_total_credit'));
            $totalDebit  = array_sum(array_column($previewData, 'calc_total_debit'));
            $netAmount   = $totalCredit - $totalDebit;

            $platformRemainBalance = 0;
            if ($request->filter_type == 'need_to_receive_online' && $netAmount < 0) {

                $platformRemainBalance = $platformBalance + $netAmount; // since netAmount is negative here
            } else {

                $platformRemainBalance = $platformBalance - $netAmount;
            }

            $summary = [
                'total_credit' => (float)$totalCredit,
                'total_debit'  => (float)$totalDebit,
                'net_amount'   => (float)$netAmount,
                'platform_current_balance' => (float)$platformBalance,
                'platform_remaining_balance_after_settlement' => (float)$platformRemainBalance,
            ];
        } else {

            /*
        |--------------------------------------------------------------------------
        | DEFAULT FULL SUMMARY (OLD BEHAVIOR)
        |--------------------------------------------------------------------------
        */
            $summary = $ledgerData['summary'];
        }

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            [
                'summary' => $summary,
                'preview' => array_values($previewData),
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE BATCH (REUSES PREVIEW ENGINE)
    |--------------------------------------------------------------------------
    */
    public function createSettlementBatch(Request $request)
    {
        $request->validate([
            'cutoff_date' => 'required|date',
            'owner_type'   => 'required|in:seller,buyer,delivery',
            'filter_type'  => 'required|in:need_to_pay_online,need_to_pay_cash,need_to_receive_online',
        ]);

        /*
    |------------------------------------------------
    | PREVIEW DATA
    |------------------------------------------------
    */
        $previewResponse = $this->getPayoutSettlementPreview($request)->getData(true);
        $processData = $previewResponse['data']['preview'] ?? [];

        if (empty($processData)) {
            return $this->showErrorMessage('No settlement data found. Batch not created.');
        }

        /*
    |------------------------------------------------
    | COLLECT ALL LEDGER IDS
    |------------------------------------------------
    */
        $allLedgerIds = [];

        foreach ($processData as $pData) {
            foreach ($pData['ledgers'] ?? [] as $ledger) {
                $allLedgerIds[] = $ledger['id'];
            }
        }

        $allLedgerIds = array_unique($allLedgerIds);

        if (empty($allLedgerIds)) {
            return $this->showErrorMessage('No valid ledgers available for settlement.');
        }

        /*
    |------------------------------------------------
    | FETCH EXISTING SETTLED LEDGERS
    |------------------------------------------------
    */
        $existingLedgerIds = SettlementAccountLedger::whereIn(
            'account_ledger_id',
            $allLedgerIds
        )->pluck('account_ledger_id')
            ->toArray();

        /*
    |------------------------------------------------
    | FILTER PROCESS DATA (REMOVE DUPLICATES)
    |------------------------------------------------
    */
        $filteredProcessData = [];

        foreach ($processData as $pData) {

            $validLedgers = collect($pData['ledgers'] ?? [])
                ->reject(fn($ledger) => in_array($ledger['id'], $existingLedgerIds))
                ->values()
                ->toArray();

            // skip account if no valid ledgers left
            if (empty($validLedgers)) {
                continue;
            }

            // recalc totals for remaining ledgers
            $calcTotalCredit = array_sum(array_column($validLedgers, 'credit'));
            $calcTotalDebit  = array_sum(array_column($validLedgers, 'debit'));
            $calcNetAmount   = $calcTotalCredit - $calcTotalDebit;

            $pData['ledgers'] = $validLedgers;
            $pData['calc_total_credit'] = $calcTotalCredit;
            $pData['calc_total_debit']  = $calcTotalDebit;
            $pData['calc_net_amount']   = $calcNetAmount;

            $filteredProcessData[] = $pData;
        }

        /*
    |------------------------------------------------
    | STOP IF AFTER FILTER NOTHING LEFT
    |------------------------------------------------
    */
        if (empty($filteredProcessData)) {
            return $this->showErrorMessage(
                'All ledgers are already settled. Nothing new to create.'
            );
        }

        /*
    |------------------------------------------------
    | RECALCULATE FINAL TOTALS
    |------------------------------------------------
    */
        $totalCredit = array_sum(array_column($filteredProcessData, 'calc_total_credit'));
        $totalDebit  = array_sum(array_column($filteredProcessData, 'calc_total_debit'));
        $netAmount   = $totalCredit - $totalDebit;

        if (
            ($request->filter_type !== 'need_to_receive_online' && $netAmount <= 0) ||
            ($request->filter_type === 'need_to_receive_online' && $netAmount >= 0)
        ) {
            return $this->showErrorMessage('Invalid settlement combination.');
        }

        /*
    |------------------------------------------------
    | CREATE BATCH (TRANSACTION SAFE)
    |------------------------------------------------
    */
        DB::beginTransaction();

        try {

            $settlementBatch = SettlementBatch::create([
                'finance_year_id' => MstFinancialYear::currentFinancialYear()->id,
                'batch_date'      => date('Y-m-d'),
                'cutoff_date'     => $request->cutoff_date,
                'total_credit'    => $totalCredit,
                'total_debit'     => $totalDebit,
                'net_amount'      => $netAmount,
            ]);

            foreach ($filteredProcessData as $pData) {

                $settlementAccount = $settlementBatch->settlementAccounts()->create([
                    'finance_year_id'     => $settlementBatch->finance_year_id,
                    'user_account_id'     => $pData['account_id'],
                    'platform_account_id' => $request->platform_account_id,
                    'amount'              => $pData['calc_net_amount'],
                    'status'              => 'pending',
                ]);

                foreach ($pData['ledgers'] as $ledger) {

                    $settlementAccount->settlementAccountLedgers()->create([
                        'settlement_batch_id'   => $settlementBatch->id,
                        'settlement_account_id' => $settlementAccount->id,
                        'account_ledger_id'     => $ledger['id'],
                        'credit'                => $ledger['credit'],
                        'debit'                 => $ledger['debit'],
                    ]);
                }
            }

            DB::commit();

            return $this->successResponse(
                __('messages.success_messages.success_create'),
                ['settlement_batch_id' => $settlementBatch->id]
            );
        } catch (\Throwable $e) {

            DB::rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 🔥 COMBINATION FILTER (USED BY BOTH METHODS)
    |--------------------------------------------------------------------------
    */
    private function applyCombinationFilter(array $previewData, $ownerType, $filterType)
    {
        return array_filter($previewData, function ($row) use ($ownerType, $filterType) {

            if ($row['owner_type'] !== $ownerType) {
                return false;
            }

            $net = $row['calc_net_amount'];
            // ✅ CASH DETECTION FROM LEDGERS (OLD WORKING LOGIC)
            $isCash = collect($row['ledgers'])->contains(function ($ledger) {
                return str_starts_with($ledger['common_reference'] ?? '', 'MKT') || str_starts_with($ledger['source_code'] ?? '', 'MKT');
            });

            if ($filterType === 'need_to_pay_online') {
                return $net > 0 && !$isCash;
            }

            if ($filterType === 'need_to_pay_cash') {
                return $net > 0 && $isCash;
            }

            if ($filterType === 'need_to_receive_online') {
                return $net < 0 && !$isCash;
            }

            return true;
        });
    }


    /*
    |--------------------------------------------------------------------------
    | BASE DATA ENGINE (UNCHANGED)
    |--------------------------------------------------------------------------
    */
    private function getAccountLedgerDataByCutOffDate($cutOffDate)
    {
        $ledgers = AccountLedger::query()
            ->with(['account'])
            ->where('status', LedgerStatusEnum::AVAILABLE->value)
            ->whereDate('ledger_date', '<=', $cutOffDate)
            ->whereHas('account', function ($q) {
                $q->whereNotIn('accnt_code', PlatformAccountCodeEnum::casesAsValues());
            })
            ->get();

        $summaryData = [];

        foreach (
            [
                AccountOwnerTypeEnum::SELLER,
                AccountOwnerTypeEnum::BUYER,
                AccountOwnerTypeEnum::DELIVERY,
            ] as $ownerEnum
        ) {

            $owner = $ownerEnum->value;

            $rows = $ledgers->filter(
                fn($l) =>
                optional($l->account)->owner_type === $owner
            );

            $credit = $rows->sum('credit');
            $debit  = $rows->sum('debit');

            $summaryData[$owner] = [
                'owner_type'   => $owner,
                'total_credit' => (float) $credit,
                'total_debit'  => (float) $debit,
                'net_amount'   => (float) ($credit - $debit),
            ];
        }

        $previewData = [];

        $grouped = $ledgers->groupBy(function ($ledger) {

            $isCash =
                str_starts_with($ledger->common_reference ?? '', 'MKT')
                || str_starts_with($ledger->source_code ?? '', 'MKT');

            $paymentType = $isCash ? 'cash' : 'online';

            // ⭐ TWO LEVEL GROUP KEY
            return $paymentType . '_' . $ledger->account_id;
        });

        foreach ($grouped as $key => $rows) {

            $account = $rows->first()->account;

            $credit = $rows->sum('credit');
            $debit  = $rows->sum('debit');

            $isCashGroup = str_starts_with($key, 'cash_');

            $previewData[] = [

                'owner_type'   => $account->owner_type,
                'account_id'   => $account->id,
                'accnt_code'   => $account->accnt_code,
                'account_name' => $account->name,
                'currency'     => $account->currency,

                // ⭐ VERY IMPORTANT
                'payment_type' => $isCashGroup ? 'cash' : 'online',

                'calc_total_credit' => (float) $credit,
                'calc_total_debit'  => (float) $debit,
                'calc_net_amount'   => (float) ($credit - $debit),

                'ledgers' => $rows->map(fn($ledger) => [
                    'id' => $ledger->id,
                    'description' => $ledger->description,
                    'ledger_date' => $ledger->ledger_date,
                    'common_reference' => $ledger->common_reference,
                    'source_code' => $ledger->source_code,
                    'credit' => $ledger->credit,
                    'debit' => $ledger->debit,
                    'net_amount' => (float) ($ledger->credit - $ledger->debit),
                ])->values(),
            ];
        }

        ## Base on Account Id
        // $previewData = [];

        // foreach ($ledgers->groupBy('account_id') as $accountId => $rows) {

        //     $account = $rows->first()->account;

        //     $credit = $rows->sum('credit');
        //     $debit  = $rows->sum('debit');

        //     $previewData[] = [
        //         'owner_type'   => $account->owner_type,
        //         'account_id'   => $accountId,
        //         'accnt_code'   => $account->accnt_code,
        //         'account_name' => $account->name,
        //         'currency'     => $account->currency,

        //         'calc_total_credit' => (float) $credit,
        //         'calc_total_debit'  => (float) $debit,
        //         'calc_net_amount'   => (float) ($credit - $debit),

        //         'ledgers' => $rows->map(fn($ledger) => [
        //             'id' => $ledger->id,
        //             'description' => $ledger->description,
        //             'ledger_date' => $ledger->ledger_date,
        //             'common_reference' => $ledger->common_reference,
        //             'credit' => $ledger->credit,
        //             'debit' => $ledger->debit,
        //             'net_amount' => (float) ($ledger->credit - $ledger->debit),
        //         ])->values(),
        //     ];
        // }

        return [
            'summary' => $summaryData,
            'preview' => $previewData,
        ];
    }
}
