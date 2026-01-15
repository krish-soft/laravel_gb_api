<?php

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Common\Payment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Schedule a task to finalize pending payments
Schedule::call(function () {

    $finalizer = app(\App\Services\Common\Payment\PaymentFinalizerService::class);

    Payment::where('status', PaymentStatusEnum::INITIATED->value)
        ->whereNull('gateway_order_id') // 🔥 IMPORTANT GUARD
        ->where('created_at', '<', now()->subMinutes(20))
        ->each(function (Payment $payment) use ($finalizer) {

            if ($payment->is_final) {
                return;
            }

            $payment->markFailed('timeout', 'Payment not completed');
            $finalizer->handleFailure($payment, 'timeout');
        });

})->everyMinute();


Schedule::call(function () {

    $reconciler = app(
        \App\Services\Common\Payment\PaymentReconciliationService::class
    );

    Payment::where('status', PaymentStatusEnum::INITIATED->value)
        ->whereNotNull('gateway_order_id')
        ->where('created_at', '<', now()->subMinutes(10))
        ->each(function ($payment) use ($reconciler) {

            if ($payment->is_final) {
                return;
            }

            $reconciler->reconcile($payment);
        });

})->everyFiveMinutes();
