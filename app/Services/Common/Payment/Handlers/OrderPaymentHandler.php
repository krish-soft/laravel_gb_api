<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment;
use App\Models\Common\Wallet\WalletTransaction;
use App\Services\Buyer\Checkout\CheckoutRevertService;
use App\Services\Common\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class OrderPaymentHandler
{

    public function onSuccess(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            $source = $payment->source();

            // 🔒 Only handle order payments
            if (!$source instanceof Order) {
                return;
            }

            $order = $source;

            // 🔒 Idempotency: order already confirmed
            if ($order->order_status === OrderStatusEnum::CONFIRMED->value) {
                return;
            }

            // 1️⃣ Confirm order
            $order->update([
                'order_status' => OrderStatusEnum::CONFIRMED->value,
                'payment_status' => PaymentStatusEnum::PAID->value,
            ]);

            // 2️⃣ Finalize wallet HOLD (ONLY ONCE)
            $walletTxn = WalletTransaction::where(
                'payment_reference',
                $payment->payment_code
            )
                ->lockForUpdate()
                ->first();

            if (!$walletTxn) {
                // 3️⃣ Create wallet transaction (ONCE)
                $walletTxn = app(WalletService::class)->createTransaction(
                    $order->buyer->wallet,
                    $payment->amount,
                    WalletTypeEnum::DEBIT,
                    WalletStatusEnum::COMPLETED, // money received → finalize directly
                    [
                        'source_type' => Order::class,
                        'source_id' => $order->id,
                        'source_code' => $order->order_number,
                        'payment_reference' => $payment->payment_code,
                        'gateway' => $payment->gateway,
                        'description' => 'Order # ' . $order->order_number . ' payment received',
                    ]
                );

                // 4️⃣ Finalize → ledger + wallet balance
                app(WalletService::class)->finalizeTransaction($walletTxn);
            }

            //TODO:: Shipment process can be triggered here or via another service/event
        });
    }
//    public function onSuccess(Payment $payment): void
//    {
//        DB::transaction(function () use ($payment) {
//
//            /** @var Order $order */
//            $source = $payment->source();
//
//            if (!$source instanceof Order) {
//                return; // Not an order payment
//            }
//
//            $order = $source;
//
//            if (!$order || $order->order_status === OrderStatusEnum::CONFIRMED->value) {
//                return;
//            }
//
//            $order->update([
//                'order_status' => OrderStatusEnum::CONFIRMED->value,
//                'payment_status' => PaymentStatusEnum::PAID->value,
//            ]);
//
//            // TODO::  Wallet To Add Funds
//
//
//            //
//        });
//    }

    public function onFailure(
        Payment $payment,
        string  $reason
    ): void
    {
        DB::transaction(function () use ($payment, $reason) {

            /** @var Order $order */
            $source = $payment->source();

            if (!$source instanceof Order) {
                return; // Not an order payment
            }

            $order = $source;

            if (
                !$order ||
                in_array($order->order_status, [
                    OrderStatusEnum::CANCELLED->value,
                    OrderStatusEnum::SUSPENDED->value,
                ])
            ) {
                return;
            }

            // ❌ Cancel order
            $order->update([
                'order_status' => OrderStatusEnum::CANCELLED->value,
                'payment_status' => PaymentStatusEnum::FAILED->value,
            ]);

            // Then Revert due to revert will not apply on processing so no issues

            // Revert Checkout
            $checkoutRevertService = app(CheckoutRevertService::class);
            $checkoutRevertService->revert($order);
        });
    }
}
