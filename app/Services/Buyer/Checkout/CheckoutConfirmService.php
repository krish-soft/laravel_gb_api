<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderChargeTypeEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderCharge;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutConfirmService
{


    public function confirm(Cart $cart, array $charges, $paymentMethod): Order
    {

        /* -------------------------------------------------
         | Cart validations
         -------------------------------------------------*/
        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->cartItems()->count() === 0) {
            throw new RuntimeException(__('messages.error_messages.cart_empty'));
        }

        if (empty($charges) || !is_array($charges)) {
            throw new RuntimeException(__('messages.error_messages.checkout_failed'));
        }

        // Check cart is expiry base on updated_at + expiry minutes
        $expiryMinutes = MstPaymentSetting::cartExpiryMinutes();
        if ($cart->updated_at->addMinutes($expiryMinutes) < now()) {
            // Mark cart as expired
            $cart->update([
                'status' => CartStatusEnum::EXPIRED->value,
            ]);
            throw new RuntimeException(__('messages.error_messages.cart_expired'));
        }

        return DB::transaction(function () use ($cart, $charges, $paymentMethod) {
            /* -------------------------------------------------
             | 0️⃣ Lock cart (prevent double checkout)
             -------------------------------------------------*/
            $cart->update([
                'status' => CartStatusEnum::LOCKED->value,
                'locked_at'=> now(),
            ]);

            /* -------------------------------------------------
             | 1️⃣ Create Order
             -------------------------------------------------*/
            $order = Order::create([
                'buyer_id' => $cart->buyer_id,
                'cart_id' => $cart->id,
                'currency' => MstFinanceSetting::currency() ?? 'INR',
                'subtotal' => 0,
                'total_amount' => 0,
                'order_status' => OrderStatusEnum::PENDING->value,

                'shipping_fulfillment_location_id' => $cart->fulfillment_location_id // shipping location from cart
            ]);

            $subtotal = 0;

            /* -------------------------------------------------
             | 2️⃣ Process Cart Items (STRICT)
             -------------------------------------------------*/
            foreach ($cart->cartItems as $cartItem) {

                $package = $cartItem->productListingPackage()
                    ->lockForUpdate()
                    ->first();

                $listingItem = $package?->productListingItem;
                $listing = $listingItem?->productListing;

                if (!$package || !$listingItem || !$listing) {
                    throw new RuntimeException(__('messages.error_messages.listing_not_available'));
                }

                if (
                    !$listing->is_active ||
                    $listing->is_expired ||
                    $listing->is_sold
                ) {
                    throw new RuntimeException(__('messages.error_messages.listing_locked'));
                }

                if ($package->is_sold) {
                    throw new RuntimeException(__('messages.error_messages.package_sold_out'));
                }

                $availableQty = $package->qty - $package->sold_qty;

                if ($availableQty < $cartItem->order_qty) {
                    throw new RuntimeException(__('messages.error_messages.insufficient_stock'));
                }

                /* ---- Deduct stock ---- */
                $package->increment('sold_qty', $cartItem->order_qty);

                if ($package->sold_qty >= $package->qty) {
                    $package->update(['is_sold' => true]);
                }

                if ($listing->packages()->where('is_sold', false)->count() === 0) {
                    $listing->update(['is_sold' => true]);
                }

                $lineTotal = $cartItem->order_qty * $cartItem->pack_price;
                $subtotal += $lineTotal;

                /* ---- Order item snapshot ---- */
                OrderItem::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,

                    'pickup_fulfillment_location_id' => $listing->fulfillment_location_id, // seller location

                    'listing_code' => $listing->listing_code,
                    'product_listing_item_id' => $listingItem->id,
                    'product_listing_package_id' => $package->id,

                    'product_name' => $listingItem->product_name,
                    'variant_name' => $listingItem->variant_name,

                    'pack_size' => $package->pack_size,
                    'pack_unit' => $package->pack_unit,
                    'pack_type_unit' => $package->pack_type_unit,

                    'order_qty' => $cartItem->order_qty,
                    'pack_price' => $cartItem->pack_price,

                    'taxable_amount' => $lineTotal,
                    'tax_amount' => 0, // tax from charges only
                    'total_amount' => $lineTotal,

                ]);
            }

            /* -------------------------------------------------
             | 3️⃣ Persist Order Charges (FROM CART)
             -------------------------------------------------*/
            $taxAmount = 0;
            $chargesTotal = 0;

            foreach ($charges as $charge) {

                // enum validation (IMPORTANT)
                if (!OrderChargeTypeEnum::tryFrom($charge['charge_type'])) {
                    throw new RuntimeException(__('messages.error_messages.checkout_failed'));
                }

                OrderCharge::create([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,

                    'charge_code' => $charge['charge_code'],
                    'charge_name' => $charge['charge_name'],

                    'rule_type' => $charge['rule_type'] ?? null,
                    'rule_no' => $charge['rule_no'] ?? null,
                    'rule_desc' => $charge['rule_desc'] ?? null,

                    'taxable_amount' => $charge['taxable_amount'],
                    'tax_amount' => $charge['tax_amount'],
                    'total_amount' => $charge['total_amount'],
                ]);

                $taxAmount += $charge['tax_amount'];
                $chargesTotal += $charge['total_amount'];
            }

            /* -------------------------------------------------
             | 4️⃣ Update Order Totals (FINAL)
             -------------------------------------------------*/
            $order->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount, // from charges only
                'total_amount' => $subtotal + $chargesTotal,
            ]);

            /* -------------------------------------------------
             | 5️⃣ Convert Cart
             -------------------------------------------------*/
            $cart->update([
                'status' => CartStatusEnum::CONVERTED->value,
                'converted_at' => now(),
            ]);

            // Everything okay then mark order as processing

            $order->update([
                'order_status' => OrderStatusEnum::PROCESSING->value,
            ]);


            // Trigger razorpay from controller to frontend

            return $order;
        });
    }
}
