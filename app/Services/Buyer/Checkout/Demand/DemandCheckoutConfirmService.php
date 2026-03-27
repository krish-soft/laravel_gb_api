<?php

namespace App\Services\Buyer\Checkout\Demand;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Charge\ChargesEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Models\Buyer\Cart\DemandCart;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\DemandOrderCharge;
use App\Models\Buyer\Order\DemandOrderItem;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DemandCheckoutConfirmService
{


    public function confirm(DemandCart $cart, array $charges, $paymentMethod, FulfillmentLocation $fulfillmentLocation, array $cartMeta = []): DemandOrder
    {

        /* -------------------------------------------------
         | Cart validations
         -------------------------------------------------*/
        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->demandCartItems()->count() === 0) {
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

        return DB::transaction(function () use ($cart, $charges, $paymentMethod, $fulfillmentLocation) {
            /* -------------------------------------------------
             | 0️⃣ Lock cart (prevent double checkout)
             -------------------------------------------------*/
            $cart->update([
                'status' => CartStatusEnum::LOCKED->value,
                'locked_at' => now(),
            ]);

            // Get Depot Id from fulfillment location if not then from user 
            $fulfillmentLocationId = $fulfillmentLocation->id;
            $depotId = $fulfillmentLocation->primaryDepot->depot_id ?? $cart->buyer->primaryDepot->depot_id; // depot_id becasue have anotehr tables so

            /* -------------------------------------------------
             | 1️⃣ Create Order
             -------------------------------------------------*/
            $order = DemandOrder::create([
                'buyer_id' => $cart->buyer_id,
                'cart_id' => $cart->id,
                'depot_id' => $depotId,

                'currency' => MstFinanceSetting::currency() ?? 'INR',
                'order_date' => date('Y-m-d'),
                'payment_method' => $paymentMethod,
                'subtotal' => 0,
                'total_amount' => 0,
                'order_status' => OrderStatusEnum::PENDING->value,

                'shipping_fulfillment_location_id' =>  $fulfillmentLocationId // shipping location from cart
            ]);

            $subtotal = 0;
            $baseItemTaxableTotal = 0;

            /* -------------------------------------------------
             | 2️⃣ Process Cart Items (STRICT)
             -------------------------------------------------*/
            foreach ($cart->demandCartItems as $cartItem) {


                $product = $cartItem->product;

                $lineTotal = $cartItem->order_qty * $cartItem->pack_price;
                $subtotal += $lineTotal;
                $baseItemTaxableTotal += $lineTotal;


                /* ---- Order item snapshot ---- */
                DemandOrderItem::create([

                    'demand_order_id' => $order->id,
                    'order_number' => $order->order_number,

                    'product_id' => $cartItem->product_id,
                    'product_code' => $product->product_code ?? null,
                    'product_name' => $product->name,

                    'variant_code' => $listingVariant?->variant_code ?? null,
                    'variant_name' =>  $listingVariant?->name ?? null,

                    'pack_size' => $cartItem->pack_size,
                    'pack_unit' => $cartItem->pack_unit,
                    'pack_type_unit' => $cartItem->pack_type_unit,

                    'order_qty' => $cartItem->order_qty,
                    'pack_price' => $cartItem->pack_price,
                    'per_unit_price' => $cartItem->per_unit_price,

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
                if (!in_array($charge['charge_code'], ChargesEnum::casesAsValues())) {
                    throw new RuntimeException(__('messages.error_messages.checkout_failed'));
                }


                DemandOrderCharge::create([
                    'demand_order_id' => $order->id,
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

                // Item Sub total add taxavble
                $subtotal += $charge['taxable_amount'];
            }


            ### NOTE: We can add one more step here to validate order total with cart total and if mismatch then throw error but for now we are assuming it will always match because we are taking charges from cart meta only but in future if we want to calculate charges again on confirm then this step will be important to avoid any manipulation from client side.
            $creditAmount = 0;
            if (isset($cartMeta)) {

                $canUseCredit = $cartMeta['can_checkout_with_credit'] ?? false;
                $creditBalanceToUse = $cartMeta['credit_balance_to_use'] ?? 0;
                $creditAmount = $canUseCredit ? $creditBalanceToUse : 0;
            }

            /* -------------------------------------------------
             | 4️⃣ Update Order Totals (FINAL)
             -------------------------------------------------*/
            $order->update([
                'base_amount' => $baseItemTaxableTotal, // we can use it for accounting and settlement items total only
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount, // from charges only
                'total_amount' => $subtotal + $taxAmount,
                'credit_amount' => $creditAmount,
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
