<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\Cart;
use App\Services\Buyer\Checkout\CheckoutPreviewService;
use App\Services\Buyer\Checkout\CheckoutConfirmService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckoutApiController extends ApiResponseWithAuthController
{
    /**
     * -------------------------------------------------
     * Checkout Preview
     * -------------------------------------------------
     */
    public function preview(
        Request $request,
        CheckoutPreviewService $service
    ) {
        try {
            $cart = Cart::where('buyer_id', $request->user()->id)
                ->where('status', 'active')
                ->with([
                    'cartItems.productListingPackage.productListingItem.productListing'
                ])
                ->firstOrFail();

            return $this->successResponse(
                $service->preview($cart),
                __('messages.success_messages.checkout_preview')
            );
        } catch (RuntimeException $e) {
            return $this->showErrorMessage($e->getMessage());
        }
    }

    /**
     * -------------------------------------------------
     * Checkout Confirm
     * -------------------------------------------------
     */
    public function confirm(
        Request $request,
        CheckoutConfirmService $service
    ) {
        try {
            $data = $request->validate([
                'payment_method' => 'required|in:cod,razorpay,manual',

                // ✅ charges must come from preview
                'charges' => 'required|array|min:1',
                'charges.*.charge_type' => 'required|string',
                'charges.*.charge_name' => 'required|string',
                'charges.*.taxable_amount' => 'required|numeric',
                'charges.*.tax_amount' => 'required|numeric',
                'charges.*.total_amount' => 'required|numeric',
            ]);

            $cart = Cart::where('buyer_id', $request->user()->id)
                ->where('status', 'active')
                ->with([
                    'cartItems.productListingPackage.productListingItem.productListing'
                ])
                ->firstOrFail();

            // ✅ CORRECT service call
            $order = $service->confirm(
                $cart,
                $data['charges']
            );

            return $this->successResponse(
                $order,
                __('messages.success_messages.order_created')
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (RuntimeException $e) {
            return $this->showErrorMessage($e->getMessage());
        }
    }
}
