<?php


namespace App\Services\Common\Payment\Gateways;

use Razorpay\Api\Api;
use RuntimeException;

class RazorpayBankVerificationService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(
            config('razorpay.key_id'),
            config('razorpay.key_secret')
        );
    }

    public function createContact(array $user): string
    {
        $contact = $this->api->contact->create([
            'name' => $user['name'],
            'email' => $user['email'],
            'contact' => $user['phone'],
            'type' => 'vendor',
        ]);

        return $contact['id'];
    }

    public function createFundAccount(
        string $contactId,
        array $bank
    ): string {
        $fund = $this->api->fund_account->create([
            'contact_id' => $contactId,
            'account_type' => 'bank_account',
            'bank_account' => [
                'name' => $bank['account_holder_name'],
                'ifsc' => $bank['ifsc_code'],
                'account_number' => $bank['account_number'],
            ],
        ]);

        return $fund['id'];
    }

    /**
     * ₹1 / ₹2 verification payout
     */
    public function verifyBank(
        string $fundAccountId,
        int $amountPaise = 100 // ₹1 default
    ): array {
        return $this->api->payout->create([
            'account_number' => config('razorpay.payout_account'),
            'fund_account_id' => $fundAccountId,
            'amount' => $amountPaise,
            'currency' => 'INR',
            'purpose' => 'payout',
            'mode' => 'IMPS',
            'queue_if_low_balance' => false,
        ])->toArray();
    }
}
