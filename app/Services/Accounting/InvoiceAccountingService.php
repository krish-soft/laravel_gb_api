<?php


namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Invoice\Invoice;
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
                $invoice->load([
                    'user',
                    'invoiceItems',
                    'invoiceCharges',
                ]);

                // We need to split possilbe all amount to seperate accounts
                $ownerType = null;
                $owner = $invoice->user;

                $ownerType = Account::getOwnerTypeByUser($owner);

                if (!$ownerType) {
                    // Log::warning("Unknown user type for Invoice number: {$invoice->invoice_number}, User ID: {$owner->id}");
                    throw new RuntimeException("Unknown user type for Invoice number: {$invoice->invoice_number} and User ID: {$owner->id}");
                }
                // if ($owner->isSeller()) {
                //     $ownerType = AccountOwnerTypeEnum::SELLER->value;
                // } else if ($owner->isBuyer()) {
                //     $ownerType = AccountOwnerTypeEnum::BUYER->value;
                // } else if ($owner->isDelivery()) {
                //     $ownerType = AccountOwnerTypeEnum::DELIVERY->value;
                // } else {
                //     // Log::warning("Unknown user type for Invoice number: {$invoice->invoice_number}, User ID: {$owner->id}");
                //     throw new RuntimeException("Unknown user type for Invoice number: {$invoice->invoice_number} and User ID: {$owner->id}");
                // }

                // Invoice amount breakup
                $baseAmount = $invoice->base_amount;
                $subTotal = $invoice->subtotal;
                $taxAmount = $invoice->tax_amount;
                $totalAmount = $invoice->total_amount;

                // Negative amount total invoice never gone a settled and we need to handle it manualy so we can skip it in accounting for now but log it for review
                // now check amount  negative or zero
                // if ($baseAmount <= 0 || $totalAmount <= 0) {
                //     throw new RuntimeException("Invalid invoice amount for Invoice number: {$invoice->invoice_number}, Base Amount: {$baseAmount}, Total Amount: {$totalAmount}");
                // }

                $ownerAccount = Account::getOrCreateByOwner(
                    $ownerType,
                    $owner->id
                );


                if (!$accountingService->ledgerExists(
                    $ownerAccount->id,
                    AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                    Invoice::class,
                    $invoice->id,
                    $invoice->invoice_number // for reference only, not unique
                )) {

                    $credit = 0;
                    $debit = 0;

                    if ($owner->isBuyer()) {

                        if ($totalAmount >= 0) {
                            $debit = $totalAmount;   // Purchase
                        } else {
                            $credit = abs($totalAmount); // Refund
                        }
                    } else if ($owner->isSeller()) {

                        if ($totalAmount >= 0) {
                            $credit = $totalAmount;  // Seller earning
                        } else {
                            $debit = abs($totalAmount); // Deduction / reversal
                        }
                    } else if ($owner->isDelivery()) {

                        if ($totalAmount >= 0) {
                            $credit = $totalAmount;
                        } else {
                            $debit = abs($totalAmount);
                        }
                    }

                    $accountingService->createLedger($ownerAccount, [
                        'description' => "Accounting Ledger Entry for Invoice #{$invoice->invoice_number}",
                        'credit' => $credit, // we are storing in each accounts  ,
                        'debit'  => $debit,  // we are storing in each accounts  ,
                        'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => Invoice::class,
                        'source_id' => $invoice->id,
                        'source_code' => $invoice->invoice_number,
                        'common_reference' => $invoice->invoice_number,
                    ]);


                    ## We need to collect tax on platform for reporting
                    if (($owner->isSeller() || $owner->isDelivery()) && $taxAmount > 0) {
                        $taxAccount = Account::getOrCreateByOwner(
                            AccountOwnerTypeEnum::GOVERNMENT->value,
                            null,
                            PlatformAccountCodeEnum::PLATFORM_TAX->value
                        );
                        if (!$accountingService->ledgerExists(
                            $taxAccount->id,
                            AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                            Invoice::class,
                            $invoice->id
                        )) {
                            $accountingService->createLedger($taxAccount, [
                                'description' => "Tax for Invoice #{$invoice->invoice_number}",
                                'credit' => $invoice->tax_amount,
                                'debit'  => 0,
                                'entry_type' => AccountEntryTypeEnum::INVOICE_TAX_AMOUNT->value,
                                'status' => LedgerStatusEnum::AVAILABLE->value,
                                'is_tax' => true,
                                'source_type' => Invoice::class,
                                'source_id' => $invoice->id,
                                'source_code' => $invoice->invoice_number,
                                'common_reference' => $invoice->invoice_number,
                            ]);
                        }
                    }

                    //
                }


                //

            });
        } catch (\Exception $e) {
            // Handle exceptions, log errors, etc.
            Log::error("Invoice Accounting Error for Invoice Number: {$invoice->invoice_number}, Error: {$e->getMessage()}");

            throw $e; // Rethrow or handle as needed
        }
    }
}
