<?php

namespace App\Http\Controllers\Api\v1\Admin\Settlement;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Settlement\SettlementAccount;
use App\Models\Common\Accounting\Settlement\SettlementBatch;
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

        $account = $settlementAccount->userAccount;
        $userBank = $account?->user?->primaryBank;

        if (!$account) {
            return $this->errorResponse(__('messages.error_messages.not_found'), 404);
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

        DB::beginTransaction();

        try {

            $account = SettlementAccount::findOrFail($id);
            $account->status = $request->input('status');
            $account->save();

            $batch = $account->settlementBatch;

            if ($batch) {

                // always fetch fresh statuses
                $statuses = $batch->settlementAccounts()->pluck('status');

                if ($statuses->every(fn($s) => $s === 'settled')) {
                    $batch->status = 'settled';
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
