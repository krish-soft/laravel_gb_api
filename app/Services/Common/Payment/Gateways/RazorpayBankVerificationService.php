<?php

namespace App\Services\Common\Payment\Gateways;

use App\Models\Common\User\Legal\UserBank;
use App\Models\User;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\Error as RazorpayError;
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

    /* =====================================================
     | GET OR CREATE CONTACT (ONCE PER USER)
     ===================================================== */
    public function getOrCreateContact(UserBank $bank, User $user): string
    {
        if ($bank->razorpay_contact_id) {
            return $bank->razorpay_contact_id;
        }

        try {
            $contact = $this->api->contact->create([
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->phone_number,
                'type' => 'vendor',
                'notes' => [
                    'user_id' => $user->id,
                    'user_code' => $user->user_code,
                    'bank_code' => $bank->bank_code,
                ],
            ]);

            $bank->update([
                'razorpay_contact_id' => $contact['id'],
            ]);

            logActivity(
                'razorpay_contact_created',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                ['razorpay_contact_id' => $contact['id']]
            );

            return $contact['id'];

        } catch (RazorpayError $e) {

            logActivity(
                'razorpay_contact_failed',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            throw new RuntimeException('Unable to create Razorpay contact');
        }
    }

    /* =====================================================
     | CREATE FUND ACCOUNT (ALWAYS NEW, OLD DEACTIVATED)
     ===================================================== */
    public function createFundAccount(UserBank $bank, string $contactId): string
    {
        if ($bank->razorpay_fund_account_id) {
            return $bank->razorpay_fund_account_id;
        }

        try {
            $fund = $this->api->fund_account->create([
                'contact_id' => $contactId,
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $bank->account_holder_name,
                    'ifsc' => $bank->ifsc_code,
                    'account_number' => decrypt($bank->account_number_encrypted),
                ],
                'notes' => [
                    'bank_code' => $bank->bank_code,
                    'user_id' => $bank->user_id,
                ],
            ]);

            $bank->update([
                'razorpay_fund_account_id' => $fund['id'],
                'is_razorpay_fund_account_status' => true,
            ]);

            logActivity(
                'razorpay_fund_account_created',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                ['razorpay_fund_account_id' => $fund['id']]
            );

            return $fund['id'];

        } catch (RazorpayError $e) {

            logActivity(
                'razorpay_fund_account_failed',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            throw new RuntimeException('Invalid bank details');
        }
    }

    /* =====================================================
     | ₹1 IMPS BANK VERIFICATION (NO WEBHOOK REQUIRED)
     ===================================================== */
    public function verifyBank(UserBank $bank): void
    {
        if ($bank->verified_at) {
            return;
        }

        if (!$bank->razorpay_fund_account_id) {
            throw new RuntimeException('Fund account missing');
        }

        try {
            $payout = $this->api->payout->create([
                'account_number' => config('razorpay.payout_account'),
                'fund_account_id' => $bank->razorpay_fund_account_id,
                'amount' => 100,
                'currency' => 'INR',
                'mode' => 'IMPS',
                'purpose' => 'payout',
                'queue_if_low_balance' => false,
                'notes' => [
                    'verify' => 'bank',
                    'bank_code' => $bank->bank_code,
                ],
            ]);

            $bank->update([
                'status' => 'verified',
                'verification_mode' => 'razorpay_test_deposit',
                'verified_at' => now(),
                'verified_by' => 'system',
                'test_deposit_verified_at' => now(),
                'test_deposit_amount' => 1,
                'test_deposit_ref' => $payout['id'],
            ]);

            logActivity(
                'bank_verified',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                ['razorpay_payout_id' => $payout['id']]
            );

        } catch (RazorpayError $e) {

            $bank->update([
                'status' => 'failed',
                'review_comment' => $e->getMessage(),
            ]);

            logActivity(
                'bank_verification_failed',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            throw new RuntimeException('Bank verification failed');
        }
    }

    /* =====================================================
     | DEACTIVATE FUND ACCOUNT (SAFE)
     ===================================================== */
    public function deactivateFundAccount(UserBank $bank): void
    {
        if (!$bank->razorpay_fund_account_id) {
            return;
        }

        try {
            $this->api->fund_account
                ->fetch($bank->razorpay_fund_account_id)
                ->edit(['active' => false]);

            logActivity(
                'razorpay_fund_account_deactivated',
                request()?->user() ?? null,
                UserBank::class,
                $bank->id,
                $bank->bank_code,
                []
            );

        } catch (\Exception $e) {
            // Silent fail – non-blocking
        }
    }


    /* =====================================================
 | START BANK VERIFICATION (MASTER METHOD)
 | CALL THIS AFTER addBank / updateBank
 ===================================================== */
    public function startVerification(UserBank $bank, User $user): void
    {
        // 🔒 Already verified → nothing to do
        if ($bank->verified_at) {
            return;
        }

        // 1️⃣ Create / Get Contact
        $contactId = $this->getOrCreateContact($bank, $user);

        // 2️⃣ Create / Get Fund Account
        $fundAccountId = $this->createFundAccount($bank, $contactId);

        // 3️⃣ Send ₹1 IMPS verification (NO WEBHOOK WAIT)
        $this->verifyBank($bank);
    }

}
