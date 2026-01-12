<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Cart\CartStatusEnum;
use App\Models\Buyer\Cart\Cart;
use RuntimeException;

class CheckoutPreviewService
{
    public function preview(Cart $cart): array
    {
        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->cartItems()->count() === 0) {
            throw new RuntimeException(__('messages.error_messages.cart_empty'));
        }

        $items = [];
        $subtotal = 0;
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
            } else {
                $hasInvalidItems = true;
            }

            $items[] = [
                'cart_item_id' => $cartItem->id,

                // 🔥 LIVE DATA (SOURCE OF TRUTH)
                'listing_id' => $listing?->id,
                'listing_item_id' => $listingItem?->id,
                'package_id' => $package?->id,

                'product_name' => $listingItem?->product_name,
                'variant_name' => $listingItem?->variant_name,
                'unit' => $listingItem?->unit,

                'order_qty' => $cartItem->order_qty,
                'unit_price' => $cartItem->pack_price,
                'total_price' => $cartItem->total_price,

                // UI FLAGS
                'is_available' => $isAvailable,
                'available_qty' => $availableQty,
                'invalid_reason_code' => $reasonCode,
                'invalid_reason_message' => $reasonMessage,
            ];
        }

        return [
            'cart_id' => $cart->id,
            'currency' => 'INR',
            'items' => $items,
            'subtotal' => $subtotal,
            'charges' => [],
            'total_amount' => $subtotal,
            'has_invalid_items' => $hasInvalidItems,
            'can_checkout' => !$hasInvalidItems,
        ];
    }
}


