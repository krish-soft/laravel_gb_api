<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Payment\Payment;
use Illuminate\Support\Facades\DB;

class OrderAccountingService
{
    public function recordPaidOrder(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {

            $accounting = app(AccountingService::class);

            /*
            |-------------------------------------------------
            | 1. PLATFORM CLEARING (FULL PAID AMOUNT)
            |-------------------------------------------------
            */
            $clearing = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_CLEARING->value)->firstOrFail();

            if (!$this->ledgerExists(
                $clearing->id,
                AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                Order::class,
                $order->id
            )) {
                $accounting->createLedger($clearing, [
                    'credit' => $order->total_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    'status' => LedgerStatusEnum::AVAILABLE->value,
                    'source_type' => Order::class,
                    'source_id' => $order->id,
                    'source_code' => $order->order_number,
                    'reference' => $payment->payment_code,
                    'payment_reference' => $payment->gateway_order_id,
                ]);
            }

            /*
            |-------------------------------------------------
            | 2. SELLER EARNINGS (ITEM TAXABLE AMOUNT ONLY)
            |-------------------------------------------------
            */
            foreach ($order->orderItems as $item) {

                $seller = Account::where('owner_type', AccountOwnerTypeEnum::SELLER->value)
                    ->where('owner_id', $item->seller_id)
                    ->firstOrFail();

                if ($this->ledgerExists(
                    $seller->id,
                    AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    OrderItem::class,
                    $item->id
                )) {
                    continue;
                }

                $accounting->createLedger($seller, [
                    'credit' => $item->taxable_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    'status' => LedgerStatusEnum::PENDING->value,
                    'source_type' => OrderItem::class,
                    'source_id' => $item->id,
                    'source_code' => $order->order_number,
                    'reference' => $payment->payment_code,
                    'payment_reference' => $payment->gateway_order_id,
                ]);
            }

            /*
            |-------------------------------------------------
            | 3. PLATFORM REVENUE (CHARGE TAXABLE AMOUNT)
            |-------------------------------------------------
            */
            foreach ($order->orderCharges as $charge) {

                $revenue = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_REVENUE->value)->firstOrFail();

                if ($this->ledgerExists(
                    $revenue->id,
                    AccountEntryTypeEnum::ORDER_PLATFORM_FEE_BASE->value,
                    get_class($charge),
                    $charge->id
                )) {
                    continue;
                }

                $accounting->createLedger($revenue, [
                    'credit' => $charge->taxable_amount,
                    'debit'  => 0,
                    'entry_type' => AccountEntryTypeEnum::ORDER_PLATFORM_FEE_BASE->value,
                    'status' => LedgerStatusEnum::PENDING->value,
                    'source_type' => get_class($charge),
                    'source_id' => $charge->id,
                    'source_code' => $order->order_number,
                    'reference' => $payment->payment_code,
                    'payment_reference' => $payment->gateway_order_id,
                ]);
            }

            /*
            |-------------------------------------------------
            | 4. GOVERNMENT TAX (ITEM + CHARGE TAX)
            |-------------------------------------------------
            */
            if ($order->tax_amount > 0) {

                $tax = Account::where('owner_type', AccountOwnerTypeEnum::GOVERNMENT->value)->firstOrFail();

                if (!$this->ledgerExists(
                    $tax->id,
                    AccountEntryTypeEnum::ORDER_TAX_AMOUNT->value,
                    Order::class,
                    $order->id
                )) {
                    $accounting->createLedger($tax, [
                        'credit' => $order->tax_amount,
                        'debit'  => 0,
                        'entry_type' => AccountEntryTypeEnum::ORDER_TAX_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'is_tax' => true,
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'reference' => $payment->payment_code,
                        'payment_reference' => $payment->gateway_order_id,
                    ]);
                }
            }
        });
    }

    /**
     * Proper idempotency guard
     */
    private function ledgerExists(
        int $accountId,
        string $entryType,
        string $sourceType,
        int $sourceId
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
