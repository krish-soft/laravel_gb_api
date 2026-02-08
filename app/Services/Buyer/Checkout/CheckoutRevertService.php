<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutRevertService
{
    public function revert(Order $order): Order
    {
        //        if (!in_array($order->order_status, [OrderStatusEnum::PENDING->value, OrderStatusEnum::PROCESSING->value])) {
        // Ignore this becasue revertin is base on failure of any transactions
        // if (!in_array($order->order_status, [OrderStatusEnum::FAILED_PAYMENT->value])) {
        //     throw new RuntimeException(__('messages.error_messages.order_cannot_be_reverted'));
        // }

        return DB::transaction(function () use ($order) {

            foreach ($order->orderItems as $orderItem) {

                $package = $orderItem->productListingPackage()
                    ->lockForUpdate()
                    ->first();

                if (!$package) {
                    continue;
                }

                /* -------------------------------------------------
                 | 1️⃣ Revert sold quantity
                 -------------------------------------------------*/
                $package->sold_qty -= $orderItem->order_qty;

                if ($package->sold_qty < 0) {
                    $package->sold_qty = 0;
                }

                /* -------------------------------------------------
                 | 2️⃣ Restore package flags
                 -------------------------------------------------*/
                $package->is_sold = ($package->sold_qty >= $package->qty);
                $package->save();

                /* -------------------------------------------------
                 | 3️⃣ Restore listing flags (FORCE UNLOCK)
                 -------------------------------------------------*/
                $listing = $package->productListingItem?->productListing;

                if (!$listing) {
                    continue;
                }

                // $totalQty = $listing->packages()->sum('qty');
                // $totalSold = $listing->packages()->sum('sold_qty');
                $totalQty = 0;
                $totalSold = 0;

                $totalQty = $listing->total_qty;
                $totalSold = $listing->total_total_sold_qtyqty;

                if ($totalSold <= 0) {
                    $listing->is_sold = false;
                    $listing->is_partial = false;
                } elseif ($totalSold < $totalQty) {
                    $listing->is_sold = false;
                    $listing->is_partial = true;
                } else {
                    $listing->is_sold = true;
                    $listing->is_partial = false;
                }

                // 🔥 IMPORTANT: force-enable listing again
                $listing->is_expired = false;
                $listing->is_locked = false;

                $listing->save();
            }

            /* -------------------------------------------------
             | 4️⃣ Mark order as CANCELLED
             -------------------------------------------------*/
            if (!in_array($order->order_status, [OrderStatusEnum::CANCELLED->value])) {
                $order->update([
                    'order_status' => OrderStatusEnum::CANCELLED->value, // Finally Cancelled
                    'remarks' => 'Order reverted due to failed payment', // can be anything not fail only for payment if manual then ?
                    // 'payment_status' => PaymentStatusEnum::FAILED->value,
                ]);
            }

            return $order;
        });
    }
}
