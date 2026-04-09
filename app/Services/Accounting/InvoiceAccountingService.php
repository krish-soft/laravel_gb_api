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
    //

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
                        "Unknown user type for Invoice #{$invoice->invoice_number}, User ID: {$owner->id}"
                    );
                }
                // Invoice type flags
                $type = $invoice->invoice_type;

                $isSales = $type == InvoiceTypeEnum::SALES->value;
                $isSalesReturn = $type == InvoiceTypeEnum::SALES_RETURN->value;
                $isPurchase = $type == InvoiceTypeEnum::PURCHASE->value;
                $isPurchaseReturn = $type == InvoiceTypeEnum::PURCHASE_RETURN->value;

                $totalAmount = $invoice->total_amount;

                // Owner Account
                $ownerAccount = Account::getOrCreateByOwner($ownerType, $owner->id);

                if (!$accountingService->ledgerExists(
                    $ownerAccount->id,
                    AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                    Invoice::class,
                    $invoice->id
                )) {

                    $debit = 0;
                    $credit = 0;

                    // Original
                    if ($isSales) {
                        $debit = $totalAmount;
                    } elseif ($isSalesReturn) {
                        $credit = $totalAmount;
                    } elseif ($isPurchase) {
                        $credit = $totalAmount;
                    } elseif ($isPurchaseReturn) {
                        $debit = $totalAmount;
                    }

                    $action = match (true) {
                        $isSales => 'Sales',
                        $isSalesReturn => 'Sales Return',
                        $isPurchase => 'Purchase',
                        $isPurchaseReturn => 'Purchase Return',
                        default => 'Invoice',
                    };

                    $accountingService->createLedger($ownerAccount, [
                        // 'description' => "Invoice #{$invoice->invoice_number}",
                        'description' => "Invoice #{$invoice->invoice_number} - {$action}",
                        'credit' => $credit,
                        'debit'  => $debit,
                        'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'source_code' => $invoice->invoice_number,
                        'common_reference' => $invoice->invoice_number,
                    ]);
                }

                if ($isSales) {
                    return; // we don not need charge of its because we already received total amount from buyer, we will handle charge in settlement
                }

                $clearingAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::PLATFORM->value,
                    null,
                    PlatformAccountCodeEnum::PLATFORM_CLEARING->value
                );

                // Charges Handling
                foreach ($invoice->invoiceCharges as $charge) {

                    $chargeAmount = $charge->taxable_amount;
                    $taxAmount = $charge->tax_amount;

                    $debitCharge = 0;
                    $creditCharge = 0;

                    $debitTax = 0;
                    $creditTax = 0;

                    if ($isSales || $isPurchase) {
                        // Platform earns
                        $creditCharge = $chargeAmount;
                        $creditTax = $taxAmount;
                    } elseif ($isSalesReturn || $isPurchaseReturn) {
                        // Platform refunds
                        $debitCharge = abs($chargeAmount);
                        $debitTax = abs($taxAmount);
                    }

                    // Charge Ledger
                    // 
                    if ($chargeAmount  != 0) {

                        if (!$accountingService->ledgerExists(
                            $clearingAccount->id,
                            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {
                            $accountingService->createLedger($clearingAccount, [
                                // 'description' => "Charge: {$charge->charge_name} (Invoice #{$invoice->invoice_number})",
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

                    if ($taxAmount  != 0) {

                        if (!$accountingService->ledgerExists(
                            $clearingAccount->id,
                            AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,
                            InvoiceCharge::class,
                            $charge->id
                        )) {
                            $accountingService->createLedger($clearingAccount, [
                                // 'description' => "Charge: {$charge->charge_name} (Invoice #{$invoice->invoice_number})",
                                'description' => "Invoice #{$invoice->invoice_number} - Charge Tax ({$charge->charge_name})",
                                'credit' => $creditTax,
                                'debit'  => $debitTax,
                                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'source_type' => InvoiceCharge::class,
                                'source_id' => $charge->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number,
                            ]);
                        }
                    }
                }

                // Finalize
                $invoice->status = InvoiceStatusEnum::ACCOUNTED->value;
                $invoice->is_locked = true;
                $invoice->save();
            });
        } catch (\Exception $e) {

            $invoice->addFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR, $e->getMessage());
            Log::error("Invoice Accounting Error: {$invoice->invoice_number} | {$e->getMessage()}");
            throw $e;
        }
    }
}
