<?php

namespace App\Http\Controllers\Api\v1\Admin\AdminUser;

use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Admin\AdminUserTypeEnum;
use App\Enum\Common\Module\AppModuleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminUserAdminApiController extends ApiResponseWithAdminAuthController
{
    //

    public function index()
    {
        // $users = User::latest()
        //     ->whereIn('role', AdminRoleEnum::casesAsValues())
        //     ->get();
        $modules = collect(AppModuleEnum::casesAsArray())->pluck('label', 'value');

        $users = User::latest()
            ->whereIn('role', AdminRoleEnum::casesAsValues())
            ->get()
            ->map(function ($user) use ($modules) {

                if ($user->access_modules === ['*']) {
                    $user->access_modules_arr = ['ALL'];
                    return $user;
                }

                $user->access_modules_arr = collect($user->access_modules)
                    ->map(fn($v) => $modules[$v] ?? null)
                    ->filter()
                    ->values();

                return $user;
            });

        // 

        return $this->successResponse(__('messages.success_messages.success_get'), $users, 200);
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:' . implode(',', AdminUserTypeEnum::casesAsValues()),
            'role' =>   'required|in:' . implode(',', AdminRoleEnum::casesAsValues()),
            'access_modules' => 'required|array',
            'access_modules.*' => 'in:' . implode(',', [...AppModuleEnum::casesAsValues(), '*']),
        ]);

        // make sure user if super_admin then its access_modules always *
        if (isset($validatedData['role']) && $validatedData['role'] === AdminRoleEnum::SUPERADMIN->value) {
            $validatedData['access_modules'] = ['*'];
        }

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
            'role' => $validatedData['role'],
            'user_type' => $validatedData['user_type'],
            // 'access_modules' => $validatedData['access_modules'],
            'is_active' => true,
        ]);


        // Log activity
        logActivity(
            'admin_user_created',   // ACTIVITY TYPE
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'email' => $user->email,
                'role' => $user->role,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_create'), $user, 201);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user || !in_array($user->role, AdminRoleEnum::casesAsValues())) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        return $this->successResponse(__('messages.success_messages.success_get'), $user, 200);
    }


    public function update(Request $request, $id)
    {

        $user = User::find($id);

        if ($user->id === $request->user()->id) {
            return $this->showErrorMessage(__('messages.error_messages.cannot_update_self'), 403);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|required|string|min:8',
            'role' =>   'sometimes|required|in:' . implode(',', AdminRoleEnum::casesAsValues()),
            'user_type' => 'sometimes|required|in:' . implode(',', AdminUserTypeEnum::casesAsValues()),
            'access_modules' => 'sometimes|array',
            'access_modules.*' => 'in:' . implode(',', [...AppModuleEnum::casesAsValues(), '*']),
            'is_active' => 'sometimes|required|boolean',
            'inactive_reason' => 'nullable|string|max:255',
        ]);


        if (isset($validatedData['password'])) {
            $validatedData['password'] = bcrypt($validatedData['password']);
        }

        // make sure user if super_admin then its access_modules always *
        if (isset($validatedData['role']) && $validatedData['role'] === AdminRoleEnum::SUPERADMIN->value) {
            $validatedData['access_modules'] = ['*'];
        }

        $user->update($validatedData);

        // Log activity
        logActivity(
            'admin_user_updated',   // ACTIVITY TYPE
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'email' => $user->email,
                'role' => $user->role,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $user, 200);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if ($user->id === request()->user()->id) {
            return $this->showErrorMessage(__('messages.error_messages.cannot_update_self'), 403);
        }

        if (!$user || in_array($user->role, [AdminRoleEnum::SUPERADMIN->value])) {
            return $this->showErrorMessage(__('messages.error_messages.not_found'), 404);
        }

        // Log activity
        logActivity(
            'admin_user_deleted',   // ACTIVITY TYPE
            $user,                 // ACTOR (who did it)
            get_class($user), // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'email' => $user->email,
                'role' => $user->role,
            ]
        );

        $user->delete();

        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }




















    //
}
