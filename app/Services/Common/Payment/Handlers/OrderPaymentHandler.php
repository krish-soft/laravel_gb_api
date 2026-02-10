<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment\Payment;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Services\Accounting\OrderAccountingService;
use App\Services\Buyer\Checkout\CheckoutRevertService;
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

            //
            // 2️⃣ Record accounting entries
            app(OrderAccountingService::class)
                ->recordPaidOrder($order, $payment);

            //TODO:: Shipment process can be triggered here or via another service/event

            foreach ($order->orderItems->sortByDesc('order_qty') as $item) {

                $totalPackages = (int) $item->order_qty; // qty = number of packages

                // prevent duplicate creation (idempotent)
                $alreadyCreated = ShipmentPackage::where('order_item_id', $item->id)->count();

                $toCreate = max(0, $totalPackages - $alreadyCreated);

                for ($i = 0; $i < $toCreate; $i++) {

                    ShipmentPackage::create([
                        'order_id'       => $order->id,
                        'order_number'    => $order->order_number,
                        'order_item_id'  => $item->id,

                        'buyer_id'       => $order->buyer_id,
                        'seller_id'      => $item->seller->id, // Assuming OrderItem has a seller relationship

                        'pickup_fulfillment_location_id' => $item->pickup_fulfillment_location_id, // Assuming OrderItem has pickupFulfillmentLocation relationship
                        'shipping_fulfillment_location_id' => $order->shipping_fulfillment_location_id, // Assuming same as order's shipping location for now

                        'qty'            => 1,
                        'pack_size'      => $item->pack_size,
                        'pack_unit'      => $item->pack_unit,
                        'pack_type_unit' => $item->pack_type_unit,

                        'package_number' => ShipmentPackage::generatePackageNumber($order->buyer_id, $order->id),
                        'status'         => ShipmentStatusEnum::PENDING->value,

                        'pickup_depot_id' => $item?->pickup_depot?->id, // Assuming OrderItem has pickup_depot_id
                        'shipping_depot_id' => $order->depot_id, // From order directly
                    ]);
                }
            }
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
