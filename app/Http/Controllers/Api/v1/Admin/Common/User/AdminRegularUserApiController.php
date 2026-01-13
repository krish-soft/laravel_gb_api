<?php

namespace App\Http\Controllers\Api\v1\Admin\Common\User;

use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminRegularUserApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $users = User::latest()->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $users, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'phone_number' => 'required|string',
            'name'          => 'required|string|max:100',
            // 'email'         => 'nullable|email|max:255|unique:users,email', // Optional email
            'role'          => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, UserRoleEnum::cases())),
            'user_type'     => 'nullable|string|in:' . implode(',', array_map(fn($case) => $case->value, UserTypeEnum::cases())),
        ]);


        // Update the price_level
        $priceLevelCode = match ($request->role) {
            UserRoleEnum::BUYER->value => 'B-STD',
            UserRoleEnum::SELLER->value => 'S-STD',
            UserRoleEnum::DELIVERY->value => 'D-STD',
            default => null,
        };
        if (empty($request->user_type)) {
            $userType = match ($request->role) {
                UserRoleEnum::SELLER->value => UserTypeEnum::FARMER->value,
                UserRoleEnum::BUYER->value => UserTypeEnum::TRADER->value,
                UserRoleEnum::DELIVERY->value => UserTypeEnum::DELIVERY->value,
                default => null,
            };
        } else {
            $userType = $request->user_type;
        }


        $user = User::create([
            'phone_number' => $request->phone_number,
            'name'          => $request->name,
            'email'         => $request->email,
            'password'      => bcrypt(Str::random(12)), // Random password
            'role'          => $request->role,
            'user_type'     => $userType,
            'price_level_code' => $priceLevelCode,
        ]);

        // Log activity
        logActivity(
            'admin_user_created',
            $request->user(),       // ACTOR (who did it)
            get_class($user),       // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'user_code' => $user->user_code,
                'role' => $user->role,
                'user_type' => $user->user_type,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_create'), $user, 201);




        //
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $user, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //

        $request->validate([
            'phone_number'  => 'sometimes|unique:users,phone_number,' . $user->id,
            'name'          => 'sometimes|string|max:100',
            'email'         => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'role'          => 'sometimes|string|in:' . implode(',', array_map(fn($case) => $case->value, UserRoleEnum::cases())),
            'user_type'     => 'sometimes|string|in:' . implode(',', array_map(fn($case) => $case->value, UserTypeEnum::cases())),
        ]);


        $user->update($request->only([
            'phone_number',
            'name',
            'email',
            'role',
            'user_type',
        ]));

        // Log activity
        logActivity(
            'admin_user_updated',
            $request->user(),       // ACTOR (who did it)
            get_class($user),       // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'user_code' => $user->user_code,
                'role' => $user->role,
                'user_type' => $user->user_type,
            ]
        );

        return $this->successResponse(__('messages.success_messages.success_update'), $user, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //

        // Prevent deleting
        return $this->errorResponse(__('messages.error_messages.user_detlete_prohibited'), 403);


        // Log activity
        logActivity(
            'admin_user_deleted',
            request()->user(),       // ACTOR (who did it)
            get_class($user),       // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'user_code' => $user->user_code,
                'role' => $user->role,
                'user_type' => $user->user_type,
            ]
        );

        $user->delete();


        return $this->successResponse(__('messages.success_messages.success_delete'), null, 200);
    }


    /**
     *  User Depots
     */
    public function addDepot(Request $request, User $user)
    {
        //
        $request->validate([
            'depot_id' => 'required|exists:mst_depots,id',
            'is_primary' => 'sometimes|boolean',
        ]);

        $user = $user->depots()->create([
            'depot_id' => $request->depot_id,
            'is_primary' => $request->is_primary ?? false,
        ]);


        // Log activity
        logActivity(
            'user_depot_added',
            $request->user(),       // ACTOR (who did it)
            get_class($user),       // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'depot_id' => $request->depot_id,
                'is_primary' => $request->is_primary ?? false,
            ]
        );


        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    public function removeDepot(Request $request, User $user, $depotId)
    {

        $depot = $user->depots()->where('depot_id', $depotId)->firstOrFail();
        $depot->delete();

        // Log activity
        logActivity(
            'user_depot_removed',
            $request->user(),       // ACTOR (who did it)
            get_class($user),       // SUBJECT TYPE (what was affected)
            $user->id,              // SUBJECT ID
            $user->user_code,       // SUBJECT CODE (human readable)
            [
                'depot_id' => $request->depot_id,
                'is_primary' => $request->is_primary ?? false,
            ]
        );


        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }



    //
}
