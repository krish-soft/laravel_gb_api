<?php

namespace App\Http\Controllers\Api\v1\Admin\Auth;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OneTimePasswordService;
use Illuminate\Http\Request;

class AdminUserResetPasswordApiController extends ApiResponseController
{
    //

    /**
     * Send OTP for password reset
     */
    public function sendForgotPasswordOtp(
        Request $request,
        OneTimePasswordService $otpService
    ) {
        $request->validate([
            'email' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->showErrorMessage(
                'No account found with this phone number',
                404
            );
        }

        // Generate OTP (user exists → user_id stored)
        $otpService->generate(
            $user,
            'forgot_password',
            'email',
            ['email' => $request->email]
        );

        // Send OTP (email stub)
        if (!$otpService->send()) {
            return $this->showErrorMessage(
                'Failed to send OTP. Please try again later.',
                500
            );
        }

        return $this->showSuccessMessage(
            'OTP sent successfully',
            ['request_id' => $otpService->requestId()]
        );
    }

    /**
     * Verify OTP and reset password
     */
    public function resetPassword(
        Request $request,
        OneTimePasswordService $otpService
    ) {
        $request->validate([
            'email' => 'required|string',
            'request_id'   => 'required|string',
            'otp'          => 'required|string|size:6',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->showErrorMessage(
                // 'We could not find an account associated with the provided information.',
                __('messages.error_messages.account_not_associate'),
                404
            );
        }

        // Verify OTP
        if (!$otpService->verify(
            $user,
            'forgot_password',
            $request->request_id,
            $request->otp,
            $request->email
        )) {
            // return $this->showErrorMessage('Invalid or expired OTP', 422);
            return $this->showErrorMessage(__('messages.error_messages.invalid_or_expired_otp'), 422);
        }

        // Update password
        $user->update([
            'password' => bcrypt($request->password),
        ]);

        // return $this->showSuccessMessage('Password reset successfully');
        return $this->showSuccessMessage(__('messages.success_messages.password_reset_successfully'));
    }
}
