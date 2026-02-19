<?php

namespace App\Http\Controllers\Api\v1\Admin\Settlment;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\AccountLedger;
use Illuminate\Http\Request;

class SettlementAdminApiController extends ApiResponseWithAdminAuthController
{
    //



    public function getPayoutSettlementPreview(Request $request)
    {
        $request->validate([
            'cut_off_date' => 'required|date',
        ]);

        $cutOffDate = $request->input('cut_off_date');


        $ledgersData = $this->getAccountLedgerDataByCutOffDate($cutOffDate);

        /**
         * ------------------------------------------
         * FINAL RESPONSE
         * ------------------------------------------
         */
        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $ledgersData
        );
    }


    // Now create Settlement Batch To Process Furher to Payout 

    public function createSettlementBatch(Request $request)
    {
        $request->validate([
            'cut_off_date' => 'required|date',
            'owner_type' => 'required|string|in:seller,buyer,delivery',
        ]);

        $cutOffDate = $request->input('cut_off_date');
        $ownerType = $request->input('owner_type');
        // Logic to create settlement batch based on cut-off date and owner type

        $ledgerData = $this->getAccountLedgerDataByCutOffDate($cutOffDate);
        $previewData = $ledgerData['preview'] ?? [];

        // Filter preview data based on owner type
        $filteredData = array_filter($previewData, function ($item) use ($ownerType) {
            return $item['owner_type'] === $ownerType;
        });

        // Now create Batch and then create payout for entry in batch and mark ledger as settled

        

        // Here you can implement the logic to create settlement batch using $filteredData

        return $this->successResponse(
            __('messages.success_messages.settlement_batch_created'),
            ['cut_off_date' => $cutOffDate]
        );
    }





    // make common 
    private function getAccountLedgerDataByCutOffDate($cutOffDate)
    {
        /*
    |--------------------------------------------------------------------------
    | Base Query (USE RELATION — NO JOIN)
    |--------------------------------------------------------------------------
    */
        $baseQuery = AccountLedger::query()
            ->with(['account'])
            ->where('status', LedgerStatusEnum::AVAILABLE->value)
            ->whereDate('ledger_date', '<=', $cutOffDate)
            ->whereHas('account', function ($q) {
                $q->whereNotIn('accnt_code', PlatformAccountCodeEnum::casesAsValues());
            });

        /*
    |--------------------------------------------------------------------------
    | LOAD LEDGERS ONCE
    |--------------------------------------------------------------------------
    */
        $ledgers = $baseQuery->get();

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ SUMMARY DATA (OWNER TYPE TOTALS)
    |--------------------------------------------------------------------------
    */
        $summaryData = [];

        foreach (
            [
                AccountOwnerTypeEnum::SELLER,
                AccountOwnerTypeEnum::BUYER,
                AccountOwnerTypeEnum::DELIVERY,
            ] as $ownerEnum
        ) {

            $owner = $ownerEnum->value;

            $rows = $ledgers->filter(function ($l) use ($owner) {
                return optional($l->account)->owner_type === $owner;
            });

            $totalCredit = $rows->sum('credit');
            $totalDebit  = $rows->sum('debit');

            $summaryData[$owner] = [
                'owner_type'   => $owner,
                'total_credit' => (float) $totalCredit,
                'total_debit'  => (float) $totalDebit,
                'net_amount'   => (float) ($totalCredit - $totalDebit),
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ PREVIEW DATA (ACCOUNT_ID WISE — DETAIL VIEW)
    |--------------------------------------------------------------------------
    */
        $previewData = [];

        $ledgersByAccount = $ledgers->groupBy('account_id');

        foreach ($ledgersByAccount as $accountId => $rows) {

            $account = $rows->first()->account;

            $totalCredit = $rows->sum('credit');
            $totalDebit  = $rows->sum('debit');

            $previewData[] = [
                // DETAIL IS ACCOUNT BASED
                'owner_type'   => $account->owner_type,
                'account_id'   => $accountId,
                'accnt_code'   => $account->accnt_code,
                'account_name' => $account->name,
                'currency'     => $account->currency,

                'calc_total_credit' => (float) $totalCredit,
                'calc_total_debit'  => (float) $totalDebit,
                'calc_net_amount'   => (float) ($totalCredit - $totalDebit),

                'ledgers' => $rows->map(function ($ledger) {
                    return [
                        'id'          => $ledger->id,
                        'description' => $ledger->description,
                        'ledger_date' => $ledger->ledger_date,
                        'common_reference' => $ledger->common_reference,
                        'credit'      => $ledger->credit,
                        'debit'       => $ledger->debit,
                        'net_amount' => (float) ($ledger->credit - $ledger->debit),
                    ];
                })->values(),
            ];
        }

        /*
    |--------------------------------------------------------------------------
    | FINAL RESPONSE
    |--------------------------------------------------------------------------
    */
        return [
            'summary' => $summaryData,   // OWNER TYPE TOTALS
            'preview' => $previewData,   // ACCOUNT_ID DETAIL
        ];
    }




    //
}
