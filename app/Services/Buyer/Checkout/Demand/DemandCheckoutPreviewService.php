<?php

namespace App\Services\Buyer\Checkout\Demand;

use App\Enum\Accounting\AccountOwnerTypeEnum;
use App\Enum\Common\Cart\CartStatusEnum;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Cart\DemandCart;
use App\Models\Common\Accounting\Account;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Services\Common\Charge\ChargeCalculationService;
use RuntimeException;

class DemandCheckoutPreviewService
{
    protected ChargeCalculationService $chargeService;

    public function __construct(ChargeCalculationService $chargeService)
    {
        $this->chargeService = $chargeService;
    }

    public function preview(DemandCart $cart, bool $isBuyerPickup): array
    {

        if ($cart->status !== CartStatusEnum::ACTIVE->value) {
            throw new RuntimeException(__('messages.error_messages.cart_not_active'));
        }

        if ($cart->demandCartItems()->count() === 0) {
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

        foreach ($cart->demandCartItems as $cartItem) {

            $product = $cartItem->product;


            $subtotal += $cartItem->total_price;

            $totalQty += $cartItem->order_qty;
            $totalWeight += ($cartItem->order_qty * $cartItem->pack_size);

            $packages[] = [
                'pack_size' => $cartItem->pack_size,
                'pack_unit' => $cartItem->pack_unit,
                'pack_type_unit' => $cartItem->pack_type_unit,
                'order_qty' => $cartItem->order_qty,
            ];


            $items[] = [
                'cart_item_id' => $cartItem->id,

                'product_name' => $product->name,
                'variant_name' => $product->variant_name,

                'pack_size' => $cartItem->pack_size,
                'pack_unit' => $cartItem->pack_unit,
                'pack_type_unit' => $cartItem->pack_type_unit,

                'order_qty' => $cartItem->order_qty,

                'taxable_amount' => $cartItem->total_price,
                'tax_amount' => 0, // tax calculation not implemented yet
                'total_amount' => $cartItem->total_price,


                'is_available' => true,
                // 'available_qty' => 0

                'invalid_reason_code' => null,
                'invalid_reason_message' => null,
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
            $avaliableBalance = $buyerAccount->available_balance;

            if ($avaliableBalance > 0 && $finalTotalAmount > 0) {
                $canCheckoutWithCredit = true;
                $creditBalanceToUse = $avaliableBalance;
            }

            //
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
