<?php

namespace App\Services\Common\Payment\Gateways;

use Razorpay\Api\Api;

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

    public function createPayout(
        string $fundAccountId,
        float $amount,
        string $reference
    ): array {
        return $this->api->payout->create([
            'account_number' => config('razorpay.payout_account'),
            'fund_account_id' => $fundAccountId,
            'amount' => (int)($amount * 100),
            'currency' => 'INR',
            'mode' => 'IMPS',
            'purpose' => 'payout',
            'reference_id' => $reference,
        ])->toArray();
    }

    public function fetchBalance(): array
    {
        return $this->api->balance->fetch()->toArray();
    }


    public function fetchPayout(string $payoutId): ?array
    {
        try {
            return $this->api->payout
                ->fetch($payoutId)
                ->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }
}
