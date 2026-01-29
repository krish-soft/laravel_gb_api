<?php


namespace App\Services\Common\Payment;

use App\Enum\Common\Payment\PaymentGatewayEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Common\Payment\Payment;
use App\Services\Common\Payment\Gateways\RazorpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{


    public function reconcile(Payment $payment): void
    {
        if ($payment->is_final) {
            return;
        }

        if ($payment->gateway !== PaymentGatewayEnum::RAZORPAY->value) {
            return;
        }

        $razorpay  = app(RazorpayService::class);
        $finalizer = app(PaymentFinalizerService::class);

        $result = $razorpay->getFinalStatusByOrder(
            $payment->gateway_order_id,
            $payment->amount_paise ?? null
        );

        // Log::info(json_encode([
        //     'gateway_order_id' => $payment->gateway_order_id,
        //     'payments' => $result['gateway_payload'],
        //     'paid_via' => $result['paid_via'] ?? null,
        //     'status' => $result['status'],
        // ], JSON_PRETTY_PRINT));

        DB::transaction(function () use ($payment, $result, $finalizer) {


            // 🔐 Store full Razorpay payload ALWAYS
            $payment->update([
                'meta' => $result['gateway_payload'] ?? null,
                'paid_via' => $result['paid_via'] ?? null, // card / upi / bank
            ]);

            if ($result['status'] === PaymentStatusEnum::PAID->value) {

                $payment->markPaid('manual-reconcile');

                $finalizer->handleSuccess($payment);

                // Log::info('Marking payment as PAID via reconciliation: ' . $payment->payment_code);
            } elseif ($result['status'] === PaymentStatusEnum::FAILED->value) {

                $payment->markFailed(
                    'manual-failed',
                    'Reconciled as failed'
                );

                $finalizer->handleFailure($payment, 'manual');
            }

            // PENDING → do nothing (retry later)
        });

        // Log Activity
        logActivity(
            'razorpay_payment_reconciled',
            request()?->user(),
            get_class($payment),
            $payment->id,
            $payment->payment_code,
            [
                'gateway_order_id' => $payment->gateway_order_id,
                'status' => $result['status'],
                'gateway_payment_id' => $result['payment']['id'] ?? null,
                'method' => $result['payment']['method'] ?? null,
                'amount' => $payment->amount,
            ]
        );
    }


    // public function reconcile(Payment $payment): void
    // {


    //     if ($payment->is_final) {
    //         return;
    //     }

    //     if ($payment->gateway !== PaymentGatewayEnum::RAZORPAY->value) {
    //         return;
    //     }

    //     $razorpay = app(RazorpayService::class);
    //     $finalizer = app(PaymentFinalizerService::class);

    //     $status = $razorpay->getFinalStatusByOrder(
    //         $payment->gateway_order_id
    //     );

    //     DB::transaction(function () use ($payment, $status, $finalizer) {

    //         if ($status === PaymentStatusEnum::PAID->value) {
    //             $payment->markPaid(
    //                 'manual-reconcile'
    //             );
    //             $finalizer->handleSuccess($payment);
    //         } else {
    //             $payment->markFailed(
    //                 'manual-failed',
    //                 'Reconciled as failed'
    //             );
    //             $finalizer->handleFailure($payment, 'manual');
    //         }
    //     });

    //     // Log Activity
    //     logActivity(
    //         'razorpay_payment_reconciled',
    //         request()?->user() ?? null,   // Sanctum / Web / null-safe
    //         get_class($payment),
    //         $payment->id,
    //         $payment->payment_code,
    //         [
    //             'gateway_order_id' => $payment->gateway_order_id,
    //             'payment_code' => $payment->payment_code,
    //             'new_status' => $payment->status,
    //             'amount' => $payment->amount,
    //         ]
    //     );
    // }
}
