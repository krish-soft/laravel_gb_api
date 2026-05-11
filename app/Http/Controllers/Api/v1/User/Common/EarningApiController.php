<?php

namespace App\Http\Controllers\Api\v1\User\Common;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use Illuminate\Http\Request;

class EarningApiController extends ApiResponseWithAuthController
{
    //



    // Common for users

    public function getEarningsData(Request $request)
    {
        $user = $request->user();

        $ownerType = null; // Assuming user_type is the owner type

        $ownerType = Account::getOwnerTypeByUser($user);

        if (!$ownerType) {
            return $this->errorResponse(__('messages.error_messages.not_found'), 404);
        }

        $userAccount = Account::getOrCreateByOwner(
            $ownerType,
            $user->id
        );

        if (!$userAccount) {
            return $this->errorResponse(__('messages.error_messages.not_found'), 404);
        }

        // check if account inactive or blocked
        if (!$userAccount->is_active) {
            return $this->errorResponse(__('messages.error_messages.account_inactive'), 403);
        }



        $ledgers = AccountLedger::latest()
            ->select(
                'id',
                'account_id',
                'finance_year_id',
                'description',
                'credit',
                'debit',
                'ledger_date',
                'entry_type',
                'status',
                'remarks',

                'is_tax',
                'is_open_balance',

            )
            ->where('account_id', $userAccount->id)
            ->limit(10) // only latest 10 transactions
            ->get();



        $data = [
            'available_balance' => $userAccount->available_balance,
            'hold_balance' => $userAccount->hold_balance,
            'credit_limit' => $userAccount->credit_limit,
            // 'total_credit' => $userAccount->total_credit,
            // 'total_debit' => $userAccount->total_debit,
            'ledgers' => $ledgers,
        ];



        return $this->successResponse(__('messages.success_messages.success_get'), $data);
    }
}
