<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobDemandOrderAccounting;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Common\Payment\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemandOrderPaymentHandler
{


    // Handle successful payment
    public function onSuccess(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {

            $source = $payment->source();

            // 🔒 Only handle order payments
            if (!$source instanceof DemandOrder) {
                return;
            }

            $order = $source;

            // 🔒 Idempotency: order already confirmed
            if ($order->order_status === OrderStatusEnum::CONFIRMED->value) {
                return;
            }

            // 1️⃣ Confirm order
            $order->update([
                'is_paid' => true,
                'order_status' => OrderStatusEnum::CONFIRMED->value,
                'payment_status' => PaymentStatusEnum::PAID->value,

                //
                'reference' => $payment->payment_code ?? null,
                'payment_reference' => $payment->gateway_order_id ?? null,

            ]);


            JobDemandOrderAccounting::dispatch($order->id)
                ->afterCommit() // Only dispatch if transaction successful
                ->delay(now()->addSeconds(3))
                ->onQueue(QueueEnum::ACCOUNTING->value);


            //
        });
    }


    // Handle failed payment
    public function onFailure(
        Payment $payment,
        string  $reason
    ): void {
        DB::transaction(function () use ($payment, $reason) {

            /** @var DemandOrder $order */
            $source = $payment->source();

            if (!$source instanceof DemandOrder) {
                return; // Not an order payment
            }

            $order = $source;

            if (
                !$order ||
                in_array($order->order_status, [
                    OrderStatusEnum::CANCELLED->value,
                    OrderStatusEnum::FAILED_PAYMENT->value,
                    OrderStatusEnum::CONFIRMED->value, // already confirmed 
                ])
            ) {
                return;
            }

            // ❌ Cancel order
            $order->update([
                'order_status' => OrderStatusEnum::FAILED_PAYMENT->value,
                'payment_status' => PaymentStatusEnum::FAILED->value,
                'reference' => $payment->payment_code ?? null,
            ]);

            // Then Revert due to revert will not apply on processing so no issues

            // Revert Checkout
            // No revert         

            $oldCart = $order->demandCart;

            if ($oldCart) {

                // 1️⃣ Clone cart
                $newCart = $oldCart->replicate();
                $newCart->cart_uuid = null;   // let booted() regenerate
                $newCart->status = CartStatusEnum::ACTIVE->value;
                $newCart->save();

                // 2️⃣ Clone cart items
                foreach ($oldCart->demandCartItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->demand_cart_id = $newCart->id;
                    $newItem->save();
                }
            }
        });
    }
}
