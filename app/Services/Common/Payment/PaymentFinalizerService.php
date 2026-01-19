<?php

namespace App\Services\Common\Payment;

use App\Models\Common\Payment\Payment;
use App\Services\Common\Payment\Handlers\OrderPaymentHandler;
use RuntimeException;

class PaymentFinalizerService
{
    public function handleSuccess(Payment $payment): void
    {
        match ($payment->source_type) {
            \App\Models\Buyer\Order\Order::class => app(OrderPaymentHandler::class)->onSuccess($payment),

            default => throw new RuntimeException('Unsupported payment source'),
        };
    }

    public function handleFailure(
        Payment $payment,
        string  $reason = 'failed'
    ): void
    {
        match ($payment->source_type) {
            \App\Models\Buyer\Order\Order::class => app(OrderPaymentHandler::class)->onFailure($payment, $reason),

            default =>
            throw new RuntimeException('Unsupported payment source'),
        };
    }
}
