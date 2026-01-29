<?php

namespace App\Services\Common\Payment\Gateways;

use App\Enum\Common\Payment\PaymentCurrencyEnum;
use App\Enum\Common\Payment\PaymentStatusEnum;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use RuntimeException;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('razorpay.key_id'),
            config('razorpay.key_secret')
        );
    }

    /* =====================================================
     | CREATE RAZORPAY ORDER
     | Maps to payments.gateway_order_id
     ===================================================== */
    public function createRazorpayOrder(
        string $receipt,
        float  $amount,
        string $currency = PaymentCurrencyEnum::INR->value
    ): array {
        try {
            $order = $this->api->order->create([
                'receipt' => $receipt,
                'amount' => (int)round($amount * 100), // paise
                'currency' => $currency,
                'payment_capture' => 1,
            ]);

            // Log Activity
            logActivity(
                'razorpay_order_created',
                request()?->user() ?? null,   // Sanctum / Web / null-safe
                null,
                null,
                null,
                [
                    'receipt' => $receipt,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway_order_id' => $order['id']
                ]
            );

            return [
                'gateway_order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency'],
            ];
        } catch (\Exception $e) {
            Log::error('Razorpay create order failed', [
                'receipt' => $receipt,
                'error' => $e->getMessage(),
            ]);

            // Log Activity
            logActivity(
                'razorpay_order_creation_failed',
                request()?->user() ?? null,   // Sanctum / Web / null-safe
                null,
                null,
                null,
                [
                    'receipt' => $receipt,
                    'amount' => $amount,
                    'currency' => $currency,
                    'error' => $e->getMessage(),
                ]
            );

            throw new RuntimeException('Unable to initiate Razorpay order');
        }
    }

    /* =====================================================
     | FRONTEND CHECKOUT PAYLOAD
     ===================================================== */
    public function checkoutPayload(array $data): array
    {
        return [
            'key' => config('razorpay.key_id'),
            'order_id' => $data['gateway_order_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'INR',
            'name' => config('app.name'),
            'description' => $data['description'] ?? 'Payment',
            'prefill' => [
                'name' => $data['name'] ?? '',
                'email' => $data['email'] ?? '',
                'contact' => $data['phone_number'] ?? '',
            ],
            'theme' => [
                'color' => '#0d6efd',
            ],
        ];
    }

    /* =====================================================
     | VERIFY FRONTEND CALLBACK (UX ONLY)
     ===================================================== */
    public function verifyPaymentSignature(array $data): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $data['razorpay_order_id'],
                'razorpay_payment_id' => $data['razorpay_payment_id'],
                'razorpay_signature' => $data['razorpay_signature'],
            ]);
            return true;
        } catch (SignatureVerificationError $e) {
            Log::warning('Razorpay signature mismatch', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /* =====================================================
     | VERIFY WEBHOOK (FINAL AUTHORITY)
     ===================================================== */
    public function verifyWebhook(string $payload, string $signature): void
    {
        try {
            $this->api->utility->verifyWebhookSignature(
                $payload,
                $signature,
                config('razorpay.webhook_secret')
            );

            // Log Activity
            logActivity(
                'razorpay_webhook_verified',
                request()?->user() ?? null,   // Sanctum / Web / null-safe
                null,
                null,
                null,
                [
                    'signature_present' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Razorpay webhook verification failed', [
                'error' => $e->getMessage(),
            ]);

            // Log Activity
            logActivity(
                'razorpay_webhook_verification_failed',
                request()?->user() ?? null,   // Sanctum / Web / null-safe
                null,
                null,
                null,
                [
                    'signature_present' => true,
                    'error' => $e->getMessage(),

                ]
            );

            throw new RuntimeException('Invalid Razorpay webhook');
        }
    }

    /* =====================================================
     | FETCH SINGLE PAYMENT (ADMIN / DEBUG)
     ===================================================== */
    public function fetchPayment(string $gatewayPaymentId): array
    {
        // Log Activity
        logActivity(
            'razorpay_payment_fetch_attempted',
            request()?->user() ?? null,   // Sanctum / Web / null-safe
            null,
            null,
            null,
            [
                'gateway_order_id' => $gatewayPaymentId,
            ]
        );


        return $this->api
            ->payment
            ->fetch($gatewayPaymentId)
            ->toArray();
    }

    /* =====================================================
     | REFUND PAYMENT (FULL / PARTIAL)
     ===================================================== */
    public function refundPayment(
        string $gatewayPaymentId,
        ?float $amount = null
    ): array {
        $payload = [];

        if ($amount !== null) {
            $payload['amount'] = (int)round($amount * 100);
        }

        // Log Activity
        logActivity(
            'razorpay_payment_refund_attempted',
            request()?->user() ?? null,   // Sanctum / Web / null-safe
            null,
            null,
            null,
            [
                'gateway_order_id' => $gatewayPaymentId,
                'amount' => $amount,
            ]
        );

        return $this->api
            ->payment
            ->fetch($gatewayPaymentId)
            ->refund($payload)
            ->toArray();
    }

    /* =====================================================
     | FETCH ALL PAYMENTS FOR A RAZORPAY ORDER
     | (USED FOR RECONCILIATION)
     ===================================================== */
    public function fetchPaymentsByOrder(string $gatewayOrderId): array
    {
        return $this->api
            ->order
            ->fetch($gatewayOrderId)
            ->payments()
            ->toArray();
    }

    /* =====================================================
     | DETERMINE FINAL STATUS FROM RAZORPAY
     | SOURCE OF TRUTH FOR MANUAL RECONCILIATION
     ===================================================== */
    public function getFinalStatusByOrder(
        string $gatewayOrderId,
        ?int $expectedAmountPaise = null
    ): array {

        try {

            $payments = $this->fetchPaymentsByOrder($gatewayOrderId);

            $hasAttempt = false;
            $hasPending = false;
            $capturedPayment = null;

            foreach ($payments['items'] ?? [] as $payment) {
                $hasAttempt = true;

                // ✅ MONEY RECEIVED (FINAL)
                if (
                    $payment['status'] === PaymentStatusEnum::CAPTURED->value &&
                    (
                        $expectedAmountPaise === null ||
                        (int) $payment['amount'] === $expectedAmountPaise
                    )
                ) {
                    $capturedPayment = $payment;
                    break;
                }

                // ⏳ STILL IN PROGRESS
                if (in_array($payment['status'], [
                    PaymentStatusEnum::CREATED->value,
                    PaymentStatusEnum::AUTHORIZED->value,
                    PaymentStatusEnum::ATTEMPTED->value ?? null,
                ], true)) {
                    $hasPending = true;
                }
            }

            // ✅ PAID (highest priority)
            if ($capturedPayment) {
                return [
                    'status'          => PaymentStatusEnum::PAID->value,
                    'gateway_payload' => $payments,
                    'paid_via'        => $this->formatPaidVia($capturedPayment),
                ];
            }

            // ⏳ PENDING (never fail here)
            if ($hasPending || !$hasAttempt) {
                return [
                    'status'          => PaymentStatusEnum::PENDING->value,
                    'gateway_payload' => $payments,
                    'paid_via'        => null,
                ];
            }

            // ❌ FAILED (ONLY when attempts exist and none are pending or captured)
            return [
                'status'          => PaymentStatusEnum::FAILED->value,
                'gateway_payload' => $payments,
                'paid_via'        => null,
            ];
        } catch (\Throwable $e) {

            // 🚨 On exception → NEVER mark failed automatically
            Log::error('Razorpay reconciliation failed', [
                'gateway_order_id' => $gatewayOrderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'          => PaymentStatusEnum::PENDING->value, // SAFE DEFAULT
                'gateway_payload' => null,
                'paid_via'        => null,
                'error'           => $e->getMessage(),
            ];
        }
    }

    private function formatPaidVia(array $payment): string
    {
        return match ($payment['method'] ?? null) {

            'upi' => 'UPI (' . ($payment['vpa'] ?? 'UPI') . ')',

            'card' => 'Card (' .
                ucfirst($payment['card']['network'] ?? 'Card') .
                ' •••• ' .
                ($payment['card']['last4'] ?? 'XXXX') .
                ')',

            'netbanking' => 'NetBanking (' . strtoupper($payment['bank'] ?? 'Bank') . ')',

            'wallet' => 'Wallet (' . ucfirst($payment['wallet'] ?? 'Wallet') . ')',

            'emi' => 'EMI (' .
                ucfirst($payment['card']['network'] ?? 'Card') .
                ' •••• ' .
                ($payment['card']['last4'] ?? 'XXXX') .
                ')',

            default => 'Online Payment',
        };
    }
}
