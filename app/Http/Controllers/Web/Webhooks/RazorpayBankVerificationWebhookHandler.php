<?php

namespace App\Http\Controllers\Web\Webhooks;

use App\Models\Common\User\Legal\UserBank;

class RazorpayBankVerificationWebhookHandler
{
    public function handle(array $payload): void
    {
        $payoutId = data_get($payload, 'payload.payout.entity.id');
        $status = data_get($payload, 'payload.payout.entity.status');
        $bankCode = data_get($payload, 'payload.payout.entity.notes.bank_code');

        if (!$payoutId || !$bankCode) {
            return;
        }

        $userBank = UserBank::where('bank_code', $bankCode)
            ->where('test_deposit_ref', $payoutId)
            ->first();

        if (!$userBank) {
            return;
        }

        // 🔒 Idempotency
        if ($userBank->verified_at) {
            return;
        }

        if ($status === 'processed') {

            $userBank->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => 'system',
                'verified_user_id' => null,
                'test_deposit_verified_at' => now(),
                'is_razorpay_fund_account_status' => true,
            ]);

            logActivity(
                'bank_verified_success',
                null,
                UserBank::class,
                $userBank->id,
                $userBank->bank_code,
                ['payout_id' => $payoutId]
            );
        }

        if ($status === 'failed') {

            $userBank->update([
                'status' => 'failed',
                'review_comment' => 'Verification payout failed',
            ]);

            logActivity(
                'bank_verification_failed',
                null,
                UserBank::class,
                $userBank->id,
                $userBank->bank_code,
                ['payout_id' => $payoutId]
            );
        }
    }
}
