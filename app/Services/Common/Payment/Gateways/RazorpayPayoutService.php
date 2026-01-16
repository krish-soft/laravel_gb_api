<?php

namespace App\Services\Common\Payment\Gateways;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error as RazorpayError;
use RuntimeException;

class RazorpayPayoutService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('razorpay.key_id'),
            config('razorpay.key_secret')
        );
    }

    /**
     * Create Razorpay Payout
     */
    public function createPayout(
        string $fundAccountId,
        float  $amount,
        string $reference
    ): array
    {
        try {
            $payout = $this->api->payout->create([
                'account_number' => config('razorpay.payout_account'),
                'fund_account_id' => $fundAccountId,
                'amount' => (int)round($amount * 100),
                'currency' => 'INR',
                'mode' => 'IMPS',
                'purpose' => 'payout',
                'reference_id' => $reference,
                'notes' => [
                    'reference' => $reference,
                    'source' => 'wallet_payout',
                ],
            ])->toArray();

            logActivity(
                'razorpay_payout_created',
                request()?->user() ?? null,
                null,
                null,
                $reference,
                [
                    'razorpay_payout_id' => $payout['id'] ?? null,
                    'amount' => $amount,
                ]
            );

            return $payout;

        } catch (RazorpayError $e) {

            logActivity(
                'razorpay_payout_failed',
                request()?->user() ?? null,
                null,
                null,
                $reference,
                [
                    'fund_account_id' => $fundAccountId,
                    'amount' => $amount,
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to process payout at this time. Please try again later.'
            );
        }
    }

    /**
     * Fetch Merchant Balance
     */
    public function fetchBalance(): array
    {
        try {
            $balance = $this->api->balance->fetch()->toArray();

            logActivity(
                'razorpay_balance_fetched',
                request()?->user() ?? null,
                null,
                null,
                null,
                [
                    'available' => $balance['available'] ?? null,
                ]
            );

            return $balance;

        } catch (RazorpayError $e) {

            logActivity(
                'razorpay_balance_fetch_failed',
                request()?->user() ?? null,
                null,
                null,
                null,
                [
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to fetch payout balance. Please try again later.'
            );
        }
    }

    /**
     * Fetch Razorpay Payout (ADMIN / RECONCILIATION)
     */
    public function fetchPayout(string $payoutId): ?array
    {
        try {
            $payout = $this->api->payout
                ->fetch($payoutId)
                ->toArray();

            logActivity(
                'razorpay_payout_fetched',
                request()?->user() ?? null,
                null,
                null,
                $payoutId,
                [
                    'status' => $payout['status'] ?? null,
                ]
            );

            return $payout;

        } catch (RazorpayError $e) {

            logActivity(
                'razorpay_payout_fetch_failed',
                request()?->user() ?? null,
                null,
                null,
                $payoutId,
                [
                    'error_code' => $e->getCode(),
                    'error_message' => $e->getMessage(),
                ]
            );

            // ❗ fetch is non-critical → return null
            return null;
        }
    }
}
