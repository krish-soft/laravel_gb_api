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
            'zone_name' => 'required|string|max:255|unique:mst_zones,zone_name',
            'zone_code' => 'required|string|max:50|unique:mst_zones,zone_code',
        ]);

        $mstZone = MstZone::create($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'zone_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->zone_code,       // SUBJECT CODE (human readable)
            [
                'zone_name' => $mstZone->zone_name,
                'zone_code' => $mstZone->zone_code,
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
            'zone_name' => 'required|string|max:255|unique:mst_zones,zone_name,' . $mstZone->id,
            'zone_code' => 'required|string|max:50|unique:mst_zones,zone_code,' . $mstZone->id,
        ]);

        $mstZone->update($request->all());

        $user = $request->user();

        // Log activity
        logActivity(
            'zone_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->zone_code,       // SUBJECT CODE (human readable)
            [
                'zone_name' => $mstZone->zone_name,
                'zone_code' => $mstZone->zone_code,
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

        $user = request()->user();
        // Log activity
        logActivity(
            'zone_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstZone), // SUBJECT TYPE (what was affected)
            $mstZone->id,              // SUBJECT ID
            $mstZone->zone_code,       // SUBJECT CODE (human readable)
            [
                'zone_name' => $mstZone->zone_name,
                'zone_code' => $mstZone->zone_code,
            ]
        );
        $mstZone->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
