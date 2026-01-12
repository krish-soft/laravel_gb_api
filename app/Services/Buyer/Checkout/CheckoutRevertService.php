<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Order\OrderStatusEnum;
use App\Models\Buyer\Order\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutRevertService
{
    public function revert(Order $order): Order
    {
        if (!in_array($order->status, [OrderStatusEnum::PENDING->value, OrderStatusEnum::PROCESSING->value])) {
            throw new RuntimeException('Order cannot be reverted');
        }

        return DB::transaction(function () use ($order) {

            foreach ($order->orderItems as $orderItem) {

                $package = $orderItem->package()
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

                $totalQty  = $listing->packages()->sum('qty');
                $totalSold = $listing->packages()->sum('sold_qty');

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
             | 4️⃣ Mark order as SUSPENDED
             -------------------------------------------------*/
            $order->update([
                'status' => OrderStatusEnum::SUSPENDED->value, // or FAILED
            ]);

            return $order;
        });
    }
}
