<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Models\Buyer\Cart\Cart;
use App\Services\Common\Charge\ChargeCalculationService;
use RuntimeException;

class CheckoutPreviewService
{
    protected ChargeCalculationService $chargeService;

    public function __construct(ChargeCalculationService $chargeService)
    {
        $this->chargeService = $chargeService;
    }

    public function preview(Cart $cart): array
    {
        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->cartItems()->count() === 0) {
            throw new RuntimeException(__('messages.error_messages.cart_empty'));
        }

        $items = [];
        $packages = [];

        $subtotal = 0;
        $totalQty = 0;
        $totalWeight = 0;

        $hasInvalidItems = false;

        foreach ($cart->cartItems as $cartItem) {

            $package = $cartItem->productListingPackage;
            $listingItem = $package?->productListingItem;
            $listing = $listingItem?->productListing;

            $availableQty = $package
                ? max(0, $package->qty - $package->sold_qty)
                : 0;

            $isAvailable = true;
            $reasonCode = null;
            $reasonMessage = null;

            if (!$package || !$listingItem || !$listing) {
                $isAvailable = false;
                $reasonCode = 'ITEM_REMOVED';
                $reasonMessage = __('messages.error_messages.listing_not_available');
            } elseif (!$listing->is_active || $listing->is_expired) {
                $isAvailable = false;
                $reasonCode = 'LISTING_INACTIVE';
                $reasonMessage = __('messages.error_messages.listing_locked');
            } elseif ($availableQty <= 0) {
                $isAvailable = false;
                $reasonCode = 'OUT_OF_STOCK';
                $reasonMessage = __('messages.error_messages.package_sold_out');
            } elseif ($availableQty < $cartItem->order_qty) {
                $isAvailable = false;
                $reasonCode = 'INSUFFICIENT_STOCK';
                $reasonMessage = __('messages.error_messages.insufficient_stock');
            }

            if ($isAvailable) {
                $subtotal += $cartItem->total_price;

                $totalQty += $cartItem->order_qty;
                $totalWeight += ($cartItem->order_qty * $package->pack_size);

                $packages[] = [
                    'order_qty'      => $cartItem->order_qty,
                    'pack_size'      => $package->pack_size,
                    'pack_unit'      => $package->pack_unit,
                    'pack_type_unit' => $package->pack_type_unit,
                ];
            } else {
                $hasInvalidItems = true;
            }

            $items[] = [
                'cart_item_id' => $cartItem->id,

                'listing_id' => $listing?->id,
                'listing_item_id' => $listingItem?->id,
                'package_id' => $package?->id,

                'product_name' => $listingItem?->product_name,
                'variant_name' => $listingItem?->variant_name,

                'order_qty' => $cartItem->order_qty,
                'unit_price' => $cartItem->pack_price,
                'total_price' => $cartItem->total_price,

                'is_available' => $isAvailable,
                'available_qty' => $availableQty,
                'invalid_reason_code' => $reasonCode,
                'invalid_reason_message' => $reasonMessage,
            ];
        }

        // ======================================================
        // CHARGES (ONLY IF CART IS VALID)
        // ======================================================

        $chargeSummary = [
            'charges' => [],
            'total_charge' => 0,
            'total_tax' => 0,
            'total_amount' => 0,
        ];

        if (!$hasInvalidItems && $subtotal > 0) {
            $chargeSummary = $this->chargeService->calculate(
                $cart->buyer->charge_level_code,
                $subtotal,
                $packages
            );
        }

        return [
            'cart_id' => $cart->id,
            'currency' => 'INR',

            'items' => $items,

            'subtotal' => round($subtotal, 2),

            'charges' => $chargeSummary['charges'],
            'total_charge' => $chargeSummary['total_charge'],
            'total_tax' => $chargeSummary['total_tax'],

            'total_amount' => round(
                $subtotal + $chargeSummary['total_amount'],
                2
            ),

            'has_invalid_items' => $hasInvalidItems,
            'can_checkout' => !$hasInvalidItems,
        ];
    }
}
