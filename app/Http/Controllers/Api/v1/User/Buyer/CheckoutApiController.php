<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Order\Order;
use App\Models\Setting\AppSetting;
use App\Services\Buyer\Checkout\CheckoutConfirmService;
use App\Services\Buyer\Checkout\CheckoutPreviewService;
use App\Services\Common\Payment\Gateways\RazorpayService;
use App\Services\Common\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class CheckoutApiController extends ApiResponseWithAuthController
{
    /**
     * -------------------------------------------------
     * Checkout Preview
     * -------------------------------------------------
     */
    public function preview(
        Request                $request,
        CheckoutPreviewService $service
    )
    {
        try {
            $cart = Cart::where('buyer_id', $request->user()->id)
                ->where('status', 'active')
                ->with([
                    'cartItems.productListingPackage.productListingItem.productListing'
                ])
                ->firstOrFail();

            return $this->successResponse(
                __('messages.success_messages.checkout_preview'),
                $service->preview($cart),
                200
            );
        } catch (Exception $e) {
            return $this->showErrorMessage($e->getMessage(), $e->getCode());
        }
    }

    /**
     * -------------------------------------------------
     * Checkout Confirm
     * -------------------------------------------------
     */
    public function confirm(
        Request                $request,
        CheckoutConfirmService $checkoutService,
        PaymentService         $paymentService,
        RazorpayService        $razorpayService
    )
    {
        $data = $request->validate([
            'payment_method' => 'required|in:razorpay',
            'charges' => 'required|array|min:1',
        ]);

        $cart = Cart::latest()->where('buyer_id', $request->user()->id)
            ->where('status', 'active')
            ->with(['cartItems.productListingPackage.productListingItem.productListing'])
            ->firstOrFail();

        // 1️⃣ Create order (lock stock)
        $order = $checkoutService->confirm($cart, $data['charges'], $request->payment_method);

        // 2️⃣ Create payment record
        $payment = $paymentService->initiate([
            'source_type' => Order::class,
            'source_id' => $order->id,
            'source_code' => $order->order_number,
            'user_id' => $request->user()->id,
            'amount' => $order->total_amount,
            'net_amount' => $order->total_amount,
            'payment_type' => 'checkout',
            'payment_method' => $request->payment_method,
        ]);

        // 3️⃣ Create Razorpay order
        $gateway = $razorpayService->createRazorpayOrder(
            $payment->payment_code,
            $payment->amount,
            AppSetting::getOrCreate()?->currency ?? 'INR'
        );

        $paymentService->attachGatewayOrder($payment, $gateway);

        // 4️⃣ Generate SIGNED payment URL (STANDARD)
        $paymentUrl = URL::temporarySignedRoute(
            'payment.page',
            now()->addMinutes(15),
            ['payment_code' => $payment->payment_code]
        );


        //  Log Activity
        logActivity(
            'checkout_payment_initiated',
            request()->user() ?? null,
            get_class($payment),
            $payment->id,
            $payment->payment_code,
            [
                'payment_code' => $payment->payment_code,
                'gateway_order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]
        );


        return $this->successResponse(
            'Proceed to payment',
            [
                'payment_code' => $payment->payment_code,
                'payment_url' => $paymentUrl,
            ],
            201
        );
    }


    // ORGINAL CODE DELETED
    // public function confirm(
    //     Request $request,
    //     CheckoutConfirmService $service
    // ) {
    //     try {
    //         $data = $request->validate([
    //             'payment_method' => 'required|in:razorpay,manual',

    //             // ✅ charges must come from preview
    //             'charges' => 'required|array|min:1',
    //             'charges.*.charge_type' => 'required|string',
    //             'charges.*.charge_name' => 'required|string',
    //             'charges.*.taxable_amount' => 'required|numeric',
    //             'charges.*.tax_amount' => 'required|numeric',
    //             'charges.*.total_amount' => 'required|numeric',
    //         ]);

    //         $paymentMethod = $data['payment_method'];

    //         $cart = Cart::where('buyer_id', $request->user()->id)
    //             ->where('status', 'active')
    //             ->with([
    //                 'cartItems.productListingPackage.productListingItem.productListing'
    //             ])
    //             ->firstOrFail();

    //         // ✅ CORRECT service call
    //         $order = $service->confirm(
    //             $cart,
    //             $data['charges'],
    //             $paymentMethod
    //         );

    //         return $this->successResponse(
    //             __('messages.success_messages.order_created') . "\n" . __('messages.success_messages.proceed_to_payment'),
    //             $order,
    //             201
    //         );
    //     } catch (Exception $e) {
    //         return $this->showErrorMessage($e->getMessage(), $e->getCode());
    //     }
    // }
}
