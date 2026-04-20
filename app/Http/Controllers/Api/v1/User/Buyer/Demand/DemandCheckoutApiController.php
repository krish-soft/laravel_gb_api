<?php

namespace App\Http\Controllers\Api\v1\User\Buyer\Demand;

use App\Enum\Common\ActionCodeEnum;
use App\Enum\Common\Legal\KycStatusEnum;
use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Http\Controllers\ApiResponseWithAuthController;
use App\Models\Buyer\Cart\DemandCart;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\Order;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Payment\Payment;
use App\Models\Master\Setting\MstFinanceSetting;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Services\Buyer\Checkout\Demand\DemandCheckoutConfirmService;
use App\Services\Buyer\Checkout\Demand\DemandCheckoutPreviewService;
use App\Services\Common\Payment\Gateways\RazorpayService;
use App\Services\Common\Payment\Handlers\DemandOrderPaymentHandler;
use App\Services\Common\Payment\PaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class DemandCheckoutApiController extends ApiResponseWithAuthController
{
    /**
     * -------------------------------------------------
     * Checkout Preview
     * -------------------------------------------------
     */
    public function preview(
        Request $request,
        DemandCheckoutPreviewService $service,
    ) {

        $request->validate([
            'is_buyer_pickup' => 'sometimes|boolean', // base on this delivery charge will be calculated
        ]);

        $isBuyerPickup = $request->input('is_buyer_pickup') ?? false;

        try {
            $cart = DemandCart::with('buyer', 'demandCartItems')->where('buyer_id', $request->user()->id)
                ->where('status', 'active')
                ->with([
                    'demandCartItems.product',
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
        Request $request,
        DemandCheckoutConfirmService $checkoutService,
        PaymentService $paymentService,
        RazorpayService $razorpayService,
        DemandOrderPaymentHandler $orderPaymentHandler
    ) {
        $user = $request->user();

        // if (!$user->isBuyer()) {
        //     return $this->showErrorMessage(
        //         __('messages.error_messages.unauthorized_action'),
        //         403
        //     );
        // }

        $data = $request->validate([
            'demand_cart_id' => 'required|exists:demand_carts,id',
            'payment_method' => 'required|in:razorpay', // manual only via admin
            'fulfillment_location_id' => 'required|exists:fulfillment_locations,id',
            'is_buyer_pickup' => 'required|boolean',
        ]);

        $cart = DemandCart::where('id', $data['demand_cart_id'])
            ->where('buyer_id', $user->id)
            ->where('status', 'active')
            ->with(['demandCartItems.product'])
            ->latest()
            ->first();

        if (! $cart) {
            return $this->showErrorMessage(
                __('messages.error_messages.cart_not_active_or_converted'),
                404
            );
        }

        $fulfillmentLocationId = $data['fulfillment_location_id'] ?? null;

        $fulfillmentLocation = FulfillmentLocation::findOrFail($fulfillmentLocationId);

        if ($fulfillmentLocation->user_id !== $user->id) {
            return $this->showErrorMessage(
                __('messages.error_messages.unauthorized_action'.' - Invalid fulfillment location'),
                400
            );
        }

        if ($fulfillmentLocation->status !== KycStatusEnum::APPROVED->value) {
            return $this->showErrorMessage(
                __('messages.error_messages.invalid_fulfillment_location'),
                400
            );
        }

        // Get preview data again to ensure charges are correct
        $cartMeta = $cart->meta ?? [];
        $canUseCredit = $cartMeta['can_checkout_with_credit'] ?? false;
        $creditBalanceToUse = $cartMeta['credit_balance_to_use'] ?? 0;

        // also check  can checkour and message
        // if (!($cartMeta['can_checkout'] ?? false)) {
        //     return $this->showErrorMessage(
        //         $cartMeta['message_not_checkout'] ?? __('messages.error_messages.invalid_checkout_charges'),
        //         400
        //     );
        // }

        $charges = $cartMeta['charges'] ?? null;
        // if not found then give error
        if (! is_array($charges) || count($charges) === 0) {
            throw new RuntimeException(__('messages.error_messages.invalid_checkout_charges'));
        }

        // $previewData = app(CheckoutPreviewService::class)->preview($cart, $data['is_buyer_pickup']);
        // $charges = $previewData['charges'];

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
                $charges, // $data['charges'],
                $data['payment_method'],
                $fulfillmentLocation,
                $cartMeta
            );

            //
            logActivity(
                'checkout_demand_order_created',
                $user,
                DemandOrder::class,
                $order->id,
                $order->order_number,
                [
                    'order_amount' => $order->total_amount,
                    'credit_amount' => $order->credit_amount,
                ]
            );

            /**
             * 2️⃣ Create Payment (INITIATED)
             */
            $payment = $paymentService->initiate([
                'source_type' => DemandOrder::class,
                'source_id' => $order->id,
                'source_code' => $order->order_number,
                'user_id' => $user->id,
                'order_amount' => $order->total_amount,
                'payment_type' => 'checkout',
                'payment_method' => $data['payment_method'],
                'credit_amount' => $canUseCredit ? $creditBalanceToUse : 0,
            ]);

            //
            logActivity(
                'payment_initiated',
                $user,
                Payment::class,
                $payment->id,
                $payment->payment_code,
                [
                    'payment_code' => $payment->payment_code,
                    'method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                ],
                // RELATED
                DemandOrder::class,
                $order->id
            );

            /**
             * ------------------------------------
             *  MANUAL (SYNC)
             * ------------------------------------
             */
            if (
                MstPaymentSetting::payInMode() === PaymentMethodEnum::MANUAL->value || // Testing only - in production we will not allow manual payment method for buyer checkout. It will be only for admin to mark payment as paid when they receive payment outside system.
                // check credit balance enough then can mark as paid directly without manual payment
                ($payment->amount <= 0 && $payment->credit_amount > 0)
            ) {

                $payment->gateway = ($canUseCredit && $order->credit_amount >= $order->total_amount) ? 'credit_balance' : 'manual';
                $payment->save();

                $payment->markPaid('MANUAL');

                $orderPaymentHandler->onSuccess($payment);

                logActivity(
                    'payment_completed_manual',
                    $user,
                    Payment::class,
                    $payment->id,
                    $payment->payment_code,
                    ['amount' => $payment->amount],
                    // RELATED
                    DemandOrder::class,
                    $order->id
                );

                DB::commit();

                return $this->successResponse(
                    __('messages.success_messages.order_created'),
                    [
                        'is_razorpay' => true,
                        'order_number' => $order->order_number,
                    ],
                    201
                );
            }

            // return "Testing no razorpay";

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
            // Store this payment URL to paymnent so can retrieve later if needed
            $payment->payment_url = $paymentUrl;
            $payment->save();

            logActivity(
                'razorpay_payment_initiated',
                $user,
                Payment::class,
                $payment->id,
                $payment->payment_code,
                [
                    'gateway_order_id' => $payment->gateway_order_id,
                    'amount' => $payment->amount,
                ],
                // RELATED
                DemandOrder::class,
                $order->id
            );

            DB::commit();

            return $this->successResponse(
                __('messages.success_messages.proceed_to_payment'),
                [
                    'is_razorpay' => true,
                    'order_number' => $order->order_number,
                    'payment_code' => $payment->payment_code,
                    'payment_url' => $paymentUrl,
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
                    ['reason' => $e->getMessage()],
                    // RELATED
                    DemandOrder::class,
                    $order->id
                );
            }

            // No revert needed here as order is only created in DB and not yet confirmed or processed.
            // If needed, can add order status and mark as cancelled here.

            throw $e;
        }
    }
}
