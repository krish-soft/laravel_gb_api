<?php

namespace App\Services\Common\Payment\Handlers;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
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

            // Buyer Account if credits used then add entry as debit to ensure debited..
            // if ($payment->credit_amount > 0) {
            //     // No need to do this entry due t owhen invoice base entry will do will minus this amount
            //     $accountingService = app(AccountingService::class);
            //     $buyerAccount = Account::getOrCreateByOwner(
            //         AccountOwnerTypeEnum::BUYER->value,
            //         $order->buyer_id
            //     );
            // }


            //
            // 2️⃣ Record accounting entries
            // We will start later reconcile service that time to do
            // app(OrderAccountingService::class)
            //     ->recordPaidOrder($order, $payment);

            // NEW FLOW DUE TO CUTOFF CHANGE
            //TODO:: Shipment process can be triggered here or via another service/event
            // WHEN CUTOFF HAPPEN THAT TIME WILL DO
            // foreach ($order->orderItems->sortByDesc('order_qty', SORT_DESC) as $item) {

            //     $totalPackages = (int) $item->order_qty; // qty = number of packages

            //     // prevent duplicate creation (idempotent)
            //     $alreadyCreated = ShipmentPackage::where('order_item_id', $item->id)->count();

            //     $toCreate = max(0, $totalPackages - $alreadyCreated);

            //     for ($i = 0; $i < $toCreate; $i++) {

            //         $productListing = $item->productListingItem?->productListing;
            //         $pkg = $item->productListingPackage;

            //         // Regular Orders
            //         ShipmentPackage::create([
            //             'order_id'       => $order->id,
            //             'order_item_id'  => $item->id,

            //             'shipment_date' => date('Y-m-d'), // Set shipment date to current date, can be adjusted as needed
            //             'order_type' => 'regular', // For future use if we have different order types

            //             'buyer_id'       => $order->buyer_id,
            //             'seller_id'      => $item->seller->id, // Assuming OrderItem has a seller relationship

            //             'product_listing_package_id' => $pkg?->id, // Assuming OrderItem has this field
            //             'product_listing_id' => $productListing?->id, // Assuming OrderItem has this field

            //             'pickup_fulfillment_location_id' => $item->pickup_fulfillment_location_id, // Assuming OrderItem has pickupFulfillmentLocation relationship
            //             'shipping_fulfillment_location_id' => $order->shipping_fulfillment_location_id, // Assuming same as order's shipping location for now

            //             'product_code' => $item->product_code,
            //             'product_name' => $item->product_name,

            //             'qty'            => 1,
            //             'pack_size'      => $item->pack_size,
            //             'pack_price'     => $item->pack_price,
            //             'pack_unit'      => $item->pack_unit,
            //             'pack_type_unit' => $item->pack_type_unit,

            //             'package_number' => ShipmentPackage::generatePackageNumber($order->buyer_id),
            //             'status'         => ShipmentStatusEnum::PENDING->value,

            //             'pickup_depot_id' => $item?->pickup_depot?->depot_id, // Assuming OrderItem has pickup_depot_id
            //             'shipping_depot_id' => $order->depot_id, // From order directly

            //             'is_buyer_pickup' => $order->is_buyer_pickup, // From order directly
            //             'is_seller_dropoff' => $productListing?->is_seller_dropoff ?? false, // From product listing if available
            //         ]);
            //     }
            // }


            // // Once Shipment Ready create a Shipments and assign packages to it and update status to ready for pickup or ready for dispatch based on shipment type (pickup or dispatch)

            // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::PICKUP->value);
            // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::TRANSFER->value);
            // app(ShipmentService::class)->createShipmentAndGroups(ShipmentStatusEnum::DISPATCH->value);



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
