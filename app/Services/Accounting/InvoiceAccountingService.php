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

                /*
                |--------------------------------------------------------------------------
                | Buyer Ledger - Items Taxable (TOTAL)
                |--------------------------------------------------------------------------
                */

                $itemsTaxable = (float)$invoice->invoiceItems->sum('taxable_amount');

                if ($itemsTaxable != 0) {

                    if (!$accountingService->ledgerExists(
                        $ownerAccount->id,
                        AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                        Invoice::class,
                        $invoice->id
                    )) {

                        $entry = $this->resolveDirection($type, $itemsTaxable);

                        $accountingService->createLedger($ownerAccount, [
                            'description' => "Invoice #{$invoice->invoice_number} - Items",
                            'credit' => $entry['credit'],
                            'debit' => $entry['debit'],
                            'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,
                            'source_type' => Invoice::class,
                            'source_id' => $invoice->id,
                            'source_code' => $invoice->invoice_number,
                            'common_reference' => $invoice->invoice_number
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Buyer Ledger - Charges PER LINE
                |--------------------------------------------------------------------------
                */

                foreach ($invoice->invoiceCharges as $charge) {

                    $chargeTaxable = (float)$charge->taxable_amount;
                    $chargeTax = (float)$charge->tax_amount;

                    /*
                    |--------------------------------------------------------------------------
                    | Charge Taxable Amount
                    |--------------------------------------------------------------------------
                    */

                    if ($chargeTaxable != 0) {

                        if (!$accountingService->ledgerExists(
                            $ownerAccount->id,
                            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {

                            $entry = $this->resolveDirection($type, $chargeTaxable);

                            $accountingService->createLedger($ownerAccount, [
                                'description' => "Invoice #{$invoice->invoice_number} - Charge ({$charge->charge_name})",
                                'credit' => $entry['credit'],
                                'debit' => $entry['debit'],
                                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => InvoiceCharge::class,
                                'source_id' => $charge->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Charge Tax Amount
                    |--------------------------------------------------------------------------
                    */

                    if ($chargeTax != 0) {

                        if (!$accountingService->ledgerExists(
                            $ownerAccount->id,
                            AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {

                            $entry = $this->resolveDirection($type, $chargeTax);

                            $accountingService->createLedger($ownerAccount, [
                                'description' => "Invoice #{$invoice->invoice_number} - Charge Tax ({$charge->charge_name})",
                                'credit' => $entry['credit'],
                                'debit' => $entry['debit'],
                                'entry_type' => AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => InvoiceCharge::class,
                                'source_id' => $charge->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number
                            ]);
                        }
                    }
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

                if ($isPurchase || $isPurchaseReturn || $isSalesReturn) {

                    foreach ($invoice->invoiceCharges as $charge) {

                        $chargeAmount = (float)$charge->taxable_amount;

                        if ($chargeAmount == 0) {
                            continue;
                        }

                        $entry = $this->resolveDirection(
                            $isPurchase ? InvoiceTypeEnum::PURCHASE->value : ($isPurchaseReturn ? InvoiceTypeEnum::PURCHASE_RETURN->value :
                                    InvoiceTypeEnum::SALES_RETURN->value),
                            $chargeAmount
                        );

                        if (!$accountingService->ledgerExists(
                            $clearingAccount->id,
                            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {

                            $accountingService->createLedger($clearingAccount, [
                                'description' => "Invoice #{$invoice->invoice_number} - Charge ({$charge->charge_name})",
                                'credit' => $entry['credit'],
                                'debit' => $entry['debit'],
                                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => InvoiceCharge::class,
                                'source_id' => $charge->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number
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
                $invoice->removeFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR);
                $invoice->save();
            });
        } catch (\Throwable $e) {

            $invoice->addFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR, $e->getMessage());

            Log::error("Invoice Accounting Error: {$invoice->invoice_number} | {$e->getMessage()}");

            throw $e;
        }
    }

    private function resolveDirection(string $type, float $amount): array
    {
        $abs = abs($amount);

        $debit = 0;
        $credit = 0;

        switch ($type) {

            case InvoiceTypeEnum::SALES->value:
                $debit = $abs;
                break;

            case InvoiceTypeEnum::SALES_RETURN->value:
                $credit = $abs;
                break;

            case InvoiceTypeEnum::PURCHASE->value:
                $credit = $abs;
                break;

            case InvoiceTypeEnum::PURCHASE_RETURN->value:
                $debit = $abs;
                break;

            default:
                throw new RuntimeException("Invalid invoice type");
        }

        if ($amount < 0) {
            [$debit, $credit] = [$credit, $debit];
        }

        return [
            'debit' => $debit,
            'credit' => $credit
        ];
    }
}
