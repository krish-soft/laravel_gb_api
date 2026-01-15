<?php


namespace App\Services\Common\Payment;

use App\Enum\Common\Payment\PaymentGatewayEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Common\Payment;
use App\Services\Common\Payment\Gateways\RazorpayService;
use Illuminate\Support\Facades\DB;

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

        $razorpay = app(RazorpayService::class);
        $finalizer = app(PaymentFinalizerService::class);

        $status = $razorpay->getFinalStatusByOrder(
            $payment->gateway_order_id
        );

        DB::transaction(function () use ($payment, $status, $finalizer) {

            if ($status === PaymentStatusEnum::PAID->value) {
                $payment->markPaid(
                    'manual-reconcile'
                );
                $finalizer->handleSuccess($payment);
            } else {
                $payment->markFailed(
                    'manual-failed',
                    'Reconciled as failed'
                );
                $finalizer->handleFailure($payment, 'manual');
            }
        });

        // Log Activity
        logActivity(
            'razorpay_payment_reconciled',
            request()?->user() ?? null,   // Sanctum / Web / null-safe
            get_class($payment),
            $payment->id,
            $payment->payment_code,
            [
                'gateway_order_id' => $payment->gateway_order_id,
                'payment_code' => $payment->payment_code,
                'new_status' => $payment->status,
                'amount' => $payment->amount,
            ]
        );
    }
}
