<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Accounting;

use App\Enum\Accounting\LedgerStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;

class AccountLedgerAdminApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //

        $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
        ]);

        $accountLedgers = AccountLedger::where('account_id', $request->account_id)
            ->orderBy('ledger_date', 'desc')
            ->orderBy('id', 'desc')
            ->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $accountLedgers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            //
            'account_id' => 'required|integer|exists:accounts,id',
            'finance_year_id' => 'nullable|integer|exists:mst_financial_years,id',
            'description' => 'required|string|max:255',
            'credit' => 'required|numeric|min:0',
            'debit' => 'required|numeric|min:0',
            'ledger_date' => 'required|date',
            'entry_type' => 'nullable|string|max:100',
            'source_type' => 'nullable|string|max:100',
            'source_id' => 'nullable|integer',
            'source_code' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'parent_ledger_id' => 'nullable|integer|exists:account_ledgers,id',
            'status' => 'nullable|string|max:50',
            'settled_at' => 'nullable|date',
            'is_tax' => 'required|boolean',
            'is_open_balance' => 'required|boolean',
            'remarks' => 'nullable|string|max:500',
        ]);

        $account = Account::findOrFail($request->account_id);

        // Check already have open balance for this same year
        if ($request->is_open_balance) {
            $existingOpenBalance = AccountLedger::where('account_id', $account->id)
                ->where('is_open_balance', true)
                ->where('finance_year_id', $request->finance_year_id ?? MstFinanceSetting::appFinancialYearId())
                ->first();

            if ($existingOpenBalance) {
                return $this->errorResponse('An open balance ledger already exists for this account and financial year.', 422);
            }
        }

        $accountLedger = app(AccountingService::class)->manualEntry(
            $account,
            $request->only([
                'finance_year_id',
                'description',
                'credit',
                'debit',
                'ledger_date',
                'entry_type',
                'source_type',
                'source_id',
                'source_code',
                'reference',
                'payment_reference',
                'parent_ledger_id',
                'status',
                'settled_at',
                'is_tax',
                'is_open_balance',
                'remarks',
            ])
        );

        // Log activity
        logActivity(
            'account_ledger_created',   // ACTIVITY TYPE (what happened)
            request()->user(),       // ACTOR (who did it)
            get_class($accountLedger),       // SUBJECT TYPE (what was affected)
            $accountLedger->id,              // SUBJECT ID
            $accountLedger->ledger_code,       // SUBJECT CODE (human readable)
            [
                'credit' => $accountLedger->credit,
                'debit' => $accountLedger->debit,
                'status' => $accountLedger->status,
            ]
        );



        return $this->showSuccessMessage(__('messages.success_messages.success_create'));


        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AccountLedger $accountLedger)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $accountLedger);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AccountLedger $accountLedger)
    {
        //

        $request->validate([
            //
            'description' => 'nullable|string|max:255',
            'credit' => 'nullable|numeric|min:0',
            'debit' => 'nullable|numeric|min:0',
            'ledger_date' => 'nullable|date',
            'entry_type' => 'nullable|string|max:100',
            'source_type' => 'nullable|string|max:100',
            'source_id' => 'nullable|integer',
            'source_code' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'parent_ledger_id' => 'nullable|integer|exists:account_ledgers,id',
            'status' => 'nullable|string|max:50',
            'settled_at' => 'nullable|date',
            'is_tax' => 'nullable|boolean',
            'is_open_balance' => 'nullable|boolean',
            'remarks' => 'nullable|string|max:500',
        ]);


        $accountLedger->update($request->only([
            'description',
            'credit',
            'debit',
            'ledger_date',
            'entry_type',
            'source_type',
            'source_id',
            'source_code',
            'reference',
            'payment_reference',
            'parent_ledger_id',
            'status',
            'settled_at',
            'is_tax',
            'is_open_balance',
            'remarks',
        ]));

        // Log activity
        logActivity(
            'account_ledger_updated',   // ACTIVITY TYPE (what happened)
            request()->user(),       // ACTOR (who did it)
            get_class($accountLedger),       // SUBJECT TYPE (what was affected)
            $accountLedger->id,              // SUBJECT ID
            $accountLedger->ledger_code,       // SUBJECT CODE (human readable)
            [
                'credit' => $accountLedger->credit,
                'debit' => $accountLedger->debit,
                'status' => $accountLedger->status,
            ]
        );


        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountLedger $accountLedger)
    {
        //

        // reveres reverseLedger

        // if ($accountLedger->status === LedgerStatusEnum::SETTLED->value) {

        //     $accountLedger = app(AccountingService::class)->reverseLedger($accountLedger, 'manual_reversal_for_deletion');

        //     // Log activity for reversal
        //     logActivity(
        //         'account_ledger_reversed',   // ACTIVITY TYPE (what happened)
        //         request()->user(),       // ACTOR (who did it)
        //         get_class($accountLedger),       // SUBJECT TYPE (what was affected)
        //         $accountLedger->id,              // SUBJECT ID
        //         $accountLedger->ledger_code,       // SUBJECT CODE (human readable)
        //         [
        //             'credit' => $accountLedger->credit,
        //             'debit' => $accountLedger->debit,
        //             'status' => $accountLedger->status,
        //         ]
        //     );

        //     return $this->showSuccessMessage(__('messages.success_messages.success_reverse'));
        // }

        return $this->errorResponse(__('messages.error_messages.user_detlete_prohibited'), 403);

        // Log activity
        logActivity(
            'account_ledger_deleted',   // ACTIVITY TYPE (what happened)
            request()->user(),       // ACTOR (who did it)
            get_class($accountLedger),       // SUBJECT TYPE (what was affected)
            $accountLedger->id,              // SUBJECT ID
            $accountLedger->ledger_code,       // SUBJECT CODE (human readable)
            [
                'credit' => $accountLedger->credit,
                'debit' => $accountLedger->debit,
                'status' => $accountLedger->status,
            ]
        );


        $accountLedger->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }




    public function reverseLedger($ledgerId)
    {
        $accountLedger = AccountLedger::findOrFail($ledgerId);


        if ($accountLedger->status === LedgerStatusEnum::SETTLED->value) {
            return $this->errorResponse('Cannot reverse a settled ledger entry.', 400);
        }

        $reversedLedger = app(AccountingService::class)->reverseLedger($accountLedger);

        // Log activity for reversal
        logActivity(
            'account_ledger_reversed',   // ACTIVITY TYPE (what happened)
            request()->user(),       // ACTOR (who did it)
            get_class($reversedLedger),       // SUBJECT TYPE (what was affected)
            $reversedLedger->id,              // SUBJECT ID
            $reversedLedger->ledger_code,       // SUBJECT CODE (human readable)
            [
                'credit' => $reversedLedger->credit,
                'debit' => $reversedLedger->debit,
                'status' => $reversedLedger->status,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    public function markSettled($ledgerId)
    {
        $accountLedger = AccountLedger::findOrFail($ledgerId);

        if ($accountLedger->status === LedgerStatusEnum::SETTLED->value) {
            return $this->errorResponse('Ledger entry is already settled.', 400);
        }

        app(AccountingService::class)->markStatusSettled($accountLedger);

        // Log activity for status change
        logActivity(
            'account_ledger_settled',   // ACTIVITY TYPE (what happened)
            request()->user(),       // ACTOR (who did it)
            get_class($accountLedger),       // SUBJECT TYPE (what was affected)
            $accountLedger->id,              // SUBJECT ID
            $accountLedger->ledger_code,       // SUBJECT CODE (human readable)
            [
                'credit' => $accountLedger->credit,
                'debit' => $accountLedger->debit,
                'status' => LedgerStatusEnum::SETTLED->value,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }










    //
}
