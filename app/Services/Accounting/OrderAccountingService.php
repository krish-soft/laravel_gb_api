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
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderAccountingService
{

    /// PLATFORM + BUYER + SELLER, ORDER RECEIVED LEDGERS


    public function recordPaidOrder(Order $order, Payment $payment): void
    {
        try {

            DB::transaction(function () use ($order, $payment) {


                if (!$order || !$payment) {
                    throw new RuntimeException("Order or Payment not found for Order ID: {$order->id} and Payment ID: {$payment->id}");
                }

                if ($order->total_amount != $payment->amount) {
                    throw new RuntimeException("Payment amount does not match order total for Order ID: {$order->id} and Payment ID: {$payment->id}");
                }

                $accounting = app(AccountingService::class);

                // get total of taxable orderItems 
                $taxableItemsAmount = $order->orderItems->sum('taxable_amount');


                $buyerId = $order->buyer_id;
                $buyerAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::BUYER->value,
                    $buyerId
                );

                // For clearning we can not use total or sub total because both have inclusive tax and charges total
                // and we are seperating in multiple accounts so we need to calulate base amount

                /*
                |-------------------------------------------------
                | 1. PLATFORM CLEARING (FULL PAID AMOUNT)
                |-------------------------------------------------
                */

                // $clearing = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_CLEARING->value)->firstOrFail();
                $clearingAccount = Account::getOrCreateByOwner(
                    AccountOwnerTypeEnum::PLATFORM->value,
                    null,
                    PlatformAccountCodeEnum::PLATFORM_CLEARING->value,
                );

                if (!$this->ledgerExists(
                    $clearingAccount->id,
                    AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    Order::class,
                    $order->id
                )) {
                    $accounting->createLedger($clearingAccount, [
                        'description' => "Payment received (taxable amount) for Order #{$order->order_number}",
                        'credit' => $order->base_amount ?? $taxableItemsAmount, // we are storing in each accounts  $order->total_amount,
                        'debit'  => 0,
                        'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value,
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'reference' => $payment->payment_code,
                        'payment_reference' => $payment->gateway_order_id,
                        'common_reference' => $order->order_number,
                    ]);
                }

                /*
                |-------------------------------------------------
                | 2. SELLER EARNINGS (ITEM TAXABLE AMOUNT ONLY)
                |-------------------------------------------------
                */
                // Doing settle on invoice 
                // foreach ($order->orderItems as $item) {

                //     // seller 
                //     $seller = $item->seller;
                //     // if not fail transactions 
                //     if (!$seller) {
                //         throw  new RuntimeException("Seller not found for Order Item ID: {$item->id}");
                //         // return;
                //     }

                //     $sellerAccount = Account::getOrCreateByOwner(
                //         AccountOwnerTypeEnum::SELLER->value,
                //         $seller->id
                //     );

                //     if (!$this->ledgerExists(
                //         $sellerAccount->id,
                //         AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                //         OrderItem::class,
                //         $item->id
                //     )) {
                //         // Not Taxable base on ship qty * price because we are recording tax in separate ledger for better reporting and future use
                //         $accounting->createLedger($sellerAccount, [
                //             'description' => "Earnings for Order #{$order->order_number}: {$item->product_name} x {$item->order_qty}",
                //             'credit' => $item->taxable_amount, // we are storing in each accounts  ,
                //             'debit'  => 0,
                //             'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                //             'status' => LedgerStatusEnum::AVAILABLE->value,
                //             'source_type' => OrderItem::class,
                //             'source_id' => $item->id,
                //             'source_code' => $order->order_number,
                //             'reference' => $payment->payment_code,
                //             'payment_reference' => $payment->gateway_order_id,
                //             'common_reference' => $order->order_number,
                //         ]);
                //     }
                // }

                /*
                |-------------------------------------------------
                | 3. PLATFORM REVENUE (CHARGE TAXABLE AMOUNT)
                |-------------------------------------------------
                */
                foreach ($order->orderCharges as $charge) {

                    // $revenue = Account::where('accnt_code', PlatformAccountCodeEnum::PLATFORM_REVENUE->value)->firstOrFail();
                    $revenueAccount = Account::getOrCreateByOwner(
                        AccountOwnerTypeEnum::PLATFORM->value,
                        null,
                        PlatformAccountCodeEnum::PLATFORM_REVENUE->value
                    );

                    if (!$this->ledgerExists(
                        $revenueAccount->id,
                        AccountEntryTypeEnum::PLATFORM_CHARGE_BASE->value,
                        get_class($charge),
                        $charge->id
                    )) {
                        $accounting->createLedger($revenueAccount, [
                            'description' => "Fees for Order #{$order->order_number}: {$charge->charge_name}",
                            'credit' => $charge->taxable_amount,
                            'debit'  => 0,
                            'entry_type' => AccountEntryTypeEnum::PLATFORM_CHARGE_BASE->value,
                            'status' => LedgerStatusEnum::AVAILABLE->value,
                            'source_type' => get_class($charge),
                            'source_id' => $charge->id,
                            'source_code' => $order->order_number,
                            'reference' => $payment->payment_code,
                            'payment_reference' => $payment->gateway_order_id,
                            'common_reference' => $order->order_number,
                        ]);
                    }
                }

                /*
                |-------------------------------------------------
                | 4. GOVERNMENT TAX (ITEM + CHARGE TAX)
                |-------------------------------------------------
                */
                if ($order->tax_amount > 0) {

                    // $tax = Account::where('owner_type', AccountOwnerTypeEnum::GOVERNMENT->value)->firstOrFail();
                    $tax = Account::getOrCreateByOwner(
                        AccountOwnerTypeEnum::GOVERNMENT->value,
                        null,
                        PlatformAccountCodeEnum::PLATFORM_TAX->value
                    );


                    if (!$this->ledgerExists(
                        $tax->id,
                        AccountEntryTypeEnum::ORDER_TAX_AMOUNT->value,
                        Order::class,
                        $order->id
                    )) {
                        $accounting->createLedger($tax, [
                            'description' => "Tax for Order #{$order->order_number}",
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
                            'common_reference' => $order->order_number,
                        ]);
                    }
                }

                // Now at the end what buyer total paid to us as debit settled add it 


                // Credit Debit TO gether becasue custoemr credit and we debited for order so
                // Settled means not to update main on Account 
                if (!$this->ledgerExists(
                    $buyerAccount->id,
                    AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    Order::class,
                    $order->id
                )) {

                    $accounting->createLedger($buyerAccount, [
                        'description' => "Payment received for Order #{$order->order_number}",
                        'credit' => $order->total_amount, // we are storing in each accounts  ,
                        'debit'  => 0,
                        'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                        'status' => LedgerStatusEnum::AVAILABLE->value, // Because Actual How much we sold to them  will finalize letter so
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'reference' => $payment->payment_code,
                        'payment_reference' => $payment->gateway_order_id,
                        'common_reference' => $order->order_number,
                    ]);

                    // Debit we dont becasue we will invoice final that will debit it
                    // $accounting->createLedger($buyerAccount, [
                    //     'description' => "Payment paid for Order #{$order->order_number}",
                    //     'credit' => 0, // we are storing in each accounts  ,
                    //     'debit'  => $order->total_amount,
                    //     'entry_type' => AccountEntryTypeEnum::ORDER_BASE_AMOUNT->value,
                    //     'status' => LedgerStatusEnum::SETTLED->value,
                    //     'source_type' => Order::class,
                    //     'source_id' => $order->id,
                    //     'source_code' => $order->order_number,
                    //     'reference' => $payment->payment_code,
                    //     'payment_reference' => $payment->gateway_order_id,
                    //     'common_reference' => $order->order_number,
                    // ]);
                }



            


                //
            });
        } catch (\Exception $e) {
            throw $e;
            Log::error("Order Accounting for Order ID: {$order->order_number}, Error: {$e->getMessage()}");
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
        int $sourceId
    ): bool {
        return AccountLedger::where('account_id', $accountId)
            ->where('entry_type', $entryType)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }
}
