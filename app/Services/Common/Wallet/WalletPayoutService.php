<?php


namespace App\Services\Common\Wallet;

use App\Enum\Common\Payment\PayoutStatusEnum;
use App\Models\Common\User\Legal\UserBank;
use App\Models\Common\Wallet\Wallet;
use App\Models\Common\Wallet\WalletPayout;
use App\Services\Common\Payment\Gateways\RazorpayPayoutService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletPayoutService
{
    public function request(
        Wallet $wallet,
        UserBank $bank,
        float $amount
    ): WalletPayout {

        if ($wallet->user_id !== $bank->user_id) {
            throw new RuntimeException('Bank does not belong to wallet owner');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Invalid payout amount');
        }

        if ($wallet->available_balance < $amount) {
            throw new RuntimeException('Insufficient wallet balance');
        }

        if (!$bank->verified_at) {
            throw new RuntimeException('Bank not verified');
        }

        if ($amount < 100) {
            throw new RuntimeException('Minimum payout is ₹100');
        }

        $exists = WalletPayout::where('wallet_id', $wallet->id)
            ->whereIn('status', [PayoutStatusEnum::REQUESTED->value, PayoutStatusEnum::PROCESSING->value])
            ->exists();

        if ($exists) {
            throw new RuntimeException('Another payout is already in progress');
        }

        return WalletPayout::create([
            'payout_code' => self::generateCode('PTO'),
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'user_bank_id' => $bank->id,
            'amount' => $amount,
            'status' => PayoutStatusEnum::REQUESTED->value,
            'requested_by' => request()->user()?->user_code ?? 'system',
            'requested_ip' => request()->ip(),
        ]);
    }

    public function approveAndProcess(WalletPayout $payout): void
    {
        DB::transaction(function () use ($payout) {

            if ($payout->status !== PayoutStatusEnum::REQUESTED->value) {
                return;
            }

            $gateway = app(RazorpayPayoutService::class);

            // 🔒 Merchant balance check (IMPORTANT)
            $balance = $gateway->fetchBalance();
            if (($balance['available']['balance'] ?? 0) < $payout->amount * 100) {
                throw new RuntimeException('Merchant Razorpay balance insufficient');
            }

            $response = $gateway->createPayout(
                $payout->bank->razorpay_fund_account_id,
                $payout->amount,
                $payout->payout_code
            );

            $payout->update([
                'razorpay_payout_id' => $response['id'],
                'status' => 'processing',
                'approved_by' => request()->user()?->user_code,
                'approved_at' => now(),
            ]);

            logActivity(
                'wallet_payout_initiated',
                request()->user() ?? null,
                WalletPayout::class,
                $payout->id,
                $payout->payout_code,
                ['amount' => $payout->amount]
            );
        });
    }

    public  static function generateCode(string $prefix): string
    {
        do {
            $code = $prefix . '-' . now()->format('Ymd') . '-' . random_int(100000, 999999);
        } while (
            WalletPayout::where('payout_code', $code)->exists()
        );

        return $code;
    }
}
