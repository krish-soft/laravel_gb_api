<?php

namespace App\Http\Controllers\Api\v1\User\Common\Auth;

use App\Http\Controllers\ApiResponseController;
use App\Models\User;
use App\Services\Common\Auth\OneTimePasswordService;
use Illuminate\Http\Request;

class UserResetPasswordApiController extends ApiResponseController
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
            'phone_number' => 'required|string',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

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
            'sms',
            ['phone_number' => $request->phone_number]
        );

        // Send OTP (SMS stub)
        if (!$otpService->send()) {
            return $this->showErrorMessage(
                'Failed to send OTP. Please try again later.',
                500
            );
        }

        return $this->successResponse(
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
            'phone_number' => 'required|string',
            'request_id'   => 'required|string',
            'otp'          => 'required|string|size:6',
            'password'     => 'required|string|min:6|confirmed',
        ]);

        $user = User::where('phone_number', $request->phone_number)->first();

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
            $request->phone_number
        )) {
            // return $this->showErrorMessage('Invalid or expired OTP', 422);
            return $this->showErrorMessage(__('messages.error_messages.invalid_or_expired_otp'), 422);
        }

        // Update password
        $user->update([
            'password' => bcrypt($request->password),
        ]);

        logActivity(
            'user_reset_password',
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'email' => $user->email,
            ]
        );


        // return $this->showSuccessMessage('Password reset successfully');
        return $this->showSuccessMessage(__('messages.success_messages.password_reset_successfully'));
    }
}
