<?php

namespace App\Http\Controllers\Api\v1\User\Auth;

use App\Enum\User\UserRoleEnum;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserLoginApiController extends ApiResponseController
{
    //


    public function login(Request $request)
    {
        //
        $request->validate([
            // 'dial_code' => 'required|string',
            'phone_number' => 'required|string',
            'password' => 'required|string',

        ]);

        // Check for User Existence and Password Match (prevent user enumeration)
        $user = User::where('phone_number', $request->phone_number)
            ->whereIn('role', array_map(fn($c) => $c->value, UserRoleEnum::cases())) // Only allow specific roles
            ->first();


        if (!$user) {
            // return $this->showErrorMessage('We could not find an account associated with the provided information.', 404);
            return $this->showErrorMessage(__('messages.error_messages.account_not_associate'), 404);
        }

        if (!password_verify($request->password, $user->password)) {
            return $this->showErrorMessage(__('messages.error_messages.invalid_credentials'), 401);
        }


        // Determine expiry based on relogin

        $expiryMinutes = 30 * 24 * 60; // Default 30 days

        // Get or generate device_id
        $deviceId = $request->input('device_id');
        if (!$deviceId) {
            $deviceId = bin2hex(random_bytes(16));
        }

        // Limit the number of devices per user
        $maxDevices = 2; // Set in config/auth.php or fallback to 3

        // Get all tokens for this user
        $tokens = $user->tokens()->orderBy('created_at')->get();

        // If device count exceeds max, remove oldest tokens
        if ($tokens->count() >= $maxDevices) {
            $tokensToDelete = $tokens->take($tokens->count() - $maxDevices + 1);
            foreach ($tokensToDelete as $token) {
                $token->delete();
            }
        }

        // Generate token with expiry and device_id as abilities
        $token = $user->createToken(
            "auth_token|role:{$user->role}|device_id:{$deviceId}",
            [],
            now()->addMinutes($expiryMinutes)
        )->plainTextToken;

        // 

        $userData = [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in_minutes' => $expiryMinutes,
            'device_id' => $deviceId,
        ];


        return $this->successResponse(__('messages.success_messages.success_login'), $userData, 200);


        //
    }
}
