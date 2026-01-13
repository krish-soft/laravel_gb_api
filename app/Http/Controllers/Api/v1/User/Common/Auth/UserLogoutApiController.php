<?php

namespace App\Http\Controllers\Api\v1\User\Common\Auth;

use App\Http\Controllers\ApiResponseWithAuthController;
use Illuminate\Http\Request;

class UserLogoutApiController extends ApiResponseWithAuthController
{
    //

    public function logout(Request $request)
    {
        //
        $user = $request->user();

        // Revoke the token that was used to authenticate the current request
        $user->currentAccessToken()->delete();

        // Log activity
        logActivity(
            'user_logout',
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'logout_type' => 'single_device',
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_logout'));
    }


    // Delete All Tokens
    public function logoutAllDevices(Request $request)
    {
        //
        $user = $request->user();
        // Revoke all tokens for the user
        $user->tokens()->delete();

        // Log activity
        logActivity(
            'user_logout',
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'logout_type' => 'all_devices',
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_logout_all'));
    }
}
