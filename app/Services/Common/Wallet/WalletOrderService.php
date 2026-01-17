<?php

namespace App\Services\Common\Wallet;

use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment;
use App\Models\Common\Wallet\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletOrderService
{
    // Code for WalletOrderService goes here

    // Add Order Related Wallet Transaction Methods

    public function createNewOrderWalletTransaction(Order $order, Payment $payment): void
    {

        DB::transaction(function () use ($order, $payment) {

            /**
             * Buyer Side
             */
            $buyerWalletTxn = WalletTransaction::where('reference', $payment->payment_code)
                ->lockForUpdate()
                ->first();

            if (!$buyerWalletTxn) {
                // 3️⃣ Create wallet transaction (ONCE)
                $buyerWalletTxn = app(WalletService::class)->createTransaction(
                    $order->buyer->wallet,
                    $payment->amount,
                    WalletTypeEnum::DEBIT, // money going out 
                    WalletStatusEnum::COMPLETED, // money received create transaction as hold then → finalize directly
                    [
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'reference' => $payment->payment_code,
                        'description' => 'Order # ' . $order->order_number . ' payment received.',
                    ]
                );
            }

            // 4️⃣ Finalize → ledger + wallet balance
            app(WalletService::class)->finalizeTransaction($buyerWalletTxn);


            /**
             * Seller Side
             */
            // Which qyty and package items are sold we have to keep those transactions on hold until order is picked up or delivered.
            // To Get the qty we have to get orderItems loop 
            $orderItems = $order->orderItems;
            foreach ($orderItems as $orderItem) {

                // Check if same package and qty transaction already exists but could be same posible if 10 our of 5 or 5 remina then ??
                $sellerWalletTxn = WalletTransaction::where('payment_reference', $payment->payment_code)
                    ->where('source_id', $order->id)
                    ->where('source_type', Order::class)
                    ->lockForUpdate()
                    ->first();
            }
        });


        //
    }
}
