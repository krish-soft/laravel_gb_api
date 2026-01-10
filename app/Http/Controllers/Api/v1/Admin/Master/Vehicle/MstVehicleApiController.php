<?php

namespace App\Http\Controllers\Api\v1\Admin\Master\Vehicle;

use App\Http\Controllers\ApiResponseWithAdminAuthController;
use App\Http\Controllers\Controller;
use App\Models\Master\Vehicle\MstVehicle;
use Illuminate\Http\Request;

class MstVehicleApiController extends ApiResponseWithAdminAuthController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        $mstVehicles = MstVehicle::all();
        return $this->successResponse(__('messages.success_messages.success_get'), $mstVehicles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'vehicle_name' => 'required|string|max:255|unique:mst_vehicles,vehicle_name',
            'vehicle_code' => 'required|string|max:50|unique:mst_vehicles,vehicle_code',
            'body_type' => 'required|string|max:100',
            'capacity_class' => 'required|string|max:100',
            'max_weight_kg' => 'required|numeric|min:1',
            'max_volume_cft' => 'required|numeric|min:1',
            'max_crates' => 'required|integer|min:1',
        ]);

        $mstVehicle = MstVehicle::create($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'vehicle_created',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstVehicle), // SUBJECT TYPE (what was affected)
            $mstVehicle->id,              // SUBJECT ID
            $mstVehicle->vehicle_code,       // SUBJECT CODE (human readable)
            [
                'vehicle_name' => $mstVehicle->vehicle_name,
                'vehicle_code' => $mstVehicle->vehicle_code,
                'body_type' => $mstVehicle->body_type,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_create'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MstVehicle $mstVehicle)
    {
        //
        return $this->successResponse(__('messages.success_messages.success_get'), $mstVehicle);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MstVehicle $mstVehicle)
    {
        //
        $request->validate([
            'vehicle_name' => 'required|string|max:255|unique:mst_vehicles,vehicle_name,' . $mstVehicle->id,
            'body_type' => 'required|string|max:100',
            'capacity_class' => 'required|string|max:100',
            'max_weight_kg' => 'required|numeric|min:1',
            'max_volume_cft' => 'required|numeric|min:1',
            'max_crates' => 'required|integer|min:1',
        ]);

        $mstVehicle->update($request->all());

        $user = $request->user();
        // Log activity
        logActivity(
            'vehicle_updated',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstVehicle), // SUBJECT TYPE (what was affected)
            $mstVehicle->id,              // SUBJECT ID
            $mstVehicle->vehicle_code,       // SUBJECT CODE (human readable)
            [
                'vehicle_name' => $mstVehicle->vehicle_name,
                'vehicle_code' => $mstVehicle->vehicle_code,
                'body_type' => $mstVehicle->body_type,
            ]
        );

        return $this->showSuccessMessage(__('messages.success_messages.success_update'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MstVehicle $mstVehicle)
    {
        //

        $user = request()->user();
        // Log activity
        logActivity(
            'vehicle_deleted',        // EVENT
            $user,                 // ACTOR (who did it)
            get_class($mstVehicle), // SUBJECT TYPE (what was affected)
            $mstVehicle->id,              // SUBJECT ID
            $mstVehicle->vehicle_code,       // SUBJECT CODE (human readable)
            [
                'vehicle_name' => $mstVehicle->vehicle_name,
                'vehicle_code' => $mstVehicle->vehicle_code,
                'body_type' => $mstVehicle->body_type,
            ]
        );

        $mstVehicle->delete();
        return $this->showSuccessMessage(__('messages.success_messages.success_delete'), 200);
    }
}
