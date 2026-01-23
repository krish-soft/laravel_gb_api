<?php

namespace App\Http\Controllers\Api\v1\Admin\Master;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\MstState;
use Illuminate\Http\Request;

class MstStateApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $mstStateQuery = MstState::query();
        if ($request->has('is_active')) {
            $mstStateQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        $mstStates = $mstStateQuery->get();

        return $this->successResponse(__('messages.success_messages.success_get'), $mstStates);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'name' => 'required|string|max:255|unique:mst_states,name',
            'iso_code' => 'required|string|max:10|unique:mst_states,iso_code',
        ]);

        $mstState = MstState::create($request->all());
        $user = $request->user();

        // Log activity
        logActivity(
            'state_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstState), // SUBJECT TYPE (what was affected)
            $mstState->id,              // SUBJECT ID
            $mstState->iso_code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstState->name,
                'iso_code' => $mstState->iso_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstState $mstState)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstState);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstState $mstState)
    {
        //

        $request->validate([
            'name' => 'required|string|max:255|unique:mst_states,name,' . $mstState->id,
            'iso_code' => 'required|string|max:10|unique:mst_states,iso_code,' . $mstState->id,
        ]);

        $mstState->update($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'state_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstState), // SUBJECT TYPE (what was affected)
            $mstState->id,              // SUBJECT ID
            $mstState->iso_code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstState->name,
                'iso_code' => $mstState->iso_code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstState $mstState)
    {
        //
        $user = request()->user();

        // Log activity
        logActivity(
            'state_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstState), // SUBJECT TYPE (what was affected)
            $mstState->id,              // SUBJECT ID
            $mstState->iso_code,       // SUBJECT CODE (human readable)
            [
                'name' => $mstState->name,
                'iso_code' => $mstState->iso_code,
            ]
        );

        $mstState->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
