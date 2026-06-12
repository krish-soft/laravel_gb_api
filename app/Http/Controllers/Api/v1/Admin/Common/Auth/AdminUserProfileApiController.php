<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\Auth;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use Illuminate\Http\Request;

class AdminUserProfileApiController extends ApiResponseWithAdminAuthController
{
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! password_verify($request->input('current_password'), $user->password)) {
            return $this->showErrorMessage(__('messages.error_messages.invalid_current_password'), 422);
        }

        $user->update([
            'password' => bcrypt($request->input('new_password')),
        ]);

        logActivity(
            'admin_user_password_updated',
            $user,
            get_class($user),
            $user->id,
            $user->user_code,
            [
                'email' => $user->email,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }
}