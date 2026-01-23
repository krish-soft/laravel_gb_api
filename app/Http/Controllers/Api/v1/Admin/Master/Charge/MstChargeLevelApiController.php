<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Charge;

use App\Enum\User\UserRoleEnum;
use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Charge\MstChargeLevel;
use Illuminate\Http\Request;

class MstChargeLevelApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $mstChargeLevelsQuery = MstChargeLevel::latest();
        if ($request->has('is_active')) {
            $mstChargeLevelsQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        $mstChargeLevels = $mstChargeLevelsQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstChargeLevels);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'code' => 'required|unique:mst_charge_levels,code',
            'name' => 'required|string|max:100|unique:mst_charge_levels,name',
            'description' => 'required|string|min:5|max:255',
            'user_role_type' => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, UserRoleEnum::cases())),
        ]);

        $mstChargeLevel = MstChargeLevel::create($request->all());

        // Log activity
        logActivity(
            'charge_level_created',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstChargeLevel), // SUBJECT TYPE (what was affected)
            $mstChargeLevel->id,              // SUBJECT ID
            $mstChargeLevel->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstChargeLevel->name,
                'code' => $mstChargeLevel->code,
                'user_role_type' => $mstChargeLevel->user_role_type,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'));

        //
    }

    /**
     * Display the specified resource.
     */
    public function show(MstChargeLevel $mstChargeLevel)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstChargeLevel);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstChargeLevel $mstChargeLevel)
    {
        //
        $request->validate([
            'code' => 'required|unique:mst_charge_levels,code,' . $mstChargeLevel->id,
            'name' => 'required|string|max:100|unique:mst_charge_levels,name,' . $mstChargeLevel->id,
            'description' => 'required|string|min:5|max:255',
            'user_role_type' => 'required|string|in:' . implode(',', array_map(fn($case) => $case->value, UserRoleEnum::cases())),
        ]);

        $mstChargeLevel->update($request->all());

        // Log activity
        logActivity(
            'charge_level_updated',        // EVENT
            $request->user(),                 // ACTOR (who did it)
            get_class($mstChargeLevel), // SUBJECT TYPE (what was affected)
            $mstChargeLevel->id,              // SUBJECT ID
            $mstChargeLevel->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstChargeLevel->name,
                'code' => $mstChargeLevel->code,
                'user_role_type' => $mstChargeLevel->user_role_type,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstChargeLevel $mstChargeLevel)
    {
        //
        if ($mstChargeLevel->minimumChargeRules()->exists() || $mstChargeLevel->deliveryChargeRules()->exists()) {
            return $this->showErrorMessage(
                __('messages.error_messages.cannot_delete_used_in_transactions'),
                409
            );
        }

        // Log activity
        logActivity(
            'charge_level_deleted',        // EVENT
            request()->user(),                 // ACTOR (who did it)
            get_class($mstChargeLevel), // SUBJECT TYPE (what was affected)
            $mstChargeLevel->id,              // SUBJECT ID
            $mstChargeLevel->code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstChargeLevel->name,
                'code' => $mstChargeLevel->code,
                'user_role_type' => $mstChargeLevel->user_role_type,

            ]
        );
        $mstChargeLevel->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'));
    }
}
