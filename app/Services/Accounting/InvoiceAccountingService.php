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
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Invoice\Invoice;
use App\Models\Common\Invoice\InvoiceCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class InvoiceAccountingService
{
    public function __construct(
        private AccountingService $accountingService
    ) {}

    /**
     * Record Invoice Accounting
     */
    public function recordInvoice(Invoice $invoice): void
    {
        try {

            DB::transaction(function () use ($invoice) {

                $invoice->load([
                    'user',
                    'invoiceCharges',
                    'order',
                    'marketOrder',
                    'demandOrder',
                ]);

                $owner = $invoice->user;

                $ownerType = Account::getOwnerTypeByUser($owner);

                if (! $ownerType) {
                    throw new RuntimeException(
                        "Invalid owner type for Invoice #{$invoice->invoice_number}"
                    );
                }

                $ownerAccount = Account::getOrCreateByOwner(
                    $ownerType,
                    $owner->id
                );

                // Release pending ledgers for sales invoice
                if ($invoice->invoice_type === InvoiceTypeEnum::SALES->value) {
                    $this->releasePendingLedger($invoice, $ownerAccount);
                }

                // Base Amount
                $this->recordBaseAmount(
                    $invoice,
                    $ownerAccount
                );

                // Charges
                $this->recordCharges($invoice);

                // Mark Accounted
                $invoice->status = InvoiceStatusEnum::ACCOUNTED->value;
                $invoice->is_locked = true;
                $invoice->removeFlag(OrderFlagsEum::INVOICE_ACCOUNTING_ERROR);
                $invoice->save();
            });

        } catch (Throwable $e) {

            $invoice->addFlag(
                OrderFlagsEum::INVOICE_ACCOUNTING_ERROR,
                $e->getMessage()
            );

            Log::error(
                'Invoice Accounting Error',
                [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'message' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Record Main Invoice Amount
     */
    private function recordBaseAmount(
        Invoice $invoice,
        Account $account
    ): void {

        if ($this->accountingService->ledgerExists(
            $account->id,
            AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,
            Invoice::class,
            $invoice->id
        )) {
            return;
        }

        $debit = 0;
        $credit = 0;

        /*
        |--------------------------------------------------------------------------
        | HARDCODED ACCOUNTING RULES
        |--------------------------------------------------------------------------
        */

        switch ($invoice->invoice_type) {

            // Customer owes platform
            case InvoiceTypeEnum::SALES->value:

                $debit = $invoice->total_amount;
                // Credit amount exist on payment
                // We need to remove credit amount because already added entry in begin so if for that order exist...
                //
                $creditAmount = 0;
                if ($invoice->order) {
                    $creditAmount = $invoice?->order?->payment?->credit_amount ?? 0;
                } elseif ($invoice->demandOrder) {
                    $creditAmount = $invoice?->demandOrder?->payment?->credit_amount ?? 0;
                }
                //
                $debit = $debit - $creditAmount;

                break;

                // Reverse customer amount
            case InvoiceTypeEnum::SALES_RETURN->value:

                $credit = $invoice->total_amount;

                break;

                // Platform owes supplier
            case InvoiceTypeEnum::PURCHASE->value:

                $credit = $invoice->total_amount;

                break;

                // Reverse supplier payable
            case InvoiceTypeEnum::PURCHASE_RETURN->value:

                $debit = $invoice->total_amount;

                break;

            default:

                throw new RuntimeException(
                    "Unsupported invoice type: {$invoice->invoice_type}"
                );
        }

        $this->accountingService->createLedger(
            $account,
            [
                'description' => $this->buildInvoiceDescription($invoice),

                'debit' => $debit,
                'credit' => $credit,

                'entry_type' => AccountEntryTypeEnum::INVOICE_BASE_AMOUNT->value,

                'status' => LedgerStatusEnum::AVAILABLE->value,

                'source_type' => Invoice::class,
                'source_id' => $invoice->id,

                'source_code' => $invoice->invoice_number,
                'common_reference' => $invoice->invoice_number,
            ]
        );
    }

    /**
     * Record Charges
     */
    private function recordCharges(Invoice $invoice): void
    {
        if (
            in_array(
                $invoice->invoice_type,
                [
                    InvoiceTypeEnum::SALES->value,
                    InvoiceTypeEnum::SALES_RETURN->value,
                ]
            )
        ) {
            return;
        }

        $platformAccount = Account::getOrCreateByOwner(
            AccountOwnerTypeEnum::PLATFORM->value,
            null,
            PlatformAccountCodeEnum::PLATFORM_CLEARING->value
        );

        foreach ($invoice->invoiceCharges as $charge) {

            $this->recordChargeAmount(
                $invoice,
                $charge,
                $platformAccount
            );

            $this->recordChargeTax(
                $invoice,
                $charge,
                $platformAccount
            );
        }
    }

    /**
     * Record Charge Amount
     */
    private function recordChargeAmount(
        Invoice $invoice,
        InvoiceCharge $charge,
        Account $account
    ): void {

        if ($charge->taxable_amount <= 0) {
            return;
        }

        if ($this->accountingService->ledgerExists(
            $account->id,
            AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,
            InvoiceCharge::class,
            $charge->id
        )) {
            return;
        }

        $debit = 0;
        $credit = 0;

        switch ($invoice->invoice_type) {

            case InvoiceTypeEnum::PURCHASE->value:

                $credit = $charge->taxable_amount;

                break;

            case InvoiceTypeEnum::PURCHASE_RETURN->value:

                $debit = $charge->taxable_amount;

                break;
        }

        $invoiceLabel = self::buildInvoiceDescription($invoice);
        $this->accountingService->createLedger(
            $account,
            [
                'description' => "{$invoiceLabel} Charge {$charge->charge_name}",

                'debit' => $debit,
                'credit' => $credit,

                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_AMOUNT->value,

                'status' => LedgerStatusEnum::AVAILABLE->value,

                'source_type' => InvoiceCharge::class,
                'source_id' => $charge->id,

                'source_code' => $invoice->invoice_number,
                'common_reference' => $invoice->invoice_number,
            ]
        );
    }

    /**
     * Record Charge Tax
     */
    private function recordChargeTax(
        Invoice $invoice,
        InvoiceCharge $charge,
        Account $account
    ): void {

        if ($charge->tax_amount <= 0) {
            return;
        }

        if ($this->accountingService->ledgerExists(
            $account->id,
            AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,
            InvoiceCharge::class,
            $charge->id
        )) {
            return;
        }

        $debit = 0;
        $credit = 0;

        switch ($invoice->invoice_type) {

            case InvoiceTypeEnum::PURCHASE->value:

                $credit = $charge->tax_amount;

                break;

            case InvoiceTypeEnum::PURCHASE_RETURN->value:

                $debit = $charge->tax_amount;

                break;
        }

        $this->accountingService->createLedger(
            $account,
            [
                'description' => "Invoice #{$invoice->invoice_number} Tax {$charge->charge_name}",

                'debit' => $debit,
                'credit' => $credit,

                'entry_type' => AccountEntryTypeEnum::INVOICE_CHARGE_TAX->value,

                'status' => LedgerStatusEnum::AVAILABLE->value,

                'source_type' => InvoiceCharge::class,
                'source_id' => $charge->id,

                'source_code' => $invoice->invoice_number,
                'common_reference' => $invoice->invoice_number,
            ]
        );
    }

    /**
     * Release Pending Ledger
     */
    private function releasePendingLedger(
        Invoice $invoice,
        Account $account
    ): void {

        $reference = null;

        if ($invoice->order) {
            $reference = $invoice->order->order_number;
        } elseif ($invoice->marketOrder) {
            $reference = $invoice->marketOrder->market_order_number;
        } elseif ($invoice->demandOrder) {
            $reference = $invoice->demandOrder->order_number;
        }

        if (! $reference) {
            return;
        }

        $ledgers = AccountLedger::query()
            ->where('account_id', $account->id)
            ->where('reference', $reference)
            ->where('status', LedgerStatusEnum::PENDING->value)
            ->get();

        foreach ($ledgers as $ledger) {
            $this->accountingService->markAvailable($ledger);
        }
    }

    /**
     * Description
     */
    private function buildInvoiceDescription(
        Invoice $invoice
    ): string {

        $label = match ($invoice->invoice_type) {

            InvoiceTypeEnum::SALES->value => 'Sales Invoice',

            InvoiceTypeEnum::SALES_RETURN->value => 'Sales Return Invoice',

            InvoiceTypeEnum::PURCHASE->value => 'Purchase Invoice',

            InvoiceTypeEnum::PURCHASE_RETURN->value => 'Purchase Return Invoice',

            default => 'Invoice',
        };

        return "{$label} #{$invoice->invoice_number}";
    }
}
