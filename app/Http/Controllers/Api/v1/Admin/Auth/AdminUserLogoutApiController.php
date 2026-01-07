<?php

namespace App\Http\Controllers\Api\v1\Admin\Auth;

use App\Http\Controllers\ApiResponseWithAuthController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminUserLogoutApiController extends ApiResponseWithAuthController
{
    //

    public function logout(Request $request)
    {
        //
        $user = $request->user();

        // Revoke the token that was used to authenticate the current request
        $user->currentAccessToken()->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_logout'));
    }


    // Delete All Tokens
    public function logoutAllDevices(Request $request)
    {
        //
        $user = $request->user();
        // Revoke all tokens for the user
        $user->tokens()->delete();

        return $this->showSuccessMessage(__('messages.success_messages.success_logout_all'));
    }
}
