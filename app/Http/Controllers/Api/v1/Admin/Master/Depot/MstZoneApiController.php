<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Depot;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Depot\MstZone;
use Illuminate\Http\Request;

class MstZoneApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //

        $mstQuery = MstZone::with('state');
        if ($request->has('is_active')) {
            $mstQuery->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $mstZones = $mstQuery->get();


        return $this->successResponse(__('messages.success_messages.success_get'), $mstZones);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'state_id' => 'required|exists:mst_states,id',
            'name' => 'required|string|max:50|unique:mst_zones,name',
            'code' => 'required|string|max:50|unique:mst_zones,code',
        ]);

        $mstZone = MstZone::create($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'zone_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->code,       // SUBJECT CODE (human readable)
            [
                'state_id' => $mstZone->state_id,
                'zone_name' => $mstZone->name,
                'zone_code' => $mstZone->code,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstZone $mstZone)
    {
        //

        return $this->successResponse(__('messages.success_messages.success_get'), $mstZone);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstZone $mstZone)
    {
        //

        $request->validate([
            'state_id' => 'required|exists:mst_states,id',
            'name' => 'required|string|max:50|unique:mst_zones,name,' . $mstZone->id,
            'code' => 'required|string|max:50|unique:mst_zones,code,' . $mstZone->id,
        ]);



        $mstZone->update($request->all());

        $user = $request->user();

        // Log activity
        logActivity(
            'zone_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->code,       // SUBJECT CODE (human readable)
            [
                'state_id' => $mstZone->state_id,
                'name' => $mstZone->name,
                'code' => $mstZone->code,
            ]
        );
        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstZone $mstZone)
    {
        //

        // Check Existing Data Before Delete
        if ($mstZone->depots()->exists()) {
            return $this->errorResponse(__('messages.error_messages.cannot_delete_used_in_transactions'), 400);
        }

        $user = request()->user();

        // Log activity
        logActivity(
            'zone_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->code,       // SUBJECT CODE (human readable)
            [
                'state_id' => $mstZone->state_id,
                'name' => $mstZone->name,
                'code' => $mstZone->code,
            ]
        );
        $mstZone->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
