<?php

namespace App\Services\Common\Payment;

use App\Models\Buyer\Order\Order;
use App\Models\Common\Payment\Payment;
use App\Services\Common\Payment\Handlers\OrderPaymentHandler;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaymentFinalizerService
{
    public function handleSuccess(Payment $payment): void
    {
        // try {
        match ($payment->source_type) {
            Order::class => app(OrderPaymentHandler::class)->onSuccess($payment),

            default => throw new RuntimeException('Unsupported payment source'),
        };
        // } catch (\Exception $e) {
        //     // Log exception but do not block payment finalization
        //     \Log::error('Error in payment finalization handler: ' . $e->getMessage());
        //     Log::error($e);
        // }
    }

    public function handleFailure(
        Payment $payment,
        string  $reason = 'failed'
    ): void {
        match ($payment->source_type) {
            Order::class => app(OrderPaymentHandler::class)->onFailure($payment, $reason),

            default =>
            throw new RuntimeException('Unsupported payment source'),
        };
    }
}
