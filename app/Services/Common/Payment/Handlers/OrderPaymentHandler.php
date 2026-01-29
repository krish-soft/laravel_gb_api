<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment\Payment;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Buyer\Checkout\CheckoutRevertService;
use Illuminate\Support\Facades\DB;

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
                'order_status' => OrderStatusEnum::CONFIRMED->value,
                'payment_status' => PaymentStatusEnum::PAID->value,
            ]);

            //
            // 2️⃣ Record accounting entries
            app(OrderAccountingService::class)
                ->recordPaidOrder($order, $payment);

            //TODO:: Shipment process can be triggered here or via another service/event
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
            ]);

            // Then Revert due to revert will not apply on processing so no issues

            // Revert Checkout
            $checkoutRevertService = app(CheckoutRevertService::class);
            $checkoutRevertService->revert($order);
        });
    }
}
