<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Enum\Queue\QueueEnum;
use App\Jobs\Accounting\JobOrderAccounting;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Accounting\Account;
use App\Models\Common\Payment\Payment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Buyer\Checkout\CheckoutRevertService;
use App\Services\Common\Shipment\ShipmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPaymentHandler
{


    // Handle successful payment
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
                'is_paid' => true,
                'order_status' => OrderStatusEnum::CONFIRMED->value,
                'payment_status' => PaymentStatusEnum::PAID->value,
                //
                'reference' => $payment->payment_code ?? null,
                'payment_reference' => $payment->gateway_order_id ?? null,


            ]);


            // Account entry will be handled by listener to avoid any delay in response and also to make sure it will be processed even if there is any issue in current transaction
            JobOrderAccounting::dispatch([$order->id])
                ->afterCommit() // Only dispatch if transaction successful
                ->delay(now()->addSeconds(5))
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
            $checkoutRevertService = app(CheckoutRevertService::class);
            $checkoutRevertService->revert($order);


            $oldCart = $order->cart;

            if ($oldCart) {

                // 1️⃣ Clone cart
                $newCart = $oldCart->replicate();
                $newCart->cart_uuid = null;   // let booted() regenerate
                $newCart->status = CartStatusEnum::ACTIVE->value;
                $newCart->save();

                // 2️⃣ Clone cart items
                foreach ($oldCart->cartItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->cart_id = $newCart->id;
                    $newItem->save();
                }
            }
        });
    }
}
