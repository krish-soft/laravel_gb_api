<?php

namespace App\Http\Controllers\Api\v1\Admin\Settlment;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Accounting\AccountLedger;
use Illuminate\Http\Request;

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
            'cut_off_date' => 'required|date',
            'owner_type'   => 'nullable|in:seller,buyer,delivery',
            'filter_type'  => 'nullable|in:need_to_pay_online,need_to_pay_cash,need_to_receive_online',
            // 'source_account_id' => 'required|integer|exists:accounts,id',
        ]);

        $cutOffDate = $request->cut_off_date;

        $ledgerData  = $this->getAccountLedgerDataByCutOffDate($cutOffDate);
        $previewData = $ledgerData['preview'] ?? [];

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

            /*
        |--------------------------------------------------------------------------
        | 🔥 REBUILD SUMMARY FROM FILTERED DATA (THIS WAS MISSING)
        |--------------------------------------------------------------------------
        */
            $totalCredit = array_sum(array_column($previewData, 'calc_total_credit'));
            $totalDebit  = array_sum(array_column($previewData, 'calc_total_debit'));

            $summary = [
                'total_credit' => (float)$totalCredit,
                'total_debit'  => (float)$totalDebit,
                'net_amount'   => (float)($totalCredit - $totalDebit),
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
            'cut_off_date' => 'required|date',
            'owner_type'   => 'required|in:seller,buyer,delivery',
            'filter_type'  => 'required|in:need_to_pay_online,need_to_pay_cash,need_to_receive_online',
        ]);

        /*
        |------------------------------------------------
        | CALL SAME PREVIEW LOGIC (NO DUPLICATION)
        |------------------------------------------------
        */
        $previewResponse = $this->getPayoutSettlementPreview($request)->getData(true);

        $processData = $previewResponse['data']['preview'] ?? [];

        /*
        |------------------------------------------------
        | CALCULATE SUMMARY FOR BATCH
        |------------------------------------------------
        */
        $totalCredit = array_sum(array_column($processData, 'calc_total_credit'));
        $totalDebit  = array_sum(array_column($processData, 'calc_total_debit'));
        $netAmount   = $totalCredit - $totalDebit;

        if (
            ($request->filter_type !== 'need_to_receive_online' && $netAmount <= 0) ||
            ($request->filter_type === 'need_to_receive_online' && $netAmount >= 0)
        ) {
            return $this->showErrorMessage('Invalid settlement combination.');
        }

        /*
        |------------------------------------------------
        | CREATE BATCH HERE
        |------------------------------------------------
        */
        // SettlementBatch::create([...]);

        return $this->successResponse(
            __('messages.success_messages.settlement_batch_created'),
            [
                'summary' => [
                    'total_credit' => $totalCredit,
                    'total_debit'  => $totalDebit,
                    'net_amount'   => $netAmount,
                ],
                'details' => $processData,
            ]
        );
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
