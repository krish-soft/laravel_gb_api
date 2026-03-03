<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\LedgerStatusEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Master\Setting\MstFinanceSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountingService
{
    public function createLedger(Account $account, array $data): AccountLedger
    {
        $credit = $data['credit'] ?? 0;
        $debit  = $data['debit'] ?? 0;

        if (($credit > 0 && $debit > 0) || ($credit <= 0 && $debit <= 0)) {
            throw new RuntimeException("Invalid credit / debit amount for Account Code: {$account->accnt_code}, Credit: {$credit}, Debit: {$debit}");
        }

        return DB::transaction(function () use ($account, $data, $credit, $debit) {

            $account = Account::lockForUpdate()->findOrFail($account->id);

            $ledger = AccountLedger::create([
                'account_id'        => $account->id,
                'finance_year_id'   => $data['finance_year_id'] ??  MstFinanceSetting::appFinancialYearId(),
                'ledger_date'       => $data['ledger_date'] ?? now()->toDateString(),

                'description'       => $data['description'] ?? null,

                'credit'            => abs($credit), // alwasy positive in ledger, we will use entry_type to determine how to apply
                'debit'             => abs($debit), // alwasy positive in ledger, we will use entry_type to determine how to apply

                'entry_type'        => $data['entry_type'],
                'status'            => $data['status'] ?? LedgerStatusEnum::AVAILABLE->value,
                'is_tax'            => $data['is_tax'] ?? false,

                'source_type'       => $data['source_type'] ?? null,
                'source_id'         => $data['source_id'] ?? null,
                'source_code'       => $data['source_code'] ?? null,

                'reference'         => $data['reference'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'common_reference'   => $data['common_reference'] ?? null,

                'parent_ledger_id'  => $data['parent_ledger_id'] ?? null,
                'remarks'            => $data['remarks'] ?? null,

                'is_open_balance'   => $data['is_open_balance'] ?? false,
                'is_tax'            => $data['is_tax'] ?? false,

                'settled_at'       => $data['settled_at'] ?? null,

            ]);

            $this->updateAccountSnapshot($account, $ledger);

            logActivity(
                'accounting_ledger_created',
                request()->user(),
                AccountLedger::class,
                $ledger->id,
                $ledger->ledger_txn_code,
                $data
            );

            return $ledger;
        });
    }

    public function markAvailable(AccountLedger $ledger): void
    {
        if ($ledger->status !== LedgerStatusEnum::PENDING->value) {
            return;
        }

        DB::transaction(function () use ($ledger) {

            $ledger  = AccountLedger::lockForUpdate()->findOrFail($ledger->id);
            $account = Account::lockForUpdate()->findOrFail($ledger->account_id);

            $amount = $ledger->credit - $ledger->debit;

            $ledger->update([
                'status' => LedgerStatusEnum::AVAILABLE->value,
            ]);

            if (!$ledger->is_tax) {

                if ($ledger->credit > 0) {
                    $account->decrement('hold_balance', $ledger->credit);
                    $account->increment('available_balance', $ledger->credit);
                }

                if ($ledger->debit > 0) {
                    $account->increment('hold_balance', $ledger->debit);
                    $account->decrement('available_balance', $ledger->debit);
                }
            }

            logActivity(
                'accounting_ledger_marked_available',
                request()->user(),
                AccountLedger::class,
                $ledger->id,
                $ledger->ledger_txn_code,
                ['amount' => $amount]
            );
        });
    }

    public function markStatusSettled(AccountLedger $ledger): void
    {

        // Only Status chagne not any value update
        if ($ledger->status === LedgerStatusEnum::SETTLED->value) {
            return;
        }

        DB::transaction(function () use ($ledger) {

            $ledger  = AccountLedger::lockForUpdate()->findOrFail($ledger->id);



            $ledger->update([
                'status'     => LedgerStatusEnum::SETTLED->value,
                'settled_at' => now(),
            ]);

            logActivity(
                'accounting_ledger_marked_settled',
                request()->user(),
                AccountLedger::class,
                $ledger->id,
                $ledger->ledger_txn_code,
                []
            );
        });
    }

    public function payout(Account $account, array $data): AccountLedger
    {
        return $this->createLedger($account, array_merge($data, [
            'entry_type' => 'payout',
            'status'     => LedgerStatusEnum::SETTLED->value,
        ]));
    }

    public function manualEntry(Account $account, array $data): AccountLedger
    {
        return $this->createLedger($account, array_merge($data, [
            'entry_type' => 'manual',
            'status'     => $data['status'] ?? LedgerStatusEnum::AVAILABLE->value,
            'source_type' => 'manual_entry',
        ]));
    }

    public function updateLedger(AccountLedger $ledger, array $data): AccountLedger
    {
        if ($ledger->status === LedgerStatusEnum::SETTLED->value) {
            throw new RuntimeException('Cannot update settled ledger');
        }

        return DB::transaction(function () use ($ledger, $data) {

            $ledger = AccountLedger::lockForUpdate()->findOrFail($ledger->id);

            $ledger->update([
                'ledger_date'       => $data['ledger_date'] ?? $ledger->ledger_date,
                'reference'         => $data['reference'] ?? $ledger->reference,
                'payment_reference' => $data['payment_reference'] ?? $ledger->payment_reference,
                'remarks'            => $data['remarks'] ?? $ledger->remark,
            ]);

            logActivity(
                'accounting_ledger_updated',
                request()->user(),
                AccountLedger::class,
                $ledger->id,
                $ledger->ledger_txn_code,
                $data
            );

            return $ledger;
        });
    }

    public function reverseLedger(AccountLedger $ledger, string $remark = 'Reversal'): AccountLedger
    {

        $ledger->load('account');



        $ledger = AccountLedger::lockForUpdate()->findOrFail($ledger->id);

        if ($ledger->status === LedgerStatusEnum::SETTLED->value) {
            throw new RuntimeException('Cannot reverse a settled ledger entry.');
        }

        // Check alrady reveresed or not by checking if any reversal entry exists with parent_ledger_id
        if (AccountLedger::where('parent_ledger_id', $ledger->id)->where('entry_type', 'reversal')->exists()) {
            throw new RuntimeException('This ledger entry has already been reversed.');
        }

        return $this->createLedger(
            $ledger->account,
            [
                'description'      => "Reversal of Ledger #{$ledger->ledger_txn_code}",
                'credit'           => $ledger->debit,
                'debit'            => $ledger->credit,

                'entry_type'       => 'reversal',
                'status'           => LedgerStatusEnum::AVAILABLE->value,
                'parent_ledger_id' => $ledger->id,
                'ledger_date'      => now()->toDateString(),
                'remarks'           => $remark,

                'source_type'      => $ledger->source_type,
                'source_id'        => $ledger->source_id,
                'source_code'      => $ledger->source_code,

            ]
        );
    }

    protected function updateAccountSnapshot(Account $account, AccountLedger $ledger): void
    {
        // totals (always)
        if ($ledger->credit > 0) {
            $account->increment('total_credit', $ledger->credit);
        }

        if ($ledger->debit > 0) {
            $account->increment('total_debit', $ledger->debit);
        }

        // if ($ledger->is_tax) {
        //     return;
        // }

        $net = $ledger->credit - $ledger->debit;

        if ($ledger->status === LedgerStatusEnum::PENDING->value && $net != 0) {
            $account->increment('hold_balance', $net);
        }

        if ($ledger->status === LedgerStatusEnum::AVAILABLE->value && $net != 0) {
            $account->increment('available_balance', $net);
        }
    }





    public function ledgerExists(
        int $accountId,
        string $entryType,
        string $sourceType,
        int $sourceId,
        string $otherReference = null
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('other_reference', $otherReference) // for driver charges reversal or any other use
            ->exists();
    }








    //
}
