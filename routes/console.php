<?php

use App\Enum\Common\Payment\PaymentMethodEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Enum\Common\Shipment\DriverShipmentStatusEnum;
use App\Enum\Common\Shipment\ShipmentStatusEnum;
use App\Models\Common\Payment\Payment;
use App\Models\Common\Payment\Payout;
use App\Models\Delivery\DriverShipment;
use App\Models\Master\Setting\MstPaymentSetting;
use App\Services\Common\Payment\Handlers\PayoutHandler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Schedule a task to finalize pending payments
// Its only for razorpay payments where gateway_order_id is not yet attached

if (MstPaymentSetting::payInMode() == PaymentMethodEnum::RAZORPAY->value) {

    // 1️⃣ Reconcile Razorpay payments
    Schedule::call(function () {

        $reconciler = app(
            \App\Services\Common\Payment\PaymentReconciliationService::class
        );

        Payment::whereIn('status', [
            PaymentStatusEnum::INITIATED->value,
            PaymentStatusEnum::PROCESSING->value
        ])
            ->whereNotNull('gateway_order_id')
            ->where('created_at', '<', now()->subMinutes(10))
            ->each(function (Payment $payment) use ($reconciler) {

                if ($payment->is_final) {
                    return;
                }

                $reconciler->reconcile($payment);
            });
    })->everyFiveMinutes();



    // 2️⃣ Timeout payments (FINAL FAILURE)
    Schedule::call(function () {

        $finalizer = app(\App\Services\Common\Payment\PaymentFinalizerService::class);

        Payment::whereIn('status', [PaymentStatusEnum::INITIATED->value, PaymentStatusEnum::PROCESSING->value])
            ->whereNotNull('gateway_order_id')
            ->where('created_at', '<', now()->subMinutes(25))
            ->each(function (Payment $payment) use ($finalizer) {

                if ($payment->is_final) {
                    return;
                }

                $payment->markFailed('timeout', 'Payment not completed');
                $finalizer->handleFailure($payment, 'timeout');
            });
    })->everyThirtyMinutes();
}


/**
 *  Payout timeout handler
 */

if (MstPaymentSetting::payOutMode() == PaymentMethodEnum::RAZORPAY->value) {

    // Payout timeout handler
    Schedule::call(function () {

        $handler = app(PayoutHandler::class);

        Payout::whereIn('status', [PayoutStatusEnum::REQUESTED->value, PayoutStatusEnum::PROCESSING->value])
            ->where('created_at', '<', now()->subMinutes(30))
            ->each(function (Payout $payout) use ($handler) {

                // 🔒 Idempotency guard
                if (in_array($payout->status, [PayoutStatusEnum::PAID->value, PayoutStatusEnum::FAILED->value])) {
                    return;
                }

                $handler->onFailure(
                    $payout,
                    'Payout timeout (no response from Razorpay)'
                );
            });
    })->everyFiveMinutes();
}


// Check Driver Not accpet withhin 10 min then reset assigned job and look for other driver
Schedule::call(function () {

    $tenMinutesAgo = now()->subMinutes(10);

    DriverShipment::where('status', DriverShipmentStatusEnum::PENDING->value)
        ->where('assigned_at', '<=', $tenMinutesAgo)
        ->each(function ($driverShipment) {
            // Reset driver assignment

            // original Shipment 
            $driverShipment->shipment()->update([
                'status' => ShipmentStatusEnum::GROUPED->value,
            ]);

            // make cancelled this 
            $driverShipment->update([
                'status' => DriverShipmentStatusEnum::CANCELLED->value,
                'remarks' => 'Driver did not accept within 10 minutes, auto-cancelled.',
            ]);


            // Optionally, you can also log this event or notify someone
            // Log::info("Driver shipment ID {$driverShipment->id} has been reset due to no acceptance within 10 minutes.");
        });
})->everyTenMinutes();
