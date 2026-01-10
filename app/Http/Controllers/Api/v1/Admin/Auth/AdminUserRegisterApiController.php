<?php

namespace App\Http\Controllers\Api\v1\Admin\Auth;

use App\Enum\User\AdminRoleEnum;
use App\Enum\User\AdminUserTypeEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OneTimePasswordService;
use Illuminate\Http\Request;

class AdminUserRegisterApiController extends ApiResponseController
{
    //


    public function register(
        Request $request,
    ) {
        $request->validate([
            'name'          => 'required|string|max:100',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'required|string|min:6|confirmed',
            'role'          => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, AdminRoleEnum::cases())),
            'user_type'     => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, AdminUserTypeEnum::cases())),
        ]);

        // Create Admin User
        $user = User::create([
            'email' => $request->email,
            'name'          => $request->name,
            'email'         => $request->email ?? null,
            'password'      => bcrypt($request->password),
            'role'          => $request->role,
            'user_type' => $request->user_type,
            'sales_rep'     => $request->sales_rep ?? null,

        ]);

        // Log activity
        logActivity(
            'user_registration',
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'user_code' => $user->user_code,
                'email' => $user->email,
            ]
        );

        return $this->showSuccessMessage(
            __('messages.success_messages.register_success')
        );
    }
}
