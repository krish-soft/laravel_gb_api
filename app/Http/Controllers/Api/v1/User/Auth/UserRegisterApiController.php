<?php

namespace App\Http\Controllers\Api\v1\User\Auth;

use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OneTimePasswordService;
use Illuminate\Http\Request;

class UserRegisterApiController extends ApiResponseController
{
    //

    /**
     * Send OTP for registration
     */
    public function sendRegistrationOtp(
        Request $request,
        OneTimePasswordService $otpService
    ) {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        // Check if user already exists
        if (User::where('phone_number', $request->phone_number)->exists()) {
            // return $this->showErrorMessage('Phone number already registered', 422);
            return $this->showErrorMessage(__('messages.error_messages.account_already_registered'), 422);
        }


        // Generate OTP
        $otpService->generate(
            null,
            'user_registration',
            'sms',
            [
                'phone_number' => $request->phone_number,
            ]
        );

        // Send OTP (SMS stub)
        if (!$otpService->send()) {
            return $this->showErrorMessage(
                __('messages.error_messages.failed_to_send_otp'),  // 'Failed to send OTP. Please try again later.',
                500
            );
        }

        return $this->showSuccessMessage(
            __('messages.success_messages.otp_sent_successfully'),
            [
                'request_id' => $otpService->requestId(),
            ]
        );
    }

    /**
     * Verify OTP and register user
     */
    public function verifyOtpAndRegister(
        Request $request,
        OneTimePasswordService $otpService
    ) {
        $request->validate([
            'phone_number' => 'required|string',
            'request_id'    => 'required|string',
            'otp'           => 'required|string|size:6',
            'name'          => 'required|string|max:100',
            'email'         => 'nullable|email|max:255|unique:users,email',
            'password'      => 'required|string|min:6|confirmed',
            'role'          => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, UserRoleEnum::cases())),
            'user_type'     => 'nullable|string|in:' . implode(',', array_map(fn($case) => $case->value, UserTypeEnum::cases())),
        ]);



        // Verify OTP
        if (!$otpService->verify(
            null,
            'user_registration',
            $request->request_id,
            $request->otp
        )) {
            // return $this->showErrorMessage('Invalid or expired OTP', 422);
            return $this->showErrorMessage(__('messages.error_messages.invalid_or_expired_otp'), 422);
        }


        // Update the price_level
        $priceLevelCode = match ($request->role) {
            UserRoleEnum::BUYER->value => 'B-STD',
            UserRoleEnum::SELLER->value => 'S-STD',
            UserRoleEnum::DELIVERY->value => 'D-STD',
            default => null,
        };

        $userType = match ($request->role) {
            UserRoleEnum::SELLER->value => 'farmer',
            UserRoleEnum::BUYER->value => 'trader',
            UserRoleEnum::DELIVERY->value => 'delivery',
            default => $request->user_type ?? null,
        };


        // Create User
        $user = User::create([
            'phone_number' => $request->phone_number,
            'name'          => $request->name,
            'email'         => $request->email ?? null,
            'password'      => bcrypt($request->password),
            'role'          => $request->role,
            'user_type' => $userType,
            'price_level_code' => $priceLevelCode,
            'sales_rep'     => $request->sales_rep ?? null,

        ]);

        return $this->showSuccessMessage(
            __('messages.success_messages.register_success')
        );
    }
}
