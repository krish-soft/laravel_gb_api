<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Services\Common\Payment\Gateways\RazorpayService;
use App\Services\Common\Payment\PaymentFinalizerService;
use App\Services\Common\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RazorpayWebhookController extends Controller
{

    public function handle(
        Request                 $request,
        RazorpayService         $razorpay,
        PaymentService          $paymentService,
        PaymentFinalizerService $finalizer
    ) {
        // 1️⃣ Verify signature (MANDATORY)
        $razorpay->verifyWebhook(
            $request->getContent(),
            $request->header('X-Razorpay-Signature')
        );

        // 2️⃣ ONLY payment.captured is source of truth
        if ($request->input('event') !== 'payment.captured') {
            return response()->json(['ok' => true]);
        }

        // 3️⃣ Extract payload
        $payload = $request->all();

        $paymentEntity = data_get($payload, 'payload.payment.entity');

        $gatewayOrderId   = $paymentEntity['order_id'] ?? null;
        $gatewayPaymentId = $paymentEntity['id'] ?? null;
        $gatewayAmount    = $paymentEntity['amount'] ?? null;

        if (!$gatewayOrderId || !$gatewayPaymentId) {
            return response()->json(['ok' => true]);
        }

        // 4️⃣ Find local payment
        $payment = $paymentService->findByGatewayOrder($gatewayOrderId);

        // 5️⃣ Idempotency
        if (
            $payment->is_final ||
            $payment->status !== PaymentStatusEnum::INITIATED->value
        ) {
            return response()->json(['ok' => true]);
        }

        // 6️⃣ Amount check (paise)
        if ((int) ($payment->amount * 100) !== (int) $gatewayAmount) {
            return response()->json(['ok' => true]);
        }

        // 7️⃣ Prepare meta + paid_via
        $meta = [
            'gateway_payload' => $payload,
        ];

        $paidVia = $this->formatPaidVia($paymentEntity);

        // 8️⃣ Finalize payment
        DB::transaction(function () use (
            $payment,
            $gatewayPaymentId,
            $finalizer,
            $meta,
            $paidVia
        ) {
            $payment->markPaid(
                $gatewayPaymentId,
                array_merge($meta, ['paid_via' => $paidVia])
            );

            $finalizer->handleSuccess($payment);
        });

        // 9️⃣ Clear retry cache
        Cache::forget("razorpay:attempts:{$gatewayOrderId}");

        // 🔟 Log
        logActivity(
            'razorpay_webhook_received',
            $payment->user ?? null,
            get_class($payment),
            $payment->id,
            $payment->payment_code,
            [
                'gateway_order_id' => $gatewayOrderId,
                'gateway_payment_id' => $gatewayPaymentId,
                'amount' => $payment->amount,
                'paid_via' => $paidVia,
            ]
        );

        return response()->json(['ok' => true]);
    }

    private function formatPaidVia(array $payment): string
    {
        return match ($payment['method'] ?? null) {
            'upi' => 'UPI (' . ($payment['vpa'] ?? 'UPI') . ')',

            'card' => 'Card (' .
                ucfirst($payment['card']['network'] ?? 'Card') .
                ' **** ' .
                ($payment['card']['last4'] ?? 'XXXX') .
                ')',

            'netbanking' => 'NetBanking (' . strtoupper($payment['bank'] ?? 'Bank') . ')',

            'wallet' => 'Wallet (' . ucfirst($payment['wallet'] ?? 'Wallet') . ')',

            default => 'Online Payment',
        };
    }


    ## OLD 
    // public function handle(
    //     Request                 $request,
    //     RazorpayService         $razorpay,
    //     PaymentService          $paymentService,
    //     PaymentFinalizerService $finalizer
    // ) {
    //     // 1️⃣ Verify webhook signature (MUST)
    //     $razorpay->verifyWebhook(
    //         $request->getContent(),
    //         $request->header('X-Razorpay-Signature')
    //     );

    //     $event = $request->input('event');

    //     // 2️⃣ Only handle captured payments
    //     if ($event !== 'payment.captured') {
    //         return response()->json(['ok' => true]);
    //     }

    //     // 3️⃣ Extract gateway data safely
    //     $gatewayOrderId = data_get(
    //         $request->all(),
    //         'payload.payment.entity.order_id'
    //     );

    //     $gatewayPaymentId = data_get(
    //         $request->all(),
    //         'payload.payment.entity.id'
    //     );

    //     $gatewayAmount = data_get(
    //         $request->all(),
    //         'payload.payment.entity.amount'
    //     );

    //     // 4️⃣ Invalid payload guard
    //     if (!$gatewayOrderId || !$gatewayPaymentId) {
    //         return response()->json(['ok' => true]);
    //     }

    //     // 5️⃣ Find payment
    //     $payment = $paymentService->findByGatewayOrder($gatewayOrderId);

    //     // 6️⃣ Idempotency guard
    //     if (
    //         $payment->is_final ||
    //         $payment->status !== PaymentStatusEnum::INITIATED->value
    //     ) {
    //         return response()->json(['ok' => true]);
    //     }

    //     // 7️⃣ Amount safety check (paise)
    //     if ((int)($payment->amount * 100) !== (int)$gatewayAmount) {
    //         // Do NOT mark paid if amount mismatches
    //         return response()->json(['ok' => true]);
    //     }

    //     // 8️⃣ Finalize payment + business logic
    //     DB::transaction(function () use (
    //         $payment,
    //         $gatewayPaymentId,
    //         $finalizer
    //     ) {
    //         $payment->markPaid($gatewayPaymentId);
    //         $finalizer->handleSuccess($payment);
    //     });

    //     // 9️⃣ Log Activity
    //     logActivity(
    //         'razorpay_webhook_received',
    //         $payment?->user ?? null,
    //         get_class($payment),
    //         $payment->id,
    //         $payment->payment_code,
    //         [
    //             'payment_code' => $payment->payment_code,
    //             'gateway_order_id' => $payment->gateway_order_id,
    //             'amount' => $payment->amount,
    //             'currency' => $payment->currency,
    //         ]
    //     );


    //     return response()->json(['ok' => true]);
    // }


}
