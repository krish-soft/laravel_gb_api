<?php

namespace App\Http\Controllers\Api\v1\User\Buyer\Demand;

use App\Enum\Common\Cart\CartStatusEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Cart\CartItem;
use App\Models\Buyer\Cart\DemandCart;
use App\Models\Buyer\Cart\DemandCartItem;
use App\Models\Master\Product\MstProductPackaging;
use App\Models\Seller\Product\ProductListingPackage;
use App\Policies\Buyer\BuyerPolicyManager;
use Illuminate\Http\Request;
use RuntimeException;

class DemandCartApiController extends ApiResponseWithAuthController
{
    /* =========================================================
       =============== GET / CREATE ACTIVE CART ================
       ========================================================= */

    public function getActiveCart(Request $request)
    {
        $user = $request->user();

        $cart = DemandCart::where('buyer_id', $user->id)
            ->whereIn('status', [
                CartStatusEnum::ACTIVE->value,
                CartStatusEnum::LOCKED->value,
            ])
            ->latest()
            ->with('demandCartItems')
            ->first();

        // If no cart OR terminal cart → create new
        if (
            !$cart ||
            in_array($cart->status, [
                CartStatusEnum::CONVERTED->value,
                CartStatusEnum::ABANDONED->value,
            ])
        ) {
            $cart = DemandCart::create([
                'buyer_id' => $user->id,
                'status' => CartStatusEnum::ACTIVE->value,
            ]);
        }


        $cart = DemandCart::with([
            'demandCartItems.product:id,name,product_code',
            // 'demandCartItems.variant:id,name'
        ])
            ->find($cart->id);

        // add total sold and remain qty and total qty total
        foreach ($cart->demandCartItems as $item) {

            // itemLineTotal
            $item->line_total = $item->order_qty * $item->pack_price;
        }

        return $this->successResponse(__('messages.success_messages.cart_fetched'), $cart, 200);
    }

    /* =========================================================
       ================= ADD ITEM TO CART ======================
       ========================================================= */

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:mst_products,id',
            'variant_id' => 'nullable|integer|exists:mst_product_variants,id',
            'order_qty' => 'required|integer|min:1',

            'pack_price' => 'required|numeric|min:0',
            'per_unit_price' => 'nullable|numeric|min:0',

            'product_packaging_id' => 'required|integer|exists:mst_product_packagings,id',

            // 'pack_size' => 'required|numeric|min:0.01',
            // 'pack_unit' => 'required|string|max:20',
            // 'pack_type_unit' => 'required|string|max:20',

        ]);

        $user = $request->user();
        $cart = $this->getWritableCart($user);

        // Check Pack Siz belogns to that product pacakging 
        $mstProductPacakging = MstProductPackaging::where('product_id', $data['product_id'])
            ->where('id', $data['product_packaging_id'])
            ->first();

        if (!$mstProductPacakging) {
            return $this->showErrorMessage(__('messages.error_messages.invalid_packaging'), 422);
        }

        // Pack Price need to get From Price modules based on product, variant and packaging
        // but get from request for now and validate it should not be less than 0

        // Check if item with same product and variant already exists in cart

        $item = DemandCartItem::where('demand_cart_id', $cart->id)
            ->where('product_id', $data['product_id'])
            // ->where('variant_id', $data['variant_id'] ?? null)
            ->first();

        if ($item) {
            $item->order_qty += $data['order_qty'];
            $item->total_price = $item->order_qty * $item->pack_price;
            $item->save();

            return $this->showSuccessMessage(__('messages.success_messages.cart_item_updated'), 200);
        }

        $item = DemandCartItem::create([
            'demand_cart_id' => $cart->id,

            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            'order_qty' => $data['order_qty'],

            'pack_price' => $data['pack_price'],
            'per_unit_price' => $data['per_unit_price'] ?? ($data['pack_price'] / $mstProductPacakging->pack_size) ?? null,

            // Snapshot
            'pack_size' => $mstProductPacakging->pack_size,
            'pack_unit' => $mstProductPacakging->pack_unit,
            'pack_type_unit' => $mstProductPacakging->pack_type_unit,

            'total_price' => $data['order_qty'] * $data['pack_price'],
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

        $item = DemandCartItem::where('demand_cart_id', $cart->id)
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


        $item = DemandCartItem::where('demand_cart_id', $cart->id)
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

        $cart->demandCartItems()->delete();

        return $this->showSuccessMessage(__('messages.success_messages.cart_cleared'), 200);
    }

    /* =========================================================
       ================= CART GUARD ============================
       ========================================================= */

    protected function getWritableCart($user): DemandCart
    {
        $cart = DemandCart::where('buyer_id', $user->id)
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
            return DemandCart::create([
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
