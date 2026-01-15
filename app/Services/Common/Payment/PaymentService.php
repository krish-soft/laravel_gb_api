<?php

namespace App\Services\Common\Payment;

use App\Enum\Common\Payment\PaymentCurrencyEnum;
use App\Enum\Common\Payment\PaymentGatewayEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use App\Models\Common\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    /* -------------------------------------------------
     | Create payment record (COMMON ENTRY POINT)
     ------------------------------------------------- */
    public function initiate(array $data): Payment
    {
        return Payment::create([
            'payment_code' => $this->generatePaymentCode(),

            'source_type' => $data['source_type'],
            'source_id' => $data['source_id'],
            'source_code' => $data['source_code'] ?? null,

            'user_id' => $data['user_id'],

            'currency' => $data['currency'] ?? PaymentCurrencyEnum::INR->value,
            'amount' => $data['amount'],
            'tax_amount' => $data['tax_amount'] ?? 0,
            'fee_amount' => $data['fee_amount'] ?? 0,
            'net_amount' => $data['net_amount'] ?? $data['amount'],

            'payment_type' => $data['payment_type'],      // checkout | wallet_topUp
            'payment_method' => $data['payment_method'],  // razorpay
            'gateway' => PaymentGatewayEnum::RAZORPAY,

            'status' => PaymentStatusEnum::INITIATED->value,
            'meta' => $data['meta'] ?? [],
        ]);
    }

    /* -------------------------------------------------
     | Attach Razorpay order to payment
     ------------------------------------------------- */
    public function attachGatewayOrder(Payment $payment, array $gateway): Payment
    {
        $payment->update([
            'gateway_order_id' => $gateway['gateway_order_id'],
        ]);

        return $payment;
    }

    /* -------------------------------------------------
     | Mark payment PAID (Webhook only)
     ------------------------------------------------- */
    public function markPaid(
        Payment $payment,
        string  $gatewayPaymentId,
        array   $meta = []
    ): void
    {
        if ($payment->is_final) {
            return; // idempotent
        }

        DB::transaction(function () use ($payment, $gatewayPaymentId, $meta) {
            $payment->markPaid($gatewayPaymentId, $meta);
        });
    }

    /* -------------------------------------------------
     | Mark payment FAILED
     ------------------------------------------------- */
    public function markFailed(
        Payment $payment,
        ?string $code = null,
        ?string $reason = null
    ): void
    {
        if ($payment->is_final) {
            return;
        }

        $payment->markFailed($code, $reason);
    }

    /* -------------------------------------------------
     | Resolve payment by gateway_order_id (Webhook)
     ------------------------------------------------- */
    public function findByGatewayOrder(string $gatewayOrderId): Payment
    {
        $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();

        if (!$payment) {
            throw new RuntimeException('Payment not found');
        }

        return $payment;
    }

    /* -------------------------------------------------
     | Helpers
     ------------------------------------------------- */
    protected function generatePaymentCode(): string
    {   // Check if exist or not ??

        // return 'PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));\
        do {
            $code = 'PAY-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (Payment::where('payment_code', $code)->exists());
        return $code;
    }
}
