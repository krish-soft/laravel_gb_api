<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Enum\Common\Order\OrderChargeTypeEnum;
use App\Enum\Common\Order\OrderStatusEnum;
use App\Enum\Common\Wallet\WalletStatusEnum;
use App\Enum\Common\Wallet\WalletTypeEnum;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderCharge;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Setting\AppSetting;
use App\Services\Common\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CheckoutConfirmService
{

    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }



    public function confirm(Cart $cart, array $charges,  $paymentMethod): Order
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

        return DB::transaction(function () use ($cart, $charges, $paymentMethod) {
            /* -------------------------------------------------
             | 0️⃣ Lock cart (prevent double checkout)
             -------------------------------------------------*/
            $cart->update([
                'status' => CartStatusEnum::LOCKED->value,
            ]);

            /* -------------------------------------------------
             | 1️⃣ Create Order
             -------------------------------------------------*/
            $order = Order::create([
                'buyer_id' => $cart->buyer_id,
                'cart_id' => $cart->id,
                'currency' => AppSetting::first()?->currency_code ?? 'INR',
                'subtotal' => 0,
                'total_amount' => 0,
                'status' => OrderStatusEnum::PENDING->value,
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

                    'listing_id' => $listing->id,
                    'listing_item_id' => $listingItem->id,
                    'package_id' => $package->id,

                    'product_name' => $listingItem->product_name,
                    'variant_name' => $listingItem->variant_name,
                    'unit' => $listingItem->unit,

                    'order_qty' => $cartItem->order_qty,
                    'unit_price' => $cartItem->pack_price,
                    'total_price' => $lineTotal,

                    'seller_id' => $listing->seller_id,
                    'fulfillment_location_id' => $package->fulfillment_location_id,
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
            ]);

            // Everything okay then mark order as processing

            $order->update([
                'status' => OrderStatusEnum::PROCESSING->value,
            ]);



            ### Wallet Service start
            $meta = [
                'description' => 'Order #' . $order->order_number . ' created',
                'source_type' => Order::class,
                'source_id'   => $order->id,
                'source_code' => $order->order_number,
                'reference'   => $order->order_number,
                'payment_reference' => null,
                'gateway'     => $paymentMethod,
            ];

            $txn = $this->walletService->createTransaction(
                $order->buyer->wallet,
                $order->total_amount - $order->tax_amount, // tax stays separate ✔
                WalletTypeEnum::DEBIT,
                WalletStatusEnum::HOLD, // $walletStatus,
                $meta
            );

            $order->updateQuietly([
                'wallet_txn_code' => $txn->wallet_txn_code,
            ]);
            ## Wallet Service end

            ## Hold to be finalized upon payment completion
            ## Cancellation apply from observer if order cancelled/refunded


            return $order;
        });
    }
}
