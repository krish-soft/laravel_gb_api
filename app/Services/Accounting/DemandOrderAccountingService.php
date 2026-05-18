<?php

namespace App\Services\Accounting;

use App\Enum\Accounting\AccountEntryTypeEnum;
use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Accounting\LedgerStatusEnum;
use App\Enum\Accounting\PlatformAccountCodeEnum;
use App\Enum\Common\Order\OrderFlagsEum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Accounting\AccountLedger;
use App\Models\Common\Payment\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DemandOrderAccountingService
{
    // / PLATFORM + BUYER + SELLER, ORDER RECEIVED LEDGERS

    public function recordPaidOrder(DemandOrder $order, Payment $payment): void
    {

        try {

            DB::transaction(function () use ($order, $payment) {

                if (! $order || ! $payment) {
                    throw new RuntimeException("Order or Payment not found for Order ID: {$order->id} and Payment ID: {$payment->id}");
                }

                if ($order->total_amount != ($payment->amount + $payment->credit_amount)) {
                    $order->addFlag(OrderFlagsEum::ACCOUNTING_ERROR, 'Payment amount does not match order total');
                    throw new RuntimeException("Payment amount does not match order total for Order ID: {$order->id} and Payment ID: {$payment->id}");
                }

                $accounting = app(AccountingService::class);

                // Actual amount we received from payment gateway for this order (excluding credit used)
                $paymentAmount = $payment->amount;
                $paymentCreditAmount = $payment->credit_amount; // credit used in this payment if any

                $paymentTotalAmount = $paymentAmount + $paymentCreditAmount;

                // get total of taxable orderItems
                // $taxableItemsAmount = $order->demandOrderItems->sum('taxable_amount');

                $buyerId = $order->buyer_id;
                $buyerAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::BUYER->value,
                    $buyerId
                );

                // $clearing = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_CLEARING->value)->firstOrFail();
                $clearingAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::PLATFORM->value,
                    null,
                    PlatformAccountCodeEnum::PLATFORM_CLEARING->value,
                );

                // For clearning we can not use total or sub total because both have inclusive tax and charges total
                // and we are seperating in multiple accounts so we need to calulate base amount

                /*
                |-------------------------------------------------
                | 1. PLATFORM CLEARING (FULL PAID AMOUNT)
                |-------------------------------------------------
                */

                if ($paymentAmount > 0) {

                    // Chaning only main one entry not from reporting.
                    if (! $this->ledgerExists(
                        $clearingAccount->id,
                        AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                        Payment::class,
                        $payment->id,
                        $paymentAmount, // total without tax
                        0
                    )) {
                        $accounting->createLedger($clearingAccount, [
                            'description' => "Payment received for Order #{$order->order_number}, Payment #{$payment->payment_code}",
                            'credit' => $paymentAmount, // $order->subtotal, // total without tax  $taxableItemsAmount, // we are storing in each accounts  ,
                            'debit' => 0,
                            'entry_type' => AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,
                            'source_type' => Payment::class,
                            'source_id' => $payment->id,
                            'source_code' => $payment->payment_code,

                             'reference' => $order->order_number, // For better traceability we can use order number here because payment code can be same for multiple orders in case of partial payments
                            'payment_reference' => $payment->gateway_order_id,
                            'common_reference' => $payment->payment_code,
                        ]);
                    }

                    // Buyer Account
                    if (! $this->ledgerExists(
                        $buyerAccount->id,
                        AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                        Payment::class,
                        $payment->id,
                        $paymentAmount, // total without tax
                        0
                    )) {

                        $accounting->createLedger($buyerAccount, [
                            'description' => "Payment received for Order #{$order->order_number}, Payment #{$payment->payment_code}",
                            'credit' => $paymentAmount, // $order->subtotal, // total without tax  $taxableItemsAmount, // we are storing in each accounts  ,
                            'debit' => 0,
                            'entry_type' => AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                            'status' => LedgerStatusEnum::PENDING->value,
                            'source_type' => Payment::class,
                            'source_id' => $payment->id,
                            'source_code' => $payment->payment_code,

                            'reference' => $order->order_number, // For better traceability we can use order number here because payment code can be same for multiple orders in case of partial payments
                            'payment_reference' => $payment->gateway_order_id,
                            'common_reference' => $payment->payment_code,
                        ]);
                    }
                }

                // Credit Amount  Needed
                if ($paymentCreditAmount > 0) {

                    // Buyer Account
                    if (! $this->ledgerExists(
                        $buyerAccount->id,
                        AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                        Payment::class,
                        $payment->id,
                        0, // total without tax
                        $paymentCreditAmount
                    )) {
                        $accounting->createLedger($buyerAccount, [
                            'description' => "Credit used for Order #{$order->order_number}, Payment #{$payment->payment_code}",
                            'credit' => 0, // $order->subtotal, // total without tax  $taxableItemsAmount, // we are storing in each accounts  ,
                            'debit' => $paymentCreditAmount,

                            'entry_type' => AccountEntryTypeEnum::ORDER_PAYMENT_AMOUNT_BASE->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,

                            'source_type' => Payment::class,
                            'source_id' => $payment->id,
                            'source_code' => $payment->payment_code,

                            'reference' => $order->order_number, // For better traceability we can use order number here because payment code can be same for multiple orders in case of partial payments
                            'payment_reference' => $payment->gateway_order_id,
                            'common_reference' => $payment->payment_code,

                            'is_credit_balance' => true,
                        ]);
                    }
                }

                $order->order_status = OrderStatusEnum::ACCOUNTED->value;
                // $order->is_locked = true;// on cutoff // lock order after accounting
                $order->removeFlag(OrderFlagsEum::ACCOUNTING_ERROR); // remove accounting error flag if exists
                $order->save();

                //
            });
        } catch (\Exception $e) {
            $order->addFlag(OrderFlagsEum::ACCOUNTING_ERROR, $e->getMessage());
            throw $e;
            // Log::error("Order Accounting for Order ID: {$order->order_number}, Error: {$e->getMessage()}");
            // You can also choose to rethrow the exception or handle it as per your application's needs
        }
    }

    /**
     * Proper idempotency guard
     */
    private function ledgerExists(
        int $accountId,
        string $entryType,
        string $sourceType,
        int $sourceId,
        ?float $credit = null,
        ?float $debit = null
    ): bool {

        // ✅ Normalize to 2 decimal (match DB DECIMAL)
        $credit = is_null($credit) ? null : number_format($credit, 2, '.', '');
        $debit = is_null($debit) ? null : number_format($debit, 2, '.', '');

        return AccountLedger::query()
            ->where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->when(! is_null($credit), fn ($q) => $q->where('credit', $credit))
            ->when(! is_null($debit), fn ($q) => $q->where('debit', $debit))
            ->exists();
    }
}
