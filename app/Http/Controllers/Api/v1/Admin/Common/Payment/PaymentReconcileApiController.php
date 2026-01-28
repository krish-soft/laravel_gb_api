<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Payment;

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\Common\Payment\Payment;
use App\Services\Common\Payment\PaymentReconciliationService;

class PaymentReconcileApiController extends ApiResponseWithAdminAuthController
{
    //

    public function reconcile(
        string  $payment_code,
        PaymentReconciliationService $service
    ) {
        $payment = Payment::where('payment_code', $payment_code)
            ->firstOrFail();

        // ❌ Do not reconcile finalized payments
        if ($payment->is_final || $payment->status === PaymentStatusEnum::PAID->value) {
            logActivity(
                'payment_reconcile_noop',
                request()->user(),
                Payment::class,
                $payment->id,
                $payment->payment_code,
                [
                    'reason' => 'Payment already final',
                    'status' => $payment->status,
                ]
            );

            // return response()->json([
            //     'message' => 'Payment already finalized',
            //     'status' => $payment->status,
            // ]);

            return $this->showErrorMessage(__('messages.error_messages.payment_already_finalized'), 200);
        }

        $oldStatus = $payment->status;

        // ✅ Perform reconciliation (THIS UPDATES DB)
        $service->reconcile($payment);

        $payment->refresh();
        $newStatus = $payment->status;

        // ✅ Log reconciliation with before/after
        logActivity(
            'payment_reconciled_manual',
            request()->user(),
            Payment::class,
            $payment->id,
            $payment->payment_code,
            [
                'gateway' => $payment->gateway,
                'gateway_order_id' => $payment->gateway_order_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed' => $oldStatus !== $newStatus,
            ]
        );

        $message = $oldStatus !== $newStatus
            ? 'Payment status updated'
            : 'Payment checked, no status change.' . ', old_status : ' . $oldStatus . ' ,new_status : ' . $newStatus;

        return $this->showSuccessMessage($message, 200);
    }


    //    public function reconcile(
    //        string $payment_code,
    //        PaymentReconciliationService $service
    //    ) {
    //        $payment = Payment::where('payment_code', $payment_code)
    //            ->firstOrFail();
    //
    //        $service->reconcile($payment);
    //
    //        // 9️⃣ Log Activity
    //        logActivity(
    //            'razorpay_webhook_received',
    //            $payment->user() ?? null,
    //            get_class($payment),
    //            $payment->id,
    //            $payment->payment_code,
    //            [
    //                'payment_code' => $payment->payment_code,
    //                'gateway_order_id' => $payment->gateway_order_id,
    //                'amount' => $payment->amount,
    //                'currency' => $payment->currency,
    //            ]
    //        );
    //
    //        return response()->json([
    //            'message' => 'Payment reconciled',
    //            'status' => $payment->fresh()->status,
    //        ]);
    //    }
}
