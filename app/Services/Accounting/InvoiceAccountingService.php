<?php


namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Invoice\InvoiceStatusEnum;
use App\Enum\Common\Invoice\InvoiceTypeEnum;
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

                    // ✅ Correct Accounting Logic
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

                // Platform Accounts
                // $taxAccount = Account::getOrCreateByOwner(
                //     AccountOwnerTypeEnum::GOVERNMENT->value,
                //     null,
                //     PlatformAccountCodeEnum::PLATFORM_TAX->value
                // );

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

                    // Tax Ledger (only for non-buyer)
                    // if ($taxAmount != 0 && $ownerType != AccountOwnerTypeEnum::BUYER->value) {

                    //     if (!$accountingService->ledgerExists(
                    //         $taxAccount->id,
                    //         AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                    //         InvoiceCharge::class,
                    //         $charge->id
                    //     )) {
                    //         $accountingService->createLedger($taxAccount, [
                    //             // 'description' => "Tax: {$charge->charge_name} (Invoice #{$invoice->invoice_number})",
                    //             'description' => "Invoice #{$invoice->invoice_number} - Tax ({$charge->charge_name})",
                    //             'credit' => $creditTax,
                    //             'debit'  => $debitTax,
                    //             'entry_type' => AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                    //             'status' => LedgerStatusEnum::AVAILABLE->value,
                    //             'is_tax' => true,
                    //             'source_type' => InvoiceCharge::class,
                    //             'source_id' => $charge->id,
                    //             'source_code' => $invoice->invoice_number,
                    //             'common_reference' => $invoice->invoice_number,
                    //         ]);
                    //     }
                    // }
                }

                // Finalize
                $invoice->status = InvoiceStatusEnum::ACCOUNTED->value;
                $invoice->is_locked = true;
                $invoice->save();
            });
        } catch (\Exception $e) {
            Log::error("Invoice Accounting Error: {$invoice->invoice_number} | {$e->getMessage()}");
            throw $e;
        }
    }


    /// OLD CODE - we need to move this logic to job for better performance and avoid any delay in order processing
    // public function recordInvoice(Invoice $invoice)
    // {

    //     try {

    //         $accountingService = app(AccountingService::class);

    //         DB::transaction(function () use ($invoice, $accountingService) {
    //             $invoice->load([
    //                 'user',
    //                 'invoiceItems',
    //                 'invoiceCharges',
    //             ]);

    //             // We need to split possilbe all amount to seperate accounts
    //             $ownerType = null;
    //             $owner = $invoice->user;


    //             $isSalesInvoice = $invoice->invoice_type == 'sales'; // we can have purchase invoice in future, so we need to identify the type

    //             $ownerType = Account::getOwnerTypeByUser($owner);

    //             if (!$ownerType) {
    //                 // Log::warning("Unknown user type for Invoice number: {$invoice->invoice_number}, User ID: {$owner->id}");
    //                 throw new RuntimeException("Unknown user type for Invoice number: {$invoice->invoice_number} and User ID: {$owner->id}");
    //             }

    //             // Invoice amount breakup
    //             $baseAmount = $invoice->base_amount;
    //             $subTotal = $invoice->subtotal;
    //             $taxAmount = $invoice->tax_amount;
    //             $totalAmount = $invoice->total_amount;

    //             $ownerAccount = Account::getOrCreateByOwner(
    //                 $ownerType,
    //                 $owner->id
    //             );


    //             if (!$accountingService->ledgerExists(
    //                 $ownerAccount->id,
    //                 AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
    //                 Invoice::class,
    //                 $invoice->id,
    //             )) {


    //                 $credit = 0;
    //                 $debit = 0;

    //                 if ($totalAmount > 0) {
    //                     // $credit = $totalAmount;  // Seller earning or Delivery earning or Purchase amount
    //                     $credit = $baseAmount;
    //                 } else {
    //                     // $debit = abs($totalAmount); // Deduction / reversal for seller or delivery or refund for buyer
    //                     $debit = abs($baseAmount);
    //                 }


    //                 $accountingService->createLedger($ownerAccount, [
    //                     'description' => "Accounting Ledger Entry for Invoice #{$invoice->invoice_number}",
    //                     'credit' => abs($credit), // we are storing in each accounts  ,
    //                     'debit'  => abs($debit),  // we are storing in each accounts  ,
    //                     'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
    //                     'status' => LedgerStatusEnum::AVAILABLE->value,
    //                     'source_type' => Invoice::class,
    //                     'source_id' => $invoice->id,
    //                     'source_code' => $invoice->invoice_number,
    //                     'common_reference' => $invoice->invoice_number,
    //                 ]);

    //                 //
    //             }

    //             ## We need to collect tax on platform for reporting
    //             // if (($owner->isSeller() || $owner->isDelivery()) && !$isSalesInvoice) {

    //             // We only need entry what we are getting from seller or delivery
    //             // For buyer we already have total



    //             $taxAccount = Account::getOrCreateByOwner(
    //                 AccountOwnerTypeEnum::GOVERNMENT->value,
    //                 null,
    //                 PlatformAccountCodeEnum::PLATFORM_TAX->value
    //             );

    //             $clearingAccount = Account::getOrCreateByOwner(
    //                 AccountOwnerTypeEnum::PLATFORM->value,
    //                 null,
    //                 PlatformAccountCodeEnum::PLATFORM_CLEARING->value,
    //             );

    //             // $invocieChargesTotal = $subTotal - $baseAmount; // Total charges amount without tax

    //             if ($invoice->invoiceCharges->isNotEmpty()) {

    //                 foreach ($invoice->invoiceCharges as $charge) {

    //                     $chargeTaxable = $charge->taxable_amount;
    //                     $chargeTax = $charge->tax_amount;

    //                     $debitCharge = 0;
    //                     $creditCharge = 0;

    //                     $debitChargeTax = 0;
    //                     $creditChargeTax = 0;

    //                     if ($chargeTaxable > 0) {
    //                         $creditCharge = $chargeTaxable;
    //                     } else {
    //                         $debitCharge = abs($chargeTaxable);
    //                     }

    //                     if ($chargeTax > 0) {
    //                         $creditChargeTax = $chargeTax;
    //                     } else {
    //                         $debitChargeTax = abs($chargeTax);
    //                     }




    //                     if (!$accountingService->ledgerExists(
    //                         $clearingAccount->id,
    //                         AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
    //                         InvoiceCharge::class,
    //                         $charge->id
    //                     )) {
    //                         $accountingService->createLedger($clearingAccount, [
    //                             'description' => "Charge for Invoice #{$invoice->invoice_number}: {$charge->charge_name}",
    //                             'credit' => $creditCharge,
    //                             'debit'  => $debitCharge,
    //                             'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
    //                             'status' => LedgerStatusEnum::AVAILABLE->value,
    //                             'source_type' => InvoiceCharge::class,
    //                             'source_id' => $charge->id,
    //                             'source_code' => $invoice->invoice_number,
    //                             'common_reference' => $invoice->invoice_number,
    //                         ]);
    //                     }

    //                     // Tax 

    //                     if ($charge->tax_amount != 0) {

    //                         if (!$accountingService->ledgerExists(
    //                             $taxAccount->id,
    //                             AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
    //                             InvoiceCharge::class,
    //                             $charge->id
    //                         )) {
    //                             $accountingService->createLedger($taxAccount, [
    //                                 'description' => "Tax for Invoice #{$invoice->invoice_number}: {$charge->charge_name}",
    //                                 'credit' => $creditChargeTax,
    //                                 'debit'  => $debitChargeTax,
    //                                 'entry_type' => AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
    //                                 'status' => LedgerStatusEnum::AVAILABLE->value,
    //                                 'is_tax' => true,
    //                                 'source_type' => InvoiceCharge::class,
    //                                 'source_id' => $charge->id,
    //                                 'source_code' => $invoice->invoice_number,
    //                                 'common_reference' => $invoice->invoice_number,
    //                             ]);
    //                         }
    //                     }
    //                 }
    //             }
    //             // }


    //             // After successful ledger entries, we can mark the invoice as accounted
    //             $invoice->status = InvoiceStatusEnum::ACCOUNTED->value; // or any status to indicate it's processed
    //             $invoice->is_locked = true; // lock the invoice to prevent further changes
    //             $invoice->save();
    //         });
    //     } catch (\Exception $e) {
    //         // Handle exceptions, log errors, etc.
    //         Log::error("Invoice Accounting Error for Invoice Number: {$invoice->invoice_number}, Error: {$e->getMessage()}");

    //         throw $e; // Rethrow or handle as needed
    //     }
    // }
}
