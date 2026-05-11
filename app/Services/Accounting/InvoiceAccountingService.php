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

/**
 * Invoice Accounting Service
 * 
 * Handles accounting entries for invoices with proper debit/credit management.
 * 
 * Sign Convention:
 * - Positive amounts: Money received by the account (increases balance)
 * - Negative amounts: Money owed from the account (decreases balance)
 * - These are converted to proper debit/credit based on account type
 */
class InvoiceAccountingService
{
    private AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Record invoice accounting entries
     */
    public function recordInvoice(Invoice $invoice): void
    {
        try {
            DB::transaction(function () use ($invoice) {
                $invoice->load(['user', 'invoiceItems', 'invoiceCharges']);

                $owner = $invoice->user;
                $ownerType = Account::getOwnerTypeByUser($owner);

                if (!$ownerType) {
                    throw new RuntimeException(
                        "Unknown user type for Invoice #{$invoice->invoice_number}, User ID: {$owner->id}"
                    );
                }

                // Step 1: Record base invoice amount
                $this->recordBaseAmount($invoice, $ownerType, $owner->id);

                // Step 2: Record charges (only for non-sales invoices)
                if (!$this->isSalesInvoice($invoice)) {
                    $this->recordCharges($invoice);
                }

                // Step 3: Mark invoice as accounted
                $invoice->status = InvoiceStatusEnum::ACCOUNTED->value;
                $invoice->is_locked = true;
                $invoice->save();
            });
        } catch (\Exception $e) {
            $invoice->addFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR, $e->getMessage());
            Log::error("Invoice Accounting Error: {$invoice->invoice_number} | {$e->getMessage()}", [
                'invoice_id' => $invoice->id,
                'invoice_type' => $invoice->invoice_type,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Record base invoice amount with proper debit/credit handling
     */
    private function recordBaseAmount(Invoice $invoice, string $ownerType, int $ownerId): void
    {
        $ownerAccount = Account::getOrCreateByOwner($ownerType, $ownerId);

        // Skip if ledger already exists
        if ($this->accountingService->ledgerExists(
            $ownerAccount->id,
            AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
            Invoice::class,
            $invoice->id
        )) {
            return;
        }

        // Get signed amount (positive = money in, negative = money out)
        $signedAmount = $this->getSignedBaseAmount($invoice);
        
        // Convert signed amount to debit/credit
        [$debit, $credit] = $this->convertSignedToDebitCredit($signedAmount);

        $description = $this->buildInvoiceDescription($invoice);

        $this->accountingService->createLedger($ownerAccount, [
            'description' => $description,
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

    /**
     * Record invoice charges and taxes
     */
    private function recordCharges(Invoice $invoice): void
    {
        $clearingAccount = Account::getOrCreateByOwner(
            AccountOwnerTypeEnum::PLATFORM->value,
            null,
            PlatformAccountCodeEnum::PLATFORM_CLEARING->value
        );

        foreach ($invoice->invoiceCharges as $charge) {
            // Record charge amount
            if ($charge->taxable_amount != 0) {
                $this->recordChargeAmount($charge, $invoice, $clearingAccount);
            }

            // Record charge tax
            if ($charge->tax_amount != 0) {
                $this->recordChargeTax($charge, $invoice, $clearingAccount);
            }
        }
    }

    /**
     * Record individual charge amount
     */
    private function recordChargeAmount(
        InvoiceCharge $charge,
        Invoice $invoice,
        Account $clearingAccount
    ): void {
        if ($this->accountingService->ledgerExists(
            $clearingAccount->id,
            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
            InvoiceCharge::class,
            $charge->id
        )) {
            return;
        }

        // Get signed amount (positive = platform earns, negative = platform refunds)
        $signedAmount = $this->getSignedChargeAmount($charge, $invoice);
        [$debit, $credit] = $this->convertSignedToDebitCredit($signedAmount);

        $description = "Invoice #{$invoice->invoice_number} - Charge ({$charge->charge_name})";

        $this->accountingService->createLedger($clearingAccount, [
            'description' => $description,
            'credit' => $credit,
            'debit' => $debit,
            'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
            'status' => LedgerStatusEnum::AVAILABLE->value,
            'source_type' => InvoiceCharge::class,
            'source_id' => $charge->id,
            'source_code' => $invoice->invoice_number,
            'common_reference' => $invoice->invoice_number,
        ]);
    }

    /**
     * Record individual charge tax
     */
    private function recordChargeTax(
        InvoiceCharge $charge,
        Invoice $invoice,
        Account $clearingAccount
    ): void {
        if ($this->accountingService->ledgerExists(
            $clearingAccount->id,
            AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,
            InvoiceCharge::class,
            $charge->id
        )) {
            return;
        }

        // Get signed amount (positive = platform earns, negative = platform refunds)
        $signedAmount = $this->getSignedChargeTax($charge, $invoice);
        [$debit, $credit] = $this->convertSignedToDebitCredit($signedAmount);

        $description = "Invoice #{$invoice->invoice_number} - Charge Tax ({$charge->charge_name})";

        $this->accountingService->createLedger($clearingAccount, [
            'description' => $description,
            'credit' => $credit,
            'debit' => $debit,
            'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,
            'status' => LedgerStatusEnum::AVAILABLE->value,
            'source_type' => InvoiceCharge::class,
            'source_id' => $charge->id,
            'source_code' => $invoice->invoice_number,
            'common_reference' => $invoice->invoice_number,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS: Signed Amount Calculations
    |--------------------------------------------------------------------------
    */

    /**
     * Get signed base amount
     * Positive = money received by owner, Negative = money owed by owner
     */
    private function getSignedBaseAmount(Invoice $invoice): float
    {
        $amount = $invoice->total_amount;

        return match ($invoice->invoice_type) {
            InvoiceTypeEnum::SALES->value => $amount,              // Buyer owes us (money in)
            InvoiceTypeEnum::SALES_RETURN->value => -$amount,      // We owe buyer (money out)
            InvoiceTypeEnum::PURCHASE->value => -$amount,          // We owe supplier (money out)
            InvoiceTypeEnum::PURCHASE_RETURN->value => $amount,    // Supplier owes us (money in)
            default => throw new RuntimeException("Unsupported invoice type: {$invoice->invoice_type}"),
        };
    }

    /**
     * Get signed charge amount
     * For platform: Positive = platform earns (money in), Negative = platform refunds (money out)
     */
    private function getSignedChargeAmount(InvoiceCharge $charge, Invoice $invoice): float
    {
        $amount = $charge->taxable_amount;

        return match ($invoice->invoice_type) {
            InvoiceTypeEnum::SALES->value => $amount,              // Platform earns
            InvoiceTypeEnum::SALES_RETURN->value => -$amount,      // Platform refunds
            InvoiceTypeEnum::PURCHASE->value => $amount,           // Platform earns
            InvoiceTypeEnum::PURCHASE_RETURN->value => -$amount,   // Platform refunds
            default => 0,
        };
    }

    /**
     * Get signed tax amount
     * For platform: Positive = platform earns (money in), Negative = platform refunds (money out)
     */
    private function getSignedChargeTax(InvoiceCharge $charge, Invoice $invoice): float
    {
        $amount = $charge->tax_amount;

        return match ($invoice->invoice_type) {
            InvoiceTypeEnum::SALES->value => $amount,              // Platform earns
            InvoiceTypeEnum::SALES_RETURN->value => -$amount,      // Platform refunds
            InvoiceTypeEnum::PURCHASE->value => $amount,           // Platform earns
            InvoiceTypeEnum::PURCHASE_RETURN->value => -$amount,   // Platform refunds
            default => 0,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS: Debit/Credit Conversion
    |--------------------------------------------------------------------------
    */

    /**
     * Convert signed amount to debit/credit
     * 
     * Standard accounting: 
     * - Assets/Expenses: Debit = increase, Credit = decrease
     * - Liabilities/Income: Debit = decrease, Credit = increase
     * 
     * Simplified rule used here:
     * - Positive signed amount: Debit (for most accounts, represents increase)
     * - Negative signed amount: Credit (represents decrease/liability)
     * 
     * @return array [debit, credit]
     */
    private function convertSignedToDebitCredit(float $signedAmount): array
    {
        if ($signedAmount > 0) {
            return [abs($signedAmount), 0];  // Debit for positive
        } elseif ($signedAmount < 0) {
            return [0, abs($signedAmount)];  // Credit for negative
        } else {
            return [0, 0];  // Zero amounts
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS: Utility Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if invoice is a sales type (early exit for charge recording)
     */
    private function isSalesInvoice(Invoice $invoice): bool
    {
        return $invoice->invoice_type === InvoiceTypeEnum::SALES->value;
    }

    /**
     * Build descriptive invoice label
     */
    private function buildInvoiceDescription(Invoice $invoice): string
    {
        $actionLabel = match ($invoice->invoice_type) {
            InvoiceTypeEnum::SALES->value => 'Sales',
            InvoiceTypeEnum::SALES_RETURN->value => 'Sales Return',
            InvoiceTypeEnum::PURCHASE->value => 'Purchase',
            InvoiceTypeEnum::PURCHASE_RETURN->value => 'Purchase Return',
            default => 'Invoice',
        };

        return "Invoice #{$invoice->invoice_number} - {$actionLabel}";
    }
}
