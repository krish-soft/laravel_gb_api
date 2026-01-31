<?php

namespace App\Http\Controllers\Api\v1\User\Buyer;

use App\Enum\Common\ActionCodeEnum;
use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\Cart;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Payment\Payment;
use App\Models\Master\Setting\MstAppSetting;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Services\Buyer\Checkout\CheckoutConfirmService;
use App\Services\Buyer\Checkout\CheckoutPreviewService;
use App\Services\Common\Payment\Gateways\RazorpayService;
use App\Services\Common\Payment\Handlers\OrderPaymentHandler;
use App\Services\Common\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class CheckoutApiController extends ApiResponseWithAuthController
{
    /**
     * -------------------------------------------------
     * Checkout Preview
     * -------------------------------------------------
     */
    public function preview(
        Request                $request,
        CheckoutPreviewService $service,
    ) {

        $request->validate([
            'is_buyer_pickup' => 'sometimes|boolean', // base on this delivery charge will be calculated
        ]);

        $isBuyerPickup = $request->input('is_buyer_pickup') ?? false;

        try {
            $cart = Cart::with('buyer', 'cartItems')->where('buyer_id', $request->user()->id)
                ->where('status', 'active')
                ->with([
                    'cartItems.productListingPackage.productListingItem.productListing'
                ])
                ->firstOrFail();

            // 

            return $this->successResponse(
                __('messages.success_messages.checkout_preview'),
                $service->preview($cart, $isBuyerPickup),
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
        RazorpayService        $razorpayService,
        OrderPaymentHandler   $orderPaymentHandler
    ) {
        $user = $request->user();

        if (!$user->isBuyer()) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_action'),
                403
            );
        }

        $data = $request->validate([
            'cart_id' => 'required|exists:carts,id',
            'payment_method' => 'required|in:razorpay', // manual only via admin
            'fulfillment_location_id' => 'required|exists:fulfillment_locations,id',
            // 'charges'        => 'required|array|min:1',
            'is_buyer_pickup' => 'required|boolean',
        ]);

        $fulfillmentLocationId = $data['fulfillment_location_id'] ?? null;


        $fulfillmentLocation = FulfillmentLocation::findOrFail($fulfillmentLocationId);
        if ($fulfillmentLocation->user_id !== $user->id) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_action'),
                400
            );
        }


        $cart = Cart::where('id', $data['cart_id'])
            ->where('buyer_id', $user->id)
            ->where('status', 'active')
            ->with(['cartItems.productListingPackage.productListingItem.productListing'])
            ->latest()
            ->firstOrFail();



        $previewData = app(CheckoutPreviewService::class)->preview($cart, $data['is_buyer_pickup']);
        $charges = $previewData['charges'];

        // if not found then give error 
        if (!is_array($charges) || count($charges) === 0) {
            throw new RuntimeException(__('messages.error_messages.invalid_checkout_charges'));
        }



        $totalAmount = $cart->getTotalCartItemAmount();
        foreach ($charges as $charge) {
            $totalAmount += $charge['total_amount'];
        }

        DB::beginTransaction();

        try {

            /**
             * 1️⃣ Create Order
             */
            $order = $checkoutService->confirm(
                $cart,
                $charges, //$data['charges'],
                $data['payment_method'],
                $fulfillmentLocationId
            );

            // 
            logActivity(
                'checkout_order_created',
                $user,
                Order::class,
                $order->id,
                $order->order_number,
                ['amount' => $order->total_amount]
            );

            /**
             * 2️⃣ Create Payment (INITIATED)
             */
            $payment = $paymentService->initiate([
                'source_type'   => Order::class,
                'source_id'     => $order->id,
                'source_code'   => $order->order_number,
                'user_id'       => $user->id,
                'amount'        => $order->total_amount,
                'tax_amount'    => $order->tax_amount,
                'net_amount'    => $order->total_amount,
                'payment_type'  => 'checkout',
                'payment_method' => $data['payment_method'],
            ]);

            logActivity(
                'payment_initiated',
                $user,
                Payment::class,
                $payment->id,
                $payment->payment_code,
                [
                    'method' => $payment->payment_method,
                    'amount' => $payment->amount,
                ]
            );

            /**
             * ------------------------------------
             *  MANUAL (SYNC)
             * ------------------------------------
             */
            if (
                MstPaymentSetting::payInMode() === PaymentMethodEnum::MANUAL
            ) {

                $payment->markPaid('MANUAL');

                $orderPaymentHandler->onSuccess($payment);

                logActivity(
                    'payment_completed_manual',
                    $user,
                    Payment::class,
                    $payment->id,
                    $payment->payment_code,
                    ['amount' => $payment->amount]
                );

                DB::commit();

                return $this->successResponse(
                    __('messages.success_messages.order_created'),
                    ['order_code' => $order->order_number],
                    201
                );
            }

            /**
             * ------------------------------------
             * RAZORPAY (ASYNC)
             * ------------------------------------
             */
            if ($data['payment_method'] !== PaymentMethodEnum::RAZORPAY->value) {
                throw new RuntimeException(__('messages.error_messages.invalid_payment_method'));
            }

            $gateway = $razorpayService->createRazorpayOrder(
                $payment->payment_code,
                $payment->amount,
                MstFinanceSetting::currency()
            );

            $paymentService->attachGatewayOrder($payment, $gateway);

            $paymentUrl = URL::temporarySignedRoute(
                'payment.page',
                now()->addMinutes(15),
                ['payment_code' => $payment->payment_code]
            );

            logActivity(
                'razorpay_payment_initiated',
                $user,
                Payment::class,
                $payment->id,
                $payment->payment_code,
                [
                    'gateway_order_id' => $payment->gateway_order_id,
                    'amount' => $payment->amount,
                ]
            );

            DB::commit();

            return $this->successResponse(
                __('messages.success_messages.proceed_to_payment'),
                [
                    'payment_code' => $payment->payment_code,
                    'payment_url'  => $paymentUrl,
                ],
                201,
                ActionCodeEnum::PAYMENT_RAZORPAY
            );
        } catch (\Throwable $e) {

            DB::rollBack();

            if (isset($payment)) {
                $payment->markFailed('checkout_error', $e->getMessage());

                logActivity(
                    'payment_failed',
                    $user,
                    Payment::class,
                    $payment->id,
                    $payment->payment_code,
                    ['reason' => $e->getMessage()]
                );
            }

            if (isset($order)) {
                app(\App\Services\Buyer\Checkout\CheckoutRevertService::class)
                    ->revert($order);

                logActivity(
                    'checkout_reverted',
                    $user,
                    Order::class,
                    $order->id,
                    $order->order_number,
                    []
                );
            }

            throw $e;
        }
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
