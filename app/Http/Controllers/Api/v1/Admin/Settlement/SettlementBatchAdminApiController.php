<?php

namespace App\Http\Controllers\Api\v1\Admin\Settlement;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Settlement\SettlementAccount;
use App\Models\Common\Accounting\Settlement\SettlementBatch;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementBatchAdminApiController extends ApiResponseWithAdminAuthController
{
    //


    public function getSettlementBatchList(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);


        $startDate = $request->filled('start_date')
            ? \Carbon\Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $endDate = $request->filled('end_date')
            ? \Carbon\Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();


        $batchQuery = SettlementBatch::latest();
        $batchQuery->whereDate('batch_date', '>=', $startDate)
            ->whereDate('batch_date', '<=', $endDate);
        $batches = $batchQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $batches);

        //
    }


    public function getSettlementBatchDetails($id)
    {
        $batch = SettlementBatch::with([
            'financialYear',
            'settlementAccounts.userAccount.user',
            'settlementAccounts.platformAccount',
            'settlementAccounts.settlementAccountLedgers.accountLedger'
        ])->findOrFail($id);



        return $this->successResponse(__('messages.success_messages.success_get'), $batch);
    }


    public function getAccountBankDetails($settlementAccountId)
    {
        $settlementAccount = SettlementAccount::with([
            'userAccount.user.primaryBank'
        ])->findOrFail($settlementAccountId);


        if ($settlementAccount->status !== 'pending') {
            return $this->errorResponse('Bank details are only available for pending settlements.', 400);
        }

        $account = $settlementAccount->userAccount;
        $userBank = $account?->user?->primaryBank;

        if (!$account) {
            return $this->errorResponse(__('messages.error_messages.not_found'), 404);
        }

        // if bank details not found, return empty response with message
        if (!$userBank || is_null($userBank->ifsc_code)) {
            return $this->successResponse(
                'No bank details found for this account.',
                null
            );
        }

        $bankDetails = [
            'settlement_account_id' => $settlementAccount->id,
            'accnt_account_id'      => $account->id,
            'accnt_account_code'    => $account->accnt_code,

            'account_holder_name'   => $userBank?->account_holder_name,
            'bank_name'             => $userBank?->bank_name,
            'bank_account_number'   => $userBank?->bank_account_number,
            'bank_ifsc_code'        => $userBank?->ifsc_code,
        ];

        return $this->successResponse(
            __('messages.success_messages.success_get'),
            $bankDetails
        );
    }


    public function changeSettlementAccountStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,settled,failed',
        ]);

        $accountingService = app(AccountingService::class);


        $settlementAccount = SettlementAccount::findOrFail($id);
        $settlementAccountLedgers = $settlementAccount->settlementAccountLedgers;

        if (!$settlementAccountLedgers->isEmpty() || $request->input('status') === 'settled') {
            return $this->errorResponse('Cannot mark as settled. There are ledger entries that are not marked as settled.', 400);
        }

        // Check total of ledgers and settlement account amount should be same for settlement otherwise we have to do reversal entry for that ledger which is not marked as settled and then mark settlement account as settled

        if ($settlementAccount->amount != $settlementAccountLedgers->sum(fn($sal) => $sal->credit - $sal->debit)) {
            return $this->errorResponse('Cannot mark as settled. Total of ledger entries does not match settlement account amount.', 400);
        }



        DB::beginTransaction();

        try {


            $settlementAccount->status = $request->input('status');
            $settlementAccount->save();

            $batch = $settlementAccount->settlementBatch;

            if ($batch) {

                // always fetch fresh statuses
                $statuses = $batch->settlementAccounts()->pluck('status');

                if ($statuses->every(fn($s) => $s === 'settled')) {
                    $batch->status = 'settled';


                    ## When settled we have to make entry of credit as debit like reverse of both debit then credi and credit as debit

                    $ledgerExist = $accountingService->ledgerExists(
                        $settlementAccount->userAccount->id,
                        AccountEntryTypeEnum::SETTLEMENT->value,
                        SettlementAccount::class,
                        $settlementAccount->id
                    );

                    if (!$ledgerExist) {

                        // Settlement Account Ledger entry for User Account (Debit)
                        // its reverse base on what we have to do so do not change here
                        $debitOrCredit = $settlementAccount->amount > 0 ? 'debit' : 'credit';

                        // First settlement Account Ledger entry for User Account (Debit)
                        $ledger =   $accountingService->createLedger(
                            $settlementAccount->userAccount,
                            [
                                'entry_type' => AccountEntryTypeEnum::SETTLEMENT->value,
                                'source_type' => SettlementAccount::class,
                                'source_id' => $settlementAccount->id,
                                'debit' => $debitOrCredit === 'debit' ? $settlementAccount->amount : 0,
                                'credit' => $debitOrCredit === 'credit' ? $settlementAccount->amount : 0,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'description' => "Settlement for Account Code: {$settlementAccount->userAccount->accnt_code}, Settlement Batch: {$batch->batch_no}",

                            ]
                        );

                        // Now settlement Account Ledger entry for Platform Account (Credit)
                        $accountingService->markStatusSettled($ledger);


                        // here we have to mark all account ledger realted to that so 

                        foreach ($settlementAccountLedgers as $sal) {
                            // if parent ledger is empty then add 
                            if (!$sal->accountLedger->parent_ledger_id) {
                                $sal->accountLedger->parent_ledger_id = $ledger->id;
                                $sal->accountLedger->save();
                            }
                            $accountingService->markStatusSettled($sal->accountLedger);
                        }


                        ## FOr Platformaccount we have to mark as settled when user account is marked as settled because we are creating ledger entry for platform account when user account is marked as settled so we can not mark platform account ledger entry as settled before that because it will create problem in case if user account ledger entry is not created due to any reason and we have marked platform account ledger entry as settled then it will create problem in future when we will try to settle that ledger entry because it will be already marked as settled and we can not mark it as settled again so we have to mark it as settled when user account is marked as settled
                        $platformLedgerExist = $accountingService->ledgerExists(
                            $settlementAccount->platformAccount->id,
                            AccountEntryTypeEnum::SETTLEMENT->value,
                            SettlementAccount::class,
                            $settlementAccount->id
                        );

                        if (!$platformLedgerExist) {
                            $platformLedger =   $accountingService->createLedger(
                                $settlementAccount->platformAccount,
                                [
                                    'entry_type' => AccountEntryTypeEnum::SETTLEMENT->value,
                                    'source_type' => SettlementAccount::class,
                                    'source_id' => $settlementAccount->id,
                                    'debit' => $debitOrCredit === 'credit' ? $settlementAccount->amount : 0,
                                    'credit' => $debitOrCredit === 'debit' ? $settlementAccount->amount : 0,
                                    'status' => LedgerStatusEnum::AVAILABLE->value, // directly mark as settled because it will be marked as settled when user account is marked as settled
                                    'description' => "Settlement for Account Code: {$settlementAccount->platformAccount->accnt_code}, Settlement Batch: {$batch->batch_no}",
                                    'parent_ledger_id' => $ledger->id, // set parent ledger id to user account ledger entry id because it will be marked as settled when user account is marked as settled so we can easily track that which platform account ledger entry is related to which user account ledger entry
                                ]
                            );

                            $accountingService->markStatusSettled($platformLedger);
                        }


                        // 
                    }



                    //
                } elseif ($statuses->every(fn($s) => $s === 'failed')) {
                    $batch->status = 'failed';
                } else {
                    $batch->status = 'pending';
                }

                $batch->save();
            }

            DB::commit();

            return $this->showSuccessMessage(__('messages.success_messages.success_update'));
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Settlement status update failed', [
                'error' => $e->getMessage()
            ]);

            return $this->showErrorMessage('Something went wrong.');
        }
    }


    //

}
