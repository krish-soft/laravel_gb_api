<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Services\Common\Payment\Gateways\RazorpayService;
use App\Services\Common\Payment\PaymentFinalizerService;
use App\Services\Common\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RazorpayWebhookController extends Controller
{

    public function handle(
        Request                 $request,
        RazorpayService         $razorpay,
        PaymentService          $paymentService,
        PaymentFinalizerService $finalizer
    ) {
        // 1️⃣ Verify webhook signature (MUST)
        $razorpay->verifyWebhook(
            $request->getContent(),
            $request->header('X-Razorpay-Signature')
        );

        $event = $request->input('event');

        // 2️⃣ Only handle captured payments
        if ($event !== 'payment.captured') {
            return response()->json(['ok' => true]);
        }

        // 3️⃣ Extract gateway data safely
        $gatewayOrderId = data_get(
            $request->all(),
            'payload.payment.entity.order_id'
        );

        $gatewayPaymentId = data_get(
            $request->all(),
            'payload.payment.entity.id'
        );

        $gatewayAmount = data_get(
            $request->all(),
            'payload.payment.entity.amount'
        );

        // 4️⃣ Invalid payload guard
        if (!$gatewayOrderId || !$gatewayPaymentId) {
            return response()->json(['ok' => true]);
        }

        // 5️⃣ Find payment
        $payment = $paymentService->findByGatewayOrder($gatewayOrderId);

        // 6️⃣ Idempotency guard
        if (
            $payment->is_final ||
            $payment->status !== PaymentStatusEnum::INITIATED->value
        ) {
            return response()->json(['ok' => true]);
        }

        // 7️⃣ Amount safety check (paise)
        if ((int)($payment->amount * 100) !== (int)$gatewayAmount) {
            // Do NOT mark paid if amount mismatches
            return response()->json(['ok' => true]);
        }

        // 8️⃣ Finalize payment + business logic
        DB::transaction(function () use (
            $payment,
            $gatewayPaymentId,
            $finalizer
        ) {
            $payment->markPaid($gatewayPaymentId);
            $finalizer->handleSuccess($payment);
        });

        // 9️⃣ Log Activity
        logActivity(
            'razorpay_webhook_received',
            $payment?->user ?? null,
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


        return response()->json(['ok' => true]);
    }


    //    public function handle(
    //        Request $request,
    //        RazorpayService $razorpay,
    //        PaymentService $paymentService,
    //        PaymentFinalizerService $finalizer
    //    ) {
    //        $razorpay->verifyWebhook(
    //            $request->getContent(),
    //            $request->header('X-Razorpay-Signature')
    //        );
    //
    //        if ($request->input('event') === 'payment.captured') {
    //
    //            $gatewayOrderId = data_get(
    //                $request->all(),
    //                'payload.payment.entity.order_id'
    //            );
    //
    //            $gatewayPaymentId = data_get(
    //                $request->all(),
    //                'payload.payment.entity.id'
    //            );
    //
    //            $payment = $paymentService->findByGatewayOrder($gatewayOrderId);
    //
    //            if ($payment->is_final) {
    //                return response()->json(['ok' => true]);
    //            }
    //
    //            DB::transaction(function () use (
    //                $payment,
    //                $gatewayPaymentId,
    //                $finalizer
    //            ) {
    //                $payment->markPaid($gatewayPaymentId);
    //                $finalizer->handleSuccess($payment);
    //            });
    //        }
    //
    //        return response()->json(['ok' => true]);
    //    }
}
