<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Invoice\InvoiceTypeEnum;
use App\Enum\Common\Order\OrderFlagsEum;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Invoice\Invoice;
use App\Models\Common\Invoice\InvoiceCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InvoiceAccountingService
{
    public function recordInvoice(Invoice $invoice)
    {
        try {

            $accountingService = app(AccountingService::class);

            DB::transaction(function () use ($invoice, $accountingService) {

                $invoice->load(['user', 'invoiceItems', 'invoiceCharges']);

                $owner = $invoice->user;
                $ownerType = Account::getOwnerTypeByUser($owner);

                if (!$ownerType) {
                    throw new RuntimeException(
                        "Unknown user type for Invoice #{$invoice->invoice_number}"
                    );
                }

                $type = $invoice->invoice_type;

                $isSales = $type == InvoiceTypeEnum::SALES->value;
                $isSalesReturn = $type == InvoiceTypeEnum::SALES_RETURN->value;
                $isPurchase = $type == InvoiceTypeEnum::PURCHASE->value;
                $isPurchaseReturn = $type == InvoiceTypeEnum::PURCHASE_RETURN->value;

                $ownerAccount = Account::getOrCreateByOwner($ownerType, $owner->id);

                $amount = (float) $invoice->total_amount;
                $absAmount = abs($amount);

                $debit = 0;
                $credit = 0;

                /*
                |--------------------------------------------------------------------------
                | Owner Ledger
                |--------------------------------------------------------------------------
                */

                switch ($type) {

                    case InvoiceTypeEnum::SALES->value:
                        $debit = $absAmount;
                        break;

                    case InvoiceTypeEnum::SALES_RETURN->value:
                        $credit = $absAmount;
                        break;

                    case InvoiceTypeEnum::PURCHASE->value:
                        $credit = $absAmount;
                        break;

                    case InvoiceTypeEnum::PURCHASE_RETURN->value:
                        $debit = $absAmount;
                        break;

                    default:
                        throw new RuntimeException("Unsupported invoice type: {$type}");
                }

                // If total becomes negative -> flip entry
                if ($amount < 0) {
                    [$debit, $credit] = [$credit, $debit];
                }

                if (!$accountingService->ledgerExists(
                    $ownerAccount->id,
                    AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                    Invoice::class,
                    $invoice->id
                )) {

                    $accountingService->createLedger($ownerAccount, [
                        'description' => "Invoice #{$invoice->invoice_number}",
                        'credit' => $credit,
                        'debit' => $debit,
                        'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'source_code' => $invoice->invoice_number,
                        'common_reference' => $invoice->invoice_number,
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Platform Clearing Account
                |--------------------------------------------------------------------------
                */

                $clearingAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::PLATFORM->value,
                    null,
                    PlatformAccountCodeEnum::PLATFORM_CLEARING->value
                );

                // Is not sales invoice because when we received order we already added total amount under clearning
                if ($isPurchase || $isPurchaseReturn || $isSalesReturn) {

                    foreach ($invoice->invoiceCharges as $charge) {

                        $chargeAmount = (float) $charge->taxable_amount;
                        $absCharge = abs($chargeAmount);

                        if ($absCharge == 0) {
                            continue;
                        }

                        $debitCharge = 0;
                        $creditCharge = 0;

                        if ($isPurchase) {
                            $creditCharge = $absCharge;
                        }

                        if ($isPurchaseReturn || $isSalesReturn) {
                            $debitCharge = $absCharge;
                        }

                        // negative adjustment safety
                        if ($chargeAmount < 0) {
                            [$debitCharge, $creditCharge] = [$creditCharge, $debitCharge];
                        }

                        if (!$accountingService->ledgerExists(
                            $clearingAccount->id,
                            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {

                            $accountingService->createLedger($clearingAccount, [
                                'description' => "Invoice #{$invoice->invoice_number} - Charge ({$charge->charge_name})",
                                'credit' => $creditCharge,
                                'debit'  => $debitCharge,
                                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => InvoiceCharge::class,
                                'source_id' => $charge->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number,
                            ]);
                        }
                    }
                }
                /*
                |--------------------------------------------------------------------------
                | Finalize
                |--------------------------------------------------------------------------
                */

                $invoice->status = InvoiceStatusEnum::ACCOUNTED->value;
                $invoice->is_locked = true;
                $invoice->removeFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR); // remove flag if exists
                $invoice->save();
            });

            //
        } catch (\Throwable $e) {

            $invoice->addFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR, $e->getMessage());

            Log::error("Invoice Accounting Error: {$invoice->invoice_number} | {$e->getMessage()}");

            throw $e;
        }
    }
}
