<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Cart\CartItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Policies\Buyer\BuyerPolicyManager;
use Illuminate\Http\Request;
use RuntimeException;

class CartApiController extends ApiResponseWithAuthController
{
    /* =========================================================
       =============== GET / CREATE ACTIVE CART ================
       ========================================================= */

    public function getActiveCart(Request $request)
    {
        $user = $request->user();

        // if (!$user->isBuyer()) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        $cart = Cart::where('buyer_id', $user->id)
            ->whereIn('status', [
                CartStatusEnum::ACTIVE->value,
                CartStatusEnum::LOCKED->value,
            ])
            ->latest()
            ->with('cartItems')
            ->first();

        // If no cart OR terminal cart → create new
        if (
            !$cart ||
            in_array($cart->status, [
                CartStatusEnum::CONVERTED->value,
                CartStatusEnum::ABANDONED->value,
            ])
        ) {
            $cart = Cart::create([
                'buyer_id' => $user->id,
                'status' => CartStatusEnum::ACTIVE->value,
            ]);
        }

        // $cart = Cart::with('cartItems')
        //     ->find($cart->id);

        $cart = Cart::with([
            'cartItems.productListingItem.product:id,name',
            // 'cartItems.productListingItem.listingPackages',
            // 'cartItems.seller:id,nickname,user_code',
        ])
            ->find($cart->id);

        // add total sold and remain qty and total qty total
        foreach ($cart->cartItems as $item) {
            $soldQty = 0;
            $remainQty = 0;
            $totalQty = 0;

            foreach ($item->productListingItem->listingPackages as $package) {

                if ($package->id == $item->product_listing_package_id) {
                    $soldQty += $package->sold_qty;
                    $remainQty += ($package->qty - $package->sold_qty);
                    $totalQty += $package->qty;
                }
                unset($item->productListingItem->listingPackages);
            }


            $item->sold_qty = $soldQty;
            $item->remain_qty = $remainQty;
            $item->total_qty = $totalQty;
        }




        return $this->successResponse(__('messages.success_messages.cart_fetched'), $cart, 200);
    }

    /* =========================================================
       ================= ADD ITEM TO CART ======================
       ========================================================= */

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_listing_package_id' => 'required|integer|exists:product_listing_packages,id',
            'order_qty' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cart = $this->getWritableCart($user);

        // if (!$user->isBuyer() || $cart->buyer_id !== $user->id) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        $package = ProductListingPackage::with(
            'productListingItem.productListing'
        )->findOrFail($data['product_listing_package_id']);

        // Check that product listing is active or not 
        if (!$package->productListingItem?->productListing?->is_active) {
            return $this->showErrorMessage(__('messages.error_messages.product_listing_inactive'), 400);
        }


        // First check its allowable to add this package
        if (BuyerPolicyManager::canBuyerSeeProductListing($user, $package->productListingItem->productListing) === false) {
            return $this->showErrorMessage(__('messages.error_messages.unauthorized_action'), 403);
        }

        // Cannot add sold-out package
        if ($package->sold_qty >= $package->qty) {
            return $this->showErrorMessage(__('messages.error_messages.package_already_sold'), 400);
        }

        if ($package->qty - $package->sold_qty < $data['order_qty']) {
            return $this->showErrorMessage(__('messages.error_messages.insufficient_stock'), 400);
        }

        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_listing_package_id', $package->id)
            ->first();

        if ($item) {
            $item->order_qty += $data['order_qty'];
            $item->total_price = $item->order_qty * $item->pack_price;
            $item->save();

            return $this->showSuccessMessage(__('messages.success_messages.cart_item_updated'), 200);
        }

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'seller_id' => $package->productListingItem->productListing->seller_id,
            'product_listing_item_id' => $package->product_listing_item_id,
            'product_listing_package_id' => $package->id,

            'order_qty' => $data['order_qty'],

            // Snapshot
            'pack_size' => $package->pack_size,
            'pack_unit' => $package->pack_unit,
            'pack_type_unit' => $package->pack_type_unit,
            'pack_price' => $package->pack_price,
            'per_unit_price' => $package->per_kg_price,

            'discount_amount' => $package->discount_amount ?? 0,
            'discount_type' => $package->discount_type ?? 0,

            'total_price' => $data['order_qty'] * $package->pack_price,
        ]);

        return $this->showSuccessMessage(__('messages.success_messages.cart_item_added'), 200);
    }

    /* =========================================================
       ================= UPDATE CART ITEM ======================
       ========================================================= */

    public function updateItem(Request $request, int $cartItemId)
    {
        $data = $request->validate([
            'order_qty' => 'required|integer|min:1',
        ]);

        $user = $request->user();

        $cart = $this->getWritableCart($user);

        // if (!$user->isBuyer() || $cart->buyer_id !== $user->id) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        $item = CartItem::where('cart_id', $cart->id)
            ->findOrFail($cartItemId);

        $item->order_qty = $data['order_qty'];
        $item->total_price = $item->order_qty * $item->pack_price;
        $item->save();

        return $this->showSuccessMessage(__('messages.success_messages.cart_item_updated'), 200);
    }

    /* =========================================================
       ================= REMOVE CART ITEM ======================
       ========================================================= */

    public function removeItem(Request $request, int $cartItemId)
    {
        $user = $request->user();


        $cart = $this->getWritableCart($user);

        // if (!$user->isBuyer() || $cart->buyer_id !== $user->id) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }


        $item = CartItem::where('cart_id', $cart->id)
            ->findOrFail($cartItemId);

        $item->delete();

        return $this->showSuccessMessage(__('messages.success_messages.cart_item_removed'), 200);
    }

    /* =========================================================
       ================= CLEAR CART ============================
       ========================================================= */

    public function clearCart(Request $request)
    {
        $user = $request->user();
        $cart = $this->getWritableCart($user);

        // if (!$user->isBuyer() || $cart->buyer_id !== $user->id) {
        //     return $this->showErrorMessage(__('messages.error_messages.unauthorized_access'), 403);
        // }

        $cart->cartItems()->delete();

        return $this->showSuccessMessage(__('messages.success_messages.cart_cleared'), 200);
    }

    /* =========================================================
       ================= CART GUARD ============================
       ========================================================= */

    protected function getWritableCart($user): Cart
    {
        $cart = Cart::where('buyer_id', $user->id)
            ->latest()
            ->first();

        // Create new if none or terminal
        if (
            !$cart ||
            in_array($cart->status, [
                CartStatusEnum::CONVERTED->value,
                CartStatusEnum::ABANDONED->value,
            ])
        ) {
            return Cart::create([
                'buyer_id' => $user->id,
                'status' => CartStatusEnum::ACTIVE->value,
            ]);
        }

        // Block edits if locked
        if ($cart->status === CartStatusEnum::LOCKED->value) {
            throw new RuntimeException(
                __('messages.error_messages.cart_locked')
            );
        }

        return $cart;
    }
}
