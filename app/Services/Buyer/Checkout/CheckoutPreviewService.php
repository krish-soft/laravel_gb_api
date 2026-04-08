<?php

namespace App\Services\Buyer\Checkout;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Common\Cart\CartStatusEnum;
use App\Models\Buyer\Cart\Cart;
use App\Models\Common\Accounting\Account;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Services\Common\Charge\ChargeCalculationService;
use RuntimeException;

class CheckoutPreviewService
{
    protected ChargeCalculationService $chargeService;

    public function __construct(ChargeCalculationService $chargeService)
    {
        $this->chargeService = $chargeService;
    }

    public function preview(Cart $cart, bool $isBuyerPickup): array
    {

        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->cartItems()->count() === 0) {
            throw new RuntimeException(__('messages.error_messages.cart_empty'));
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
                    'order_qty' => $cartItem->order_qty,
                    'pack_size' => $package->pack_size,
                    'pack_unit' => $package->pack_unit,
                    'pack_type_unit' => $package->pack_type_unit,
                ];
            } else {
                $hasInvalidItems = true;
            }

            $items[] = [
                'cart_item_id' => $cartItem->id,

                'listing_code' => $listing->listing_code,
                'product_listing_item_id' => $listingItem->id,
                'product_listing_package_id' => $package->id,

                'product_name' => $listingItem->product_name,
                'variant_name' => $listingItem->variant_name,

                'pack_size' => $package->pack_size,
                'pack_unit' => $package->pack_unit,
                'pack_type_unit' => $package->pack_type_unit,

                'order_qty' => $cartItem->order_qty,

                'taxable_amount' => $cartItem->total_price,
                'tax_amount' => 0, // tax calculation not implemented yet
                'total_amount' => $cartItem->total_price,


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
            'charge_taxable' => 0,
            'charge_tax' => 0,
            'total_charge_amount' => 0,
        ];

        if (!$hasInvalidItems && $subtotal > 0) {
            $chargeSummary = $this->chargeService->calculate(
                $cart->buyer->charge_level_code,
                $subtotal,
                $packages,
                $isBuyerPickup
            );
        }

        $minimumCartAmount = MstPaymentSetting::minCartOrderAmount();

        $canCheckout = !$hasInvalidItems && $subtotal >= $minimumCartAmount;
        $messageNotCheckout = null;

        if ($hasInvalidItems) {
            $messageNotCheckout .= __('messages.error_messages.cart_has_invalid_items');
        }

        if ($subtotal < $minimumCartAmount) {
            $messageNotCheckout .= __('messages.error_messages.cart_below_minimum_amount', [
                'amount' => number_format($minimumCartAmount, 2),
            ]);
        }

        $finalTotalAmount =  round($subtotal + $chargeSummary['total_charge_amount'], 2);

        // Check User Credit balance if payment method is credit and total amount > 0
        //
        $canCheckoutWithCredit = false;
        $creditBalanceToUse = 0;

        $buyerAccount = Account::getOrCreateByOwner(
            AccountOwnerTypeEnum::BUYER->value,
            $cart->buyer_id
        );

        if ($buyerAccount) {

            $availableBalance = (float) $buyerAccount->available_balance;
            $creditLimit = (float) $buyerAccount->credit_limit;
            $isCreditUseEnabled = (bool) ($buyerAccount->is_credit_enabled ?? false);

            if ($isCreditUseEnabled && $finalTotalAmount > 0) {

                // usable credit = available balance + credit limit
                $usableBalance = $availableBalance + $creditLimit;

                // if negative, cannot use credit
                if ($usableBalance > 0) {

                    // cannot use more than order amount
                    $creditBalanceToUse = min($usableBalance, $finalTotalAmount);

                    if ($creditBalanceToUse > 0) {
                        $canCheckoutWithCredit = true;
                    } else {
                        $creditBalanceToUse = 0;
                        $canCheckoutWithCredit = false;
                    }
                }
            }
        }


        // Save meta preview data to cart
        $cart->meta = [
            'previewed_at' => now(),
            'subtotal' => round($subtotal, 2),
            'total_charge_amount' => $chargeSummary['total_charge_amount'],
            'has_invalid_items' => $hasInvalidItems,
            'can_checkout' => $canCheckout,
            'message_not_checkout' => $messageNotCheckout,
            'charges' => $chargeSummary['charges'], // detailed charges so no need to rerun on confirm
            'can_checkout_with_credit' => $canCheckoutWithCredit,
            'credit_balance_to_use' => $creditBalanceToUse,
        ];

        $cart->save();

        return [
            'cart_id' => $cart->id,
            'currency' => MstFinanceSetting::currency() ?? 'INR',

            'items' => $items,

            'subtotal' => round($subtotal, 2),

            'charges' => $chargeSummary['charges'],
            'charge_taxable' => $chargeSummary['charge_taxable'],
            'charge_tax' => $chargeSummary['charge_tax'],
            'total_charge_amount' => $chargeSummary['total_charge_amount'],

            // Credit Balance Info
            'can_checkout_with_credit' => $canCheckoutWithCredit,
            'credit_balance_to_use' => $creditBalanceToUse,

            'total_amount' => $finalTotalAmount,
            'total_amount_after_credit' => round($finalTotalAmount - $creditBalanceToUse, 2),

            'has_invalid_items' => $hasInvalidItems,
            'can_checkout' => $canCheckout,
            'message_not_checkout' => $messageNotCheckout,

        ];
    }
}
