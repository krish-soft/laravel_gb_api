<?php

namespace App\Http\Controllers\Web\Payment;

use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Common\Payment\Payment;
use App\Services\Common\Payment\Gateways\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

class PaymentPageController extends Controller
{

    public function pay(
        Request         $request,
        string          $payment_code,
        RazorpayService $razorpay
    ) {
        $payment = Payment::where('payment_code', $payment_code)
            ->where('status', PaymentStatusEnum::INITIATED->value)
            ->firstOrFail();

        // Already finalized
        if ($payment->is_final) {
            abort(410, 'Payment already completed');
        }

        // Expired payment link (WORKS)
        if ($payment->created_at->addMinutes(15)->isPast()) {
            abort(410, 'Payment expired');
        }

        // Zero / negative amount safety
        if ($payment->amount <= 0) {
            abort(400, 'Invalid payment amount');
        }

        // Gateway order must exist
        if (!$payment->gateway_order_id) {
            abort(400, 'Payment gateway not initialized');
        }

        // 🔁 ADD CACHE HERE
        $key = "razorpay:attempts:{$payment->gateway_order_id}";
        $attempt = Cache::get($key, ['attempts' => 0]);
        $attempt['attempts']++;
        $attempt['last_attempt_at'] = now();
        Cache::put($key, $attempt, now()->addMinutes(15));


        $checkout = $razorpay->checkoutPayload([
            'gateway_order_id' => $payment->gateway_order_id,
            'amount' => (int)($payment->amount * 100), // paise
            'currency' => $payment->currency ?? 'INR',
            'name' => optional($payment->user)->name,
            'email' => optional($payment->user)->email,
            'contact' => optional($payment->user)->phone_number,
            'description' => 'Payment ' . $payment->payment_code,
        ]);

        // Log Activity
        logActivity(
            'razorpay_web_payment_started',        // EVENT
            $payment->user ?? null,                 // ACTOR (who did it)
            get_class($payment), // SUBJECT TYPE (what was affected)
            $payment->id,              // SUBJECT ID
            $payment->payment_code,       // SUBJECT CODE (human readable)
            [
                'payment_code' => $payment->payment_code,
                'gateway_order_id' => $payment->gateway_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]
        );

        $statusUrl = URL::temporarySignedRoute(
            'payment.status',
            now()->addMinutes(15),
            ['payment' => $payment->payment_code]
        );


        return view('payments.checkoutWithResponse', compact('checkout', 'statusUrl'));
        // return view('payments.checkout', compact('checkout'));
    }


    ## WORKING - DELETED FOR NOW ##

    // public function pay(
    //     Request         $request,
    //     string          $payment_code,
    //     RazorpayService $razorpay
    // ) {
    //     $payment = Payment::where('payment_code', $payment_code)
    //         ->where('status', PaymentStatusEnum::INITIATED->value)
    //         ->firstOrFail();

    //     // Already finalized
    //     if ($payment->is_final) {
    //         abort(410, 'Payment already completed');
    //     }

    //     // Expired payment link (WORKS)
    //     if ($payment->created_at->addMinutes(15)->isPast()) {
    //         abort(410, 'Payment expired');
    //     }

    //     // Zero / negative amount safety
    //     if ($payment->amount <= 0) {
    //         abort(400, 'Invalid payment amount');
    //     }

    //     // Gateway order must exist
    //     if (!$payment->gateway_order_id) {
    //         abort(400, 'Payment gateway not initialized');
    //     }

    //     // 🔁 ADD CACHE HERE
    //     $key = "razorpay:attempts:{$payment->gateway_order_id}";
    //     $attempt = Cache::get($key, ['attempts' => 0]);
    //     $attempt['attempts']++;
    //     $attempt['last_attempt_at'] = now();
    //     Cache::put($key, $attempt, now()->addMinutes(15));


    //     $checkout = $razorpay->checkoutPayload([
    //         'gateway_order_id' => $payment->gateway_order_id,
    //         'amount' => (int)($payment->amount * 100), // paise
    //         'currency' => $payment->currency ?? 'INR',
    //         'name' => optional($payment->user)->name,
    //         'email' => optional($payment->user)->email,
    //         'contact' => optional($payment->user)->phone_number,
    //         'description' => 'Payment ' . $payment->payment_code,
    //     ]);

    //     // Log Activity
    //     logActivity(
    //         'razorpay_web_payment_started',        // EVENT
    //         $payment->user ?? null,                 // ACTOR (who did it)
    //         get_class($payment), // SUBJECT TYPE (what was affected)
    //         $payment->id,              // SUBJECT ID
    //         $payment->payment_code,       // SUBJECT CODE (human readable)
    //         [
    //             'payment_code' => $payment->payment_code,
    //             'gateway_order_id' => $payment->gateway_order_id,
    //             'amount' => $payment->amount,
    //             'currency' => $payment->currency,
    //         ]
    //     );

    //     return view('payments.checkout', compact('checkout'));
    // }


}
