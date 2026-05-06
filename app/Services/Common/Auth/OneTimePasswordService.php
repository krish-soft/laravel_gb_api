<?php

namespace App\Services\Common\Auth;

use App\Models\Common\OneTimePassword;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OneTimePasswordService
{
    private ?OneTimePassword $otp = null;

    private const OTP_LENGTH = 6;
    private const EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * Generate or reuse OTP
     */
    public function generate(
        ?User $user,
        string $purpose,
        string $channel,
        array $data = []
    ): bool {
        $existingOtp = $this->findActiveOtp($user, $purpose, $data['phone_number'] ?? null);

        if ($existingOtp) {
            // Prevent spam resend
            if ($existingOtp->created_at->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
                $this->otp = $existingOtp;
                return true;
            }

            // Reuse existing OTP
            $this->otp = $existingOtp;
            return true;
        }

        // Create fresh OTP
        $this->otp = OneTimePassword::create([
            'user_id'      => $user?->exists ? $user->id : null,
            'purpose'      => $purpose,
            'channel'      => $channel,
            'dial_code'    => $data['dial_code'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'email'        => $data['email'] ?? null,
            'otp_code'     => $this->generateOtpCode(), // TODO:: Pending to Add Hash when API is ready
            'expires_at'   => now()->addMinutes(self::EXPIRY_MINUTES),
            'request_id'   => (string) Str::uuid(),
            'ip_address'   => request()->ip(),
            'user_agent'   => substr(request()->userAgent(), 0, 255),
            'attempts'     => 0,
        ]);

        return true;
    }

    /**
     * Send OTP
     */
    public function send(): bool
    {
        if (!$this->otp) {
            return false;
        }

        return $this->otp->channel === 'email'
            ? $this->sendEmailOtp()
            : $this->sendSmsOtp();
    }

    /**
     * Verify OTP
     */
    public function verify(
        ?User $user,
        string $purpose,
        string $requestId,
        string $otpCode,
        ?string $phoneNumber = null
    ): bool {

        $query = OneTimePassword::where('purpose', $purpose)
            ->where('request_id', $requestId)
            ->whereNull('verified_at');

        if ($user && $user->exists) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('phone_number', $phoneNumber);
        }

        $otp = $query->latest()->first();

        // Log::info("Attempting OTP verification: request_id={$requestId}, user_id={$user?->id}, phone_number={$phoneNumber}, otp_code={$otpCode}");

        if (
            !$otp ||
            $otp->expires_at->isPast() ||
            $otp->attempts >= self::MAX_ATTEMPTS
        ) {
            return false;
        }

        // Log::info("Verifying OTP: request_id={$requestId}, user_id={$user?->id}, phone_number={$phoneNumber}, attempts={$otp->attempts}");

        $otp->increment('attempts');

        if ($otp->otp_code !== $otpCode) {
            // if (!hash_equals($otp->otp_code, $otpCode)) {
            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Find active OTP
     */
    private function findActiveOtp(
        ?User $user,
        string $purpose,
        ?string $phoneNumber
    ): ?OneTimePassword {
        $query = OneTimePassword::where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now());

        if ($user && $user->exists) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('phone_number', $phoneNumber);
        }

        return $query->latest()->first();
    }

    /**
     * Request ID
     */
    public function requestId(): ?string
    {
        return $this->otp?->request_id;
    }

    /**
     * Email OTP
     */
    protected function sendEmailOtp(): bool
    {
        if (!$this->otp || !$this->otp->email) {
            return false;
        }

        Mail::raw(
            "Your OTP is {$this->otp->otp_code}. Valid for "
                . self::EXPIRY_MINUTES . " minutes.",
            fn($m) => $m->to($this->otp->email)->subject('OTP Verification')
        );

        return true;
    }

    /**
     * SMS OTP (stub)
     */
    protected function sendSmsOtp(): bool
    {
        // TODO:: remove
        // REMOVED: 2024-05-06 ON PROD
        // return true; // testing purpose

        // http://sms.krishnasoftware.com/sendsms.jsp?user=Kim001&password=9827177854XX&senderid=KlSHNA&mobiles=9825425385&sms=OTP+FOR+KhetBajar+Reg+IS+1206+FROM+KRISHNA+COMPUTER&tempid=1207165036851849479&responsein=json
        $response = Http::get("http://sms.krishnasoftware.com/sendsms.jsp?user=Kim001&password=9827177854XX&senderid=KlSHNA&mobiles={$this->otp->phone_number}&sms=OTP+FOR+KhetBajar+Reg+IS+{$this->otp->otp_code}+FROM+KRISHNA+COMPUTER&tempid=1207165036851849479&responsein=json");

        if ($response->successful() && $response->json('smslist.sms.status') === 'success') {
            // SMS sent successfully
            return true;
        } else {
            // Failed
            return false;
        }

        // return true;
    }

    /**
     * Fixed 6-digit OTP
     */
    private function generateOtpCode(): string
    {
        return str_pad(
            (string) random_int(0, 999999),
            self::OTP_LENGTH,
            '0',
            STR_PAD_LEFT
        );
    }
}
